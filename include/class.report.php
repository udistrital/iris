<?php

class ReportModel {

    const PERM_AGENTS = 'stats.agents';

    static protected $perms = array(
            self::PERM_AGENTS => array(
                'title' =>
                /* @trans */ 'Stats',
                'desc'  =>
                /* @trans */ 'Ability to view stats of other agents in allowed departments',
                'primary' => true,
            ));

    static function getPermissions() {
        return self::$perms;
    }
}

RolePermission::register(/* @trans */ 'Miscellaneous', ReportModel::getPermissions());

class OverviewReport {
    var $start;
    var $end;
    static $end_choices = [
        'now' => 'Up to today',
        '+7 days' => 'One Week',
        '+14 days' => 'Two Weeks',
        '+1 month' => 'One Month',
        '+3 months' => 'One Quarter'
    ];

    var $format;

    function __construct($start, $end='now', $format=null) {
        global $cfg;

        $this->start = Format::sanitize($start);
        $this->end = array_key_exists($end, self::$end_choices) ? $end : 'now';
        $this->format = $format ?: $cfg->getDateFormat(true);
    }


    function getStartDate($format=null, $translate=true) {

        if (!$this->start)
            return '';

        $format =  $format ?: $this->format;
        if ($translate) {
            $format = str_replace(
                    array('y', 'Y', 'm'),
                    array('y', 'yy', 'mm'),
                    $format);
        }

        return Format::date(Misc::dbtime($this->start), false, $format);
    }


    function getDateRange() {
        global $cfg;

        $start = $this->start ?: 'last month';
        $stop = $this->end ?: 'now';

        // Convert user time to db time
        $start = Misc::dbtime($start);
        // Stop time can be relative.
        if ($stop[0] == '+') {
            // $start time + time(X days)
            $now = time();
            $stop = $start + (strtotime($stop, $now)-$now);
        } else {
            $stop = Misc::dbtime($stop);
        }

        $start = 'FROM_UNIXTIME('.$start.')';
        $stop = 'FROM_UNIXTIME('.$stop.')';

        return array($start, $stop);
    }

    function getPlotData() {
        list($start, $stop) = $this->getDateRange();
        $states = array("created", "closed", "reopened", "assigned", "transferred");
        $event_ids = Event::getIds();

        # Fetch all types of events over the timeframe
        $res = db_query('SELECT DISTINCT(E.name) FROM '.THREAD_EVENT_TABLE
            .' T JOIN '.EVENT_TABLE . ' E ON E.id = T.event_id'
            .' WHERE timestamp BETWEEN '.$start.' AND '.$stop
            .' AND T.event_id IN ('.implode(",",$event_ids).') AND T.thread_type = "A"'
            .' AND E.name IN (\'created\', \'assigned\', \'closed\', \'reopened\')'
            .' ORDER BY 1');
        $events = array();
        while ($row = db_fetch_row($res)) $events[] = __($row[0]);

        # TODO: Handle user => db timezone offset
        # XXX: Implement annulled column from the %ticket_event table
        $res = db_query('SELECT H.name, DATE_FORMAT(timestamp, \'%Y-%m-%d\'), '
                .'COUNT(DISTINCT E.id)'
            .' FROM '.THREAD_EVENT_TABLE. ' E '
            . ' LEFT JOIN '.EVENT_TABLE. ' H
                ON (E.event_id = H.id)'
            .' WHERE E.timestamp BETWEEN '.$start.' AND '.$stop
            .' AND NOT annulled'
            .' AND E.event_id IN ('.implode(",",$event_ids).')'
            .' GROUP BY E.event_id, DATE_FORMAT(E.timestamp, \'%Y-%m-%d\')'
            .' ORDER BY 2, 1');
        # Initialize array of plot values
        $plots = array();
        foreach ($events as $e) { $plots[$e] = array(); }

        $time = null; $times = array();
        # Iterate over result set, adding zeros for missing ticket events
        $slots = array();
        while ($row = db_fetch_row($res)) {
            $row_time = strtotime($row[1]);
            if ($time != $row_time) {
                # New time (and not the first), figure out which events did
                # not have any tickets associated for this time slot
                if ($time !== null) {
                    # Not the first record -- add zeros all the arrays that
                    # did not have at least one entry for the timeframe
                    foreach (array_diff($events, $slots) as $slot)
                        $plots[$slot][] = 0;
                }
                $slots = array();
                $times[] = $time = $row_time;
            }
            # Keep track of states for this timeframe
            $slots[] = __($row[0]);
            $plots[__($row[0])][] = (int)$row[2];
        }
        foreach (array_diff($events, $slots) as $slot)
            $plots[$slot][] = 0;

        return array("times" => $times, "plots" => $plots, "events" => $events);
    }

    function enumTabularGroups() {
        global $thisstaff;
        $tabs = array();
        if ($thisstaff->getManagedDepartments() || $thisstaff->hasPerm(ReportModel::PERM_AGENTS))
            $tabs["dept"] = __("Dependencia");
        if ($thisstaff->getManagedDepartments() || count($thisstaff->teams->values_flat('team_id')))
            $tabs["team"] = __("Teams");
        $tabs["staff"] = __("Agents");
        return $tabs;
    }

    function getTabularData($group='dept') {
        global $thisstaff;

        $event_ids = Event::getIds();
        $event = function ($name) use ($event_ids) {
            return $event_ids[$name];
        };
        $dash_headers = array(__('Created'),__('Assigned'),__('Closed'),__('Reopened'),
                              __('Service Time'));

        list($start, $stop) = $this->getDateRange();
        $times = ThreadEvent::objects()
            ->constrain(array(
                'thread__entries' => array(
                    'thread__entries__type' => 'R',
                    ),
               ))
            ->constrain(array(
                'thread__events' => array(
                    'thread__events__event_id' => $event('created'),
                    'event_id' => $event('closed'),
                    'annulled' => 0,
                    ),
                ))
            ->filter(array(
                    'timestamp__range' => array($start, $stop, true),
               ))
            ->aggregate(array(
                'ServiceTime' => SqlAggregate::AVG(SqlFunction::timestampdiff(
                  new SqlCode('DAY'), new SqlField('thread__task__created'), new SqlField('thread__task__closed'))
                ),
            ));
            $queryAssignTeam = array('data__contains' => '"team"');
            $queryAssigned = Q::all(array('event_id' => $event('assigned')));
            if ($group == 'team')
                $queryAssigned->add($queryAssignTeam);
            elseif ($group == 'staff')
                $queryAssigned->add(Q::not($queryAssignTeam));

            $stats = ThreadEvent::objects()
                ->filter(array(
                        'annulled' => 0,
                        'timestamp__range' => array($start, $stop, true),
                        'thread_type' => 'A',
                        'event_id__in' => array($event('created'), $event('assigned'), $event('closed'), $event('reopened'))
                   ))
                ->aggregate(array(
                    'Created' => SqlAggregate::COUNT(
                        SqlCase::N()
                            ->when(new Q(array('event_id' => $event('created'), 'staff_id' => 0)), 1)
                    ),
                    'Assigned' => SqlAggregate::COUNT(
                        SqlCase::N()
                            ->when($queryAssigned, 1)
                    ),
                    'Closed' => SqlAggregate::COUNT(
                        SqlCase::N()
                            ->when(new Q(array('event_id' => $event('closed'))), 1)
                    ),
                    'Reopened' => SqlAggregate::COUNT(
                        SqlCase::N()
                            ->when(new Q(array('event_id' => $event('reopened'))), 1)
                    ),
                ));

        switch ($group) {
        case 'dept':
            $headers = array(__('Dependencia'));
            $header = function($row) { return Dept::getLocalNameById($row['dept_id'], $row['dept__name']); };
            $pk = 'dept__id';
            $depts = ($thisstaff->isAdmin() ? $thisstaff->getDepts() : ($thisstaff->getManagedDepartments() ?: array(0)));
            $stats = $stats
                ->filter(array('dept_id__in' => $depts))
                ->values('dept__id', 'dept__name', 'dept__flags')
                ->distinct('dept__id');
            $times = $times
                ->filter(array('dept_id__in' => $depts))
                ->values('dept__id')
                ->distinct('dept__id');
            break;
        case 'team':
            $headers = array(__('Team'));
            $header = function($row) { return $row['team__name']; };
            $pk = 'team_id';
            $teams = ($thisstaff->getManagedDepartments() ? array_keys(Team::getTeams(array('dept_id' => $thisstaff->getDeptId()))) : $thisstaff->teams->values_flat('team_id'));
            if (empty($teams))
                return array("columns" => array_merge($headers, $dash_headers),
                      "data" => array());
            $stats = $stats
                ->values('team', 'team__name', 'team__flags')
                ->filter(array('dept_id' => $thisstaff->getDeptId(), 'team_id__gt' => 0, 'team_id__in' => $teams))
                ->distinct('team_id');
            $times = $times
                ->values('team_id')
                ->filter(array('team_id__gt' => 0))
                ->distinct('team_id');
            break;
        case 'staff':
            $headers = array(__('Agent'));
            $header = function($row) { return new AgentsName(array(
                'first' => $row['staff__firstname'], 'last' => $row['staff__lastname'])); };
            $pk = 'staff_id';
            $staff = Staff::getStaffMembers();
            $stats = $stats
                ->values('staff_id', 'staff__firstname', 'staff__lastname', 'agent', 'agent__firstname', 'agent__lastname')
                ->filter(array('staff_id__in' => (array_merge(array(0), array_keys($staff)))))
                ->distinct('staff_id', 'agent')
                ->order_by('-staff_id');
            $times = $times
                ->values('staff_id')
                ->filter(array('staff_id__in' => array_merge(array(0), array_keys($staff))))
                ->distinct('staff_id');
            $depts = $thisstaff->getManagedDepartments();
            if ($thisstaff->hasPerm(ReportModel::PERM_AGENTS))
                $depts = array_merge($depts, $thisstaff->getDepts());
            if ($depts)
                $Q = Q::any(array('dept_id__in' => $depts));
            else
                $Q = Q::any(array('staff_id' => $thisstaff->getId()));
            $stats = $stats->filter($Q);
            $times = $times->filter($Q);
            break;
        default:
            # XXX: Die if $group not in $groups
        }

        $timings = array();
        foreach ($times as $T) {
            $timings[$T[$pk]] = $T;
        }
        $rows = array();
        $staff = array();
        foreach ($stats as $row) {
            if ($row['staff_id'] > 0) {
                $staff[$row['staff_id']] = $row;
            } elseif ($row['agent'] > 0 && !isset($staff[$row['agent']])) {
                $row['staff__firstname'] = $row['agent__firstname'];
                $row['staff__lastname'] = $row['agent__lastname'];
                $staff[$row['agent']] = $row;
            } else {
                $staff[$row['agent']]['Created'] = $row['Created'];
            }
        }
        foreach ($staff as $R) {
          if (isset($R['dept__flags'])) {
            if ($R['dept__flags'] & Dept::FLAG_ARCHIVED)
              $status = ' - '.__('Archived');
            elseif ($R['dept__flags'] & Dept::FLAG_ACTIVE)
              $status = '';
            else
              $status = ' - '.__('Disabled');
          }
          if (isset($R['topic__flags'])) {
            if ($R['topic__flags'] & Topic::FLAG_ARCHIVED)
              $status = ' - '.__('Archived');
            elseif ($R['topic__flags'] & Topic::FLAG_ACTIVE)
              $status = '';
            else
              $status = ' - '.__('Disabled');
          }

            $T = $timings[$R[$pk]];
            $rows[] = array($header($R) . $status, $R['Created'], $R['Assigned'],
                $R['Closed'], $R['Reopened'],
                number_format($T['ServiceTime'], 1));
        }
        return array("columns" => array_merge($headers, $dash_headers),
                     "data" => $rows);
    }
}

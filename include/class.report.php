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
        $stop = 'FROM_UNIXTIME(' . ($stop + 86400) . ')';
        
        return array($start, $stop);
    }

    

    function getPlotData() {
        $tableData = $this->getTabularData();
        
        // Initialize containers
        $labels = [];
        $plots = [];
        $events = [];
        
        // Extract header/column names (excluding the first one which is labels)
        if (!empty($tableData["headers"]) && count($tableData["headers"]) > 1) {
            $events = array_slice($tableData["headers"], 1);
            
            // Initialize empty arrays for each event type
            foreach ($events as $event) {
                $eventKey = strtolower(str_replace(' ', '_', $event));
                $plots[$eventKey] = [];
            }
        }
        
        // Get all rows except the last one (total row)
        $dataRows = array_slice($tableData["data"], 0, count($tableData["data"]) - 1);
        
        foreach ($dataRows as $index => $row) {
            // First column is the label
            $labels[] = $row[0];
            
            // Process each metric value dynamically
            for ($i = 1; $i < count($row); $i++) {
                $eventKey = strtolower(str_replace(' ', '_', $events[$i - 1]));
                $plots[$eventKey][$index] = (int)$row[$i];
            }
        }
        
        $times = range(0, count($labels) - 1);
        
        return [
            "times" => $times, 
            "plots" => $plots, 
            "events" => array_keys($plots), 
            "labels" => $labels
        ];
    }

    function enumTabularGroups() {
        global $thisstaff;
        $tabs = array();
        if ($thisstaff->getManagedDepartments() || $thisstaff->hasPerm(ReportModel::PERM_AGENTS))
            $tabs["dept"] = __("Dependencias");
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
        $dash_headers = array_merge($group === 'team' ? array() : array(__('Created')), array(__('Assigned'),__('Closed'),__('Abiertos')),
            $group === 'dept' ? array('Transferidos') : array());
    
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
            ));
            $queryAssignTeam = array('data__contains' => '"team"');
            $queryAssigned = Q::all(array('event_id' => $event('assigned')));
            if ($group == 'team')
                $queryAssigned->add($queryAssignTeam);
            elseif ($group == 'staff')
                $queryAssigned->add(Q::not($queryAssignTeam));
    
            $openTasks = TaskModel::objects()
                ->filter(array(
                    'flags' => 1,
                    'updated__range' => array($start, $stop, true)
                ))
                ->values($group === 'dept' ? 'dept_id' : ($group === 'team' ? 'team_id' : 'staff_id'))
                ->annotate(array(
                    'count' => SqlAggregate::COUNT('id')
                ));
                
            $openTasksCount = array();
            foreach ($openTasks as $task) {
                $key = $group === 'dept' ? $task['dept_id'] : ($group === 'team' ? $task['team_id'] : $task['staff_id']);
                $openTasksCount[$key] += $task['count'];
            }

            $stats = ThreadEvent::objects()
                ->filter(array(
                        'annulled' => 0,
                        'timestamp__range' => array($start, $stop, true),
                        'thread_type' => 'A',
                        'event_id__in' => array_merge(
                            $group === 'team' ? array() : array($event('created')),
                            array($event('assigned'), $event('closed')),
                            $group === 'dept' ? array($event('transferred')) : array())
                   ))
                ->aggregate(array_merge(
                    $group === 'team' ? array() : array('Created' => SqlAggregate::COUNT(
                        SqlCase::N()
                            ->when(new Q(array('event_id' => $event('created'), 'staff_id' => 0)), 1)
                    )),
                    array('Assigned' => SqlAggregate::COUNT(
                        SqlCase::N()
                            ->when($queryAssigned, 1)
                    ),
                    'Closed' => SqlAggregate::COUNT(
                        SqlCase::N()
                            ->when(new Q(array('event_id' => $event('closed'))), 1)
                    )),
                    $group === 'dept' ? array('Transferred' => SqlAggregate::COUNT(
                        SqlCase::N()
                            ->when(new Q(array('event_id' => $event('transferred'))), 1)
                    )) : array(),
                ));
    
        switch ($group) {
        case 'dept':
            $headers = array(__('Dependencia'));
            $header = function($row) { return Dept::getLocalNameById($row['dept_id'], $row['dept__name']); };
            $pk = 'dept__id';
            $roleName = $thisstaff->getRole()->getName();
            if ($roleName !== 'Administrador dependencia') {
                // Si no tiene el rol adecuado, no puede ver nada
                return [
                    'headers' => [__('Dependencia')],
                    'data' => []
                ];
            }
            $depts = $thisstaff->getDepts();


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
            $stats = $stats
                ->values('staff_id', 'staff__firstname', 'staff__lastname', 'agent', 'agent__firstname', 'agent__lastname')
                ->distinct('staff_id', 'agent')
                ->order_by('-staff_id');
            $times = $times
                ->values('staff_id')
                ->distinct('staff_id');
            $roleName = $thisstaff->getRole()->getName();
            $depts = ($roleName === 'Administrador dependencia') ? $thisstaff->getDepts() : [];

            if ($thisstaff->hasPerm(ReportModel::PERM_AGENTS))
                $depts = array_merge($depts, $thisstaff->getDepts());
            if ($depts)
                $Q = Q::any(array('dept_id__in' => $depts));
            else
                $Q = Q::any(Q::all(array('staff_id' => $thisstaff->getId(), 'event_id__in' => array(2, 3, 4))))
                    ->add(Q::all(array('agent' => $thisstaff->getId(), 'event_id' => 1)));
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
        if ($group === 'staff')
            foreach ($stats as $row) {
                if ($row['staff_id'] > 0 && !isset($staff[$row['staff_id']])) {
                    $staff[$row['staff_id']] = $row;
                    $staff[$row['staff_id']]['Open'] = isset($openTasksCount[$row['staff_id']]) ? $openTasksCount[$row['staff_id']] : 0;
                } elseif ($row['staff_id'] > 0 && isset($staff[$row['staff_id']])) {
                    $staff[$row['staff_id']]['Created'] += $row['Created'];
                    $staff[$row['staff_id']]['Assigned'] += $row['Assigned'];
                    $staff[$row['staff_id']]['Closed'] += $row['Closed'];
                } elseif ($row['agent'] > 0 && !isset($staff[$row['agent']])) {
                    $row['staff__firstname'] = $row['agent__firstname'];
                    $row['staff__lastname'] = $row['agent__lastname'];
                    $staff[$row['agent']] = $row;
                    $staff[$row['agent']]['Open'] = isset($openTasksCount[$row['agent']]) ? $openTasksCount[$row['agent']] : 0;
                } elseif ($row['agent'] > 0 && $row['Created'] > 0) {
                    $staff[$row['agent']]['Created'] = $row['Created'];
                }
            }
        else {
            foreach ($stats as $rowIndex => $row) {
                $key = $group === 'dept' ? $row['dept__id'] : ($group === 'team' ? $row['team'] : null);
                $row['Open'] = isset($openTasksCount[$key]) ? $openTasksCount[$key] : 0;
                $staff[$rowIndex] = $row;
            }
        }
        
        $total = array('Created' => 0, 'Assigned' => 0, 'Open' => 0, 'Transferred' => 0, 'Closed' => 0);
        foreach ($staff as $R) {
          $total['Created'] += isset($R['Created']) ? $R['Created'] : 0;
          $total['Assigned'] += isset($R['Assigned']) ? $R['Assigned'] : 0;
          $total['Open'] += isset($R['Open']) ? $R['Open'] : 0;
          $total['Transferred'] += isset($R['Transferred']) ? $R['Transferred'] : 0;
          $total['Closed'] += isset($R['Closed']) ? $R['Closed'] : 0;
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
    
            $T = isset($timings[$R[$pk]]) ? $timings[$R[$pk]] : null;
            $rows[] = array_merge(array($header($R) . (isset($status) ? $status : '')), 
                $group === 'team' ? array() : array(isset($R['Created']) ? $R['Created'] : 0), 
                array(
                    isset($R['Assigned']) ? $R['Assigned'] : 0,
                    isset($R['Closed']) ? $R['Closed'] : 0, 
                    isset($R['Open']) ? $R['Open'] : 0
                ), 
                $group === 'dept' ? array(isset($R['Transferred']) ? $R['Transferred'] : 0) : array());
        }
        $rows[] = array_merge(array_merge(array('TOTAL'), $group === 'team' ? array() : array($total['Created']),
           array($total['Assigned'], $total['Closed'], $total['Open']),
           $group === 'dept' ? array($total['Transferred']) : array()));
        return array("columns" => array_merge($headers, $dash_headers),
                     "data" => $rows);
    }
}

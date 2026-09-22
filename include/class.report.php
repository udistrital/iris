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
    var $date_range;
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

    function getPeriod() {
        return $this->end;
    }

    static function calculateEndTimestamp($start, $period, $now) {
        if ($period === 'now')
            return $now;

        return strtotime($period, $start);
    }

    function getDateRangeTimestamps() {
        if (isset($this->date_range))
            return $this->date_range;

        $start = Misc::dbtime($this->start ?: 'last month');
        $now = Misc::dbtime('now');
        $stop = self::calculateEndTimestamp($start, $this->end, $now);

        return $this->date_range = array($start, $stop);
    }

    static function formatDateRangeForDisplay($range, $timezone,
            $format='F j, Y', $dbTimezone='UTC') {
        $formatted = array();
        $tz = new DateTimeZone($timezone);
        $dbtz = new DateTimeZone($dbTimezone);

        foreach ($range as $timestamp) {
            $databaseDate = new DateTime('@'.$timestamp);
            $gmtTimestamp = $timestamp - $dbtz->getOffset($databaseDate);
            $date = new DateTime('@'.$gmtTimestamp);
            $date->setTimezone($tz);
            $formatted[] = $date->format($format);
        }

        return $formatted;
    }

    static function formatDateRangeForQuery($range) {
        return array(
            'FROM_UNIXTIME('.$range[0].')',
            'FROM_UNIXTIME('.$range[1].')',
        );
    }

    function getDateRange() {
        $range = $this->getDateRangeTimestamps();

        // The ORM compiles __range as SQL BETWEEN, so both boundaries are
        // inclusive. For "now", the upper boundary is the current instant;
        // relative periods end exactly at the calculated timestamp.
        return self::formatDateRangeForQuery($range);
    }

    

    function getPlotData($group='dept', $tableData=null) {
        if ($tableData === null)
            $tableData = $this->getTabularData($group);

        return self::formatPlotData($tableData);
    }

    static function formatPlotData($tableData) {
        $columns = $tableData['columns'] ?? array();
        $dataRows = $tableData['data'] ?? array();
        $labels = array();
        $plots = array();

        foreach (array_slice($columns, 1) as $column) {
            $eventKey = strtolower(preg_replace('/\s+/', '_', trim((string) $column)));
            $plots[$eventKey] = array();
        }

        // getTabularData() adds TOTAL to count reports. Specialized reports
        // without a total row must retain their final data row.
        if ($dataRows) {
            $lastRow = end($dataRows);
            if (isset($lastRow[0])
                    && strtoupper(trim((string) $lastRow[0])) === 'TOTAL')
                array_pop($dataRows);
        }

        $events = array_keys($plots);
        foreach ($dataRows as $index => $row) {
            $labels[] = isset($row[0]) ? (string) $row[0] : '';
            foreach ($events as $eventIndex => $eventKey) {
                $value = $row[$eventIndex + 1] ?? 0;
                $plots[$eventKey][$index] = is_numeric($value)
                    ? $value + 0
                    : 0;
            }
        }

        return array(
            'times' => $labels ? range(0, count($labels) - 1) : array(),
            'plots' => $plots,
            'events' => $events,
            'labels' => $labels,
        );
    }

    /**
     * Departments whose statistics the agent may inspect.
     *
     * System administrators retain their normal department scope. Department
     * managers can inspect the departments they manage, and any role carrying
     * stats.agents grants access for the department where that role applies.
     */
    static function getAuthorizedDepartmentIds($staff) {
        $available = array_map('intval', $staff->getDepts());
        $managed = array_map('intval', $staff->getManagedDepartments());
        $managedMap = array_fill_keys($managed, true);
        $isAdmin = method_exists($staff, 'isAdmin') && $staff->isAdmin();
        $authorized = array();

        foreach (array_unique(array_merge($available, $managed)) as $deptId) {
            if (!$deptId)
                continue;
            $role = $staff->getRole($deptId);
            if ($isAdmin || isset($managedMap[$deptId])
                    || ($role && $role->hasPerm(ReportModel::PERM_AGENTS))) {
                $authorized[$deptId] = true;
            }
        }

        return array_keys($authorized);
    }

    function enumTabularGroups() {
        global $thisstaff;
        $tabs = array();
        $authorizedDeptIds = self::getAuthorizedDepartmentIds($thisstaff);

        if ($authorizedDeptIds)
            $tabs["dept"] = __("Dependencias");
        if ($authorizedDeptIds || count($thisstaff->teams->values_flat('team_id')))
            $tabs["team"] = __("Teams");

        if ($authorizedDeptIds) {
            $tabs["staff"] = __("Agents");
            $tabs["response_time"] = __("Tiempo de respuesta");
        }

        return $tabs;
    }

    static function mergeAuthorizedActivity($rows, $stats, $group) {
        foreach ($stats as $row) {
            if ($group === 'staff')
                $id = (int) $row['staff_id'];
            elseif ($group === 'team')
                $id = (int) ($row['team_id'] ?? $row['team']);
            else
                continue;

            // Event rows never expand the authorized catalog.
            if (!$id || !isset($rows[$id]))
                continue;

            $rows[$id]['Assigned'] += $row['Assigned'] ?? 0;
            $rows[$id]['Closed'] += $row['Closed'] ?? 0;
        }

        return $rows;
    }

    static function countCreatedEventsByAgent($events) {
        $counts = array();
        $seen = array();

        foreach ($events as $event) {
            $eventId = (int) ($event['id'] ?? 0);
            $agentId = (int) ($event['uid'] ?? 0);

            if (!$eventId || !$agentId || ($event['uid_type'] ?? null) !== 'S')
                continue;
            if (isset($seen[$eventId]))
                continue;

            $seen[$eventId] = true;
            $counts[$agentId] = ($counts[$agentId] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Build per-agent response-time intervals from task lifecycle events.
     *
     * Creation-to-assignment belongs to the agent receiving the first staff
     * assignment. Assignment-to-close belongs to the agent assigned when the
     * task is closed. The event completing each interval must be inside the
     * requested range; its starting event may predate the range.
     */
    static function calculateResponseTimeIntervals($createdEvents,
            $assignedEvents, $closedEvents, $reopenedEvents, $rangeStart,
            $rangeStop, $authorizedStaffIds) {
        $authorized = array_fill_keys(array_map('intval', $authorizedStaffIds), true);
        $createdByThread = array();
        $assignedByThread = array();
        $closedByThread = array();
        $reopenedByThread = array();

        $timestamp = function($event) {
            $value = $event['timestamp'] ?? null;
            if (is_int($value) || is_float($value) || ctype_digit((string) $value))
                return (int) $value;
            $value = $value ? strtotime($value) : false;
            return $value === false ? null : $value;
        };
        $isStaffAssignment = function($event) {
            $data = $event['data'] ?? null;
            if (is_string($data) && $data !== '')
                $data = json_decode($data, true);

            // A team assignment retains the task's staff_id snapshot. Do not
            // mistake that snapshot for a new assignment to the agent.
            return !(is_array($data)
                && isset($data['team'])
                && !isset($data['staff'])
                && !isset($data['claim']));
        };
        $seen = array();
        $appendUnique = function(&$target, $event, $time) use (&$seen) {
            $id = (int) ($event['id'] ?? 0);
            $type = $event['_response_type'] ?? 'event';
            if ($id && isset($seen[$type][$id]))
                return;
            if ($id)
                $seen[$type][$id] = true;
            $event['_response_timestamp'] = $time;
            $target[] = $event;
        };

        foreach ($createdEvents as $event) {
            $threadId = (int) ($event['thread_id'] ?? 0);
            $time = $timestamp($event);
            if (!$threadId || $time === null)
                continue;
            if (!isset($createdByThread[$threadId])
                    || $time < $createdByThread[$threadId])
                $createdByThread[$threadId] = $time;
        }

        foreach ($assignedEvents as $event) {
            $threadId = (int) ($event['thread_id'] ?? 0);
            $staffId = (int) ($event['staff_id'] ?? 0);
            $time = $timestamp($event);
            if (!$threadId || !$staffId || $time === null
                    || !$isStaffAssignment($event))
                continue;
            $event['_response_type'] = 'assigned';
            $appendUnique($assignedByThread[$threadId], $event, $time);
        }

        foreach ($closedEvents as $event) {
            $threadId = (int) ($event['thread_id'] ?? 0);
            $time = $timestamp($event);
            if (!$threadId || $time === null)
                continue;
            $event['_response_type'] = 'closed';
            $appendUnique($closedByThread[$threadId], $event, $time);
        }

        foreach ($reopenedEvents as $event) {
            $threadId = (int) ($event['thread_id'] ?? 0);
            $time = $timestamp($event);
            if (!$threadId || $time === null)
                continue;
            $event['_response_type'] = 'reopened';
            $appendUnique($reopenedByThread[$threadId], $event, $time);
        }

        $sortEvents = function(&$events) {
            usort($events, function($a, $b) {
                $byTime = $a['_response_timestamp'] <=> $b['_response_timestamp'];
                return $byTime ?: ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
            });
        };
        foreach ($assignedByThread as &$events)
            $sortEvents($events);
        unset($events);
        foreach ($closedByThread as &$events)
            $sortEvents($events);
        unset($events);
        foreach ($reopenedByThread as &$events)
            $sortEvents($events);
        unset($events);

        $result = array(
            'created_to_assigned' => array(),
            'assigned_to_closed' => array(),
        );

        foreach ($assignedByThread as $threadId => $assignments) {
            $first = $assignments[0];
            $staffId = (int) $first['staff_id'];
            $assignedAt = $first['_response_timestamp'];
            $createdAt = $createdByThread[$threadId] ?? null;
            if ($createdAt !== null
                    && isset($authorized[$staffId])
                    && $assignedAt >= $rangeStart
                    && $assignedAt <= $rangeStop
                    && $assignedAt >= $createdAt) {
                $result['created_to_assigned'][$staffId][] = $assignedAt - $createdAt;
            }
        }

        foreach ($closedByThread as $threadId => $closures) {
            $assignments = $assignedByThread[$threadId] ?? array();
            if (!$assignments)
                continue;

            foreach ($closures as $closed) {
                $closedAt = $closed['_response_timestamp'];
                $staffId = (int) ($closed['staff_id'] ?? 0);
                if (!isset($authorized[$staffId])
                        || $closedAt < $rangeStart || $closedAt > $rangeStop)
                    continue;

                $lastReopenedAt = null;
                foreach ($reopenedByThread[$threadId] ?? array() as $reopened) {
                    if ($reopened['_response_timestamp'] <= $closedAt)
                        $lastReopenedAt = $reopened['_response_timestamp'];
                    else
                        break;
                }

                $lastAssignment = null;
                foreach ($assignments as $assignment) {
                    $assignedAt = $assignment['_response_timestamp'];
                    if ($assignedAt > $closedAt)
                        break;
                    if ($lastReopenedAt !== null && $assignedAt < $lastReopenedAt)
                        continue;
                    $lastAssignment = $assignment;
                }

                // Do not combine a closure with an assignment to another
                // agent or with an assignment from a previous open cycle.
                if (!$lastAssignment
                        || (int) $lastAssignment['staff_id'] !== $staffId)
                    continue;

                $duration = $closedAt - $lastAssignment['_response_timestamp'];
                if ($duration >= 0)
                    $result['assigned_to_closed'][$staffId][] = $duration;
            }
        }

        return $result;
    }

    static function averageResponseHours($intervals) {
        return $intervals
            ? round(array_sum($intervals) / count($intervals) / 3600, 2)
            : null;
    }


    function getTabularData($group='dept') {
        global $thisstaff;

        // Apply the same authorization to direct exports and JSON requests as
        // to the tabs rendered in the dashboard.
        $allowedGroups = $this->enumTabularGroups();
        if (!array_key_exists($group, $allowedGroups))
            return array('columns' => array(), 'data' => array());

        $event = [
            'created' => 1,
            'assigned' => 4,
            'closed'  => 2,
            'transferred' => 6
        ];



        list($start, $stop) = $this->getDateRange();
        $authorizedDeptIds = self::getAuthorizedDepartmentIds($thisstaff);

        $dash_headers = array_merge(
            $group === 'team' ? array() : array(__('Created')),
            array(__('Assigned'), __('Closed'), __('Abiertos')),
            $group === 'dept' ? array(__('Transferidos')) : array()
        );

        if (!$authorizedDeptIds && $group === 'dept') {
            return array(
                'columns' => array_merge(array(__('Dependencia')), $dash_headers),
                'data' => array(),
            );
        }
        if (!$authorizedDeptIds && $group === 'staff') {
            return array(
                'columns' => array_merge(array(__('Agent')), $dash_headers),
                'data' => array(),
            );
        }
        if (!$authorizedDeptIds && $group === 'response_time') {
            return array(
                'columns' => array(
                    __('Agente'),
                    __('Prom. creado a asignado (h)'),
                    __('Prom. asignado a cerrado (h)'),
                ),
                'data' => array(),
            );
        }

        $createdByAgent = array();
        if ($group === 'staff') {
            // Creation belongs to the staff actor (uid), independently of the
            // current assignee in staff_id. This intentionally includes
            // creation events for tasks which were never assigned.
            $createdEvents = ThreadEvent::objects()
                ->filter(array(
                    'event_id' => $event['created'],
                    'timestamp__range' => array($start, $stop, true),
                    'thread_type' => 'A',
                    'annulled' => 0,
                    'uid_type' => 'S',
                    'dept_id__in' => $authorizedDeptIds,
                ))
                ->values('id', 'uid', 'uid_type')
                ->distinct('id');

            $createdByAgent = self::countCreatedEventsByAgent($createdEvents);
        }

        $openTaskFilters = array(
            'flags__hasbit' => TaskModel::ISOPEN,
        );
        if (in_array($group, array('staff', 'response_time')))
            $openTaskFilters['dept_id__in'] = $authorizedDeptIds;
        elseif ($group === 'team')
            $openTaskFilters['dept_id__in'] = $authorizedDeptIds
                ?: array($thisstaff->getDeptId());

        $openTasks = TaskModel::objects()
            ->filter($openTaskFilters)
            ->values($group === 'dept' ? 'dept_id' : ($group === 'team' ? 'team_id' : 'staff_id'))
            ->annotate(array('count' => SqlAggregate::COUNT('id')));

        $openTasksCount = [];
        foreach ($openTasks as $task) {
            $key = $group === 'dept' ? $task['dept_id'] : ($group === 'team' ? $task['team_id'] : $task['staff_id']);
            $openTasksCount[$key] = ($openTasksCount[$key] ?? 0) + $task['count'];
        }

        $base_stats = ThreadEvent::objects()
            ->filter(array(
                'annulled' => 0,
                'timestamp__range' => array($start, $stop, true),
                'thread_type' => 'A',
                'event_id__in' => array_merge(
                    $group === 'dept' ? array($event['created']) : array(),
                    array($event['assigned'], $event['closed']),
                    $group === 'dept' ? array($event['transferred']) : array()
                )
            ));


        $fields = $group === 'staff'
            ? array('staff_id', 'staff__firstname', 'staff__lastname')
            : ($group === 'team'
                ? array('team', 'team__name', 'team__flags')
                : array('dept__id', 'dept__name', 'dept__flags'));

        call_user_func_array(array($base_stats, 'values'), $fields);

        $stats = $base_stats->aggregate(array_merge(
            $group === 'dept' ? array(
                'Created' => SqlAggregate::COUNT(
                    SqlCase::N()->when(new Q(array('event_id' => $event['created'])), 1)
                )
            ) : array(),
            array(
                'Assigned' => SqlAggregate::COUNT(
                    SqlCase::N()->when(new Q(array('event_id' => $event['assigned'])), 1)
                ),
                'Closed' => SqlAggregate::COUNT(
                    SqlCase::N()->when(new Q(array('event_id' => $event['closed'])), 1)
                )
            ),
            $group === 'dept' ? array(
                'Transferred' => SqlAggregate::COUNT(
                    SqlCase::N()->when(new Q(array('event_id' => $event['transferred'])), 1)
                )
            ) : array()
        ));


        $times = ThreadEvent::objects()
            ->constrain(array(
                'thread__entries' => array('thread__entries__type' => 'R'),
            ))
            ->constrain(array(
                'thread__events' => array(
                    'thread__events__event_id' => $event['created'],
                    'event_id' => $event['closed'],
                    'annulled' => 0,
                ),
            ))
            ->filter(array('timestamp__range' => array($start, $stop, true)));

        // Active agents and authorized teams are included from their catalogs
        // below even when they have no activity in the selected period. Locked
        // agents are intentionally excluded; disabled teams remain visible.
        $authorizedStaff = array();
        $authorizedTeams = array();

        switch ($group) {
            case 'response_time':
                $headers = array(
                    __('Agente'),
                    __('Prom. creado a asignado (h)'),
                    __('Prom. asignado a cerrado (h)'),
                );

                $validStaffIds = [];
                foreach (Staff::objects()
                        ->filter(array(
                            'dept_id__in' => $authorizedDeptIds,
                            'isactive' => 1,
                        )) as $staff)
                    $validStaffIds[] = $staff->getId();

                if (!$validStaffIds) {
                    return array(
                        'columns' => $headers,
                        'data' => array(),
                    );
                }

                // Select tasks whose interval is completed in the requested
                // period. Their starting event may be older than the period.
                $assignmentCompletions = ThreadEvent::objects()
                    ->filter(array(
                        'event_id' => $event['assigned'],
                        'timestamp__range' => array($start, $stop, true),
                        'thread_type' => 'A',
                        'annulled' => 0,
                        'staff_id__in' => $validStaffIds,
                        'dept_id__in' => $authorizedDeptIds,
                    ))
                    ->values('thread_id');

                $closedEvents = array();
                $closedQuery = ThreadEvent::objects()
                    ->filter(array(
                        'event_id' => $event['closed'],
                        'timestamp__range' => array($start, $stop, true),
                        'thread_type' => 'A',
                        'annulled' => 0,
                        'staff_id__in' => $validStaffIds,
                        'dept_id__in' => $authorizedDeptIds,
                    ))
                    ->values('id', 'thread_id', 'staff_id', 'timestamp')
                    ->distinct('id');

                $candidateThreadIds = array();
                foreach ($assignmentCompletions as $assignment)
                    $candidateThreadIds[(int) $assignment['thread_id']] = true;
                foreach ($closedQuery as $closed) {
                    $closedEvents[] = $closed;
                    $candidateThreadIds[(int) $closed['thread_id']] = true;
                }
                unset($candidateThreadIds[0]);
                $candidateThreadIds = array_keys($candidateThreadIds);

                $createdEvents = array();
                $assignedEvents = array();
                $reopenedEvents = array();
                if ($candidateThreadIds) {
                    $historyRange = array('FROM_UNIXTIME(0)', $stop, true);
                    foreach (ThreadEvent::objects()
                            ->filter(array(
                                'event_id' => $event['created'],
                                'timestamp__range' => $historyRange,
                                'thread_type' => 'A',
                                'annulled' => 0,
                                'thread_id__in' => $candidateThreadIds,
                            ))
                            ->values('id', 'thread_id', 'timestamp')
                            ->distinct('id') as $created) {
                        $createdEvents[] = $created;
                    }
                    foreach (ThreadEvent::objects()
                            ->filter(array(
                                'event_id' => $event['assigned'],
                                'timestamp__range' => $historyRange,
                                'thread_type' => 'A',
                                'annulled' => 0,
                                'thread_id__in' => $candidateThreadIds,
                            ))
                            ->values('id', 'thread_id', 'staff_id', 'data', 'timestamp')
                            ->distinct('id') as $assigned) {
                        $assignedEvents[] = $assigned;
                    }
                    foreach (ThreadEvent::objects()
                            ->filter(array(
                                'event_id' => Event::getIdByName('reopened'),
                                'timestamp__range' => $historyRange,
                                'thread_type' => 'A',
                                'annulled' => 0,
                                'thread_id__in' => $candidateThreadIds,
                            ))
                            ->values('id', 'thread_id', 'timestamp')
                            ->distinct('id') as $reopened) {
                        $reopenedEvents[] = $reopened;
                    }
                }

                list($rangeStart, $rangeStop) = $this->getDateRangeTimestamps();
                $intervals = self::calculateResponseTimeIntervals(
                    $createdEvents,
                    $assignedEvents,
                    $closedEvents,
                    $reopenedEvents,
                    $rangeStart,
                    $rangeStop,
                    $validStaffIds
                );
                $createdToAssigned = $intervals['created_to_assigned'];
                $assignedToClosed = $intervals['assigned_to_closed'];

                $rows = [];
                foreach ($validStaffIds as $staffId) {
                    $agent = Staff::lookup($staffId);
                    $name = $agent ? new AgentsName([
                        'first' => $agent->getFirstName(),
                        'last' => $agent->getLastName()
                    ]) : 'N/A';

                    $avg1 = self::averageResponseHours(
                        $createdToAssigned[$staffId] ?? array()
                    );
                    $avg2 = self::averageResponseHours(
                        $assignedToClosed[$staffId] ?? array()
                    );

                    $rows[] = array(
                        $name,
                        $avg1 === null ? '-' : $avg1,
                        $avg2 === null ? '-' : $avg2,
                    );
                }

                usort($rows, function($a, $b) {
                    $aVal = $a[1];
                    $bVal = $b[1];

                    if (!is_numeric($aVal) && !is_numeric($bVal)) return 0;
                    if (!is_numeric($aVal)) return 1;
                    if (!is_numeric($bVal)) return -1;
                    return $bVal <=> $aVal;
                });


                return array('columns' => $headers, 'data' => $rows);

            case 'dept':
                $headers = array(__('Dependencia'));
                $header = function($row) {
                    return Dept::getLocalNameById($row['dept_id'], $row['dept__name']);
                };
                $pk = 'dept__id';

                $stats = $stats
                    ->filter(array('dept_id__in' => $authorizedDeptIds))
                    ->values('dept__id', 'dept__name', 'dept__flags')
                    ->distinct('dept__id');
                $times = $times
                    ->filter(array('dept_id__in' => $authorizedDeptIds))
                    ->values('dept__id')
                    ->distinct('dept__id');
                break;
        case 'team':
            $headers = array(__('Team'));
            $header = function($row) { return $row['team__name']; };
            $pk = 'team_id';
            $teams = array();
            foreach ($thisstaff->teams->values_flat('team_id') as $team)
                $teams[] = (int) $team[0];
            if ($authorizedDeptIds) {
                foreach (Team::objects()
                        ->filter(array(
                            'members__staff__dept_id__in' => $authorizedDeptIds,
                            'members__staff__isactive' => 1,
                        ))
                        ->distinct('team_id') as $team) {
                    $teams[] = $team->getId();
                }
                $teams = array_values(array_unique(array_map('intval', $teams)));
            }
            if (empty($teams))
                return array("columns" => array_merge($headers, $dash_headers),
                      "data" => array());

            foreach (Team::objects()
                    ->filter(array('team_id__in' => $teams))
                    ->order_by('name') as $team) {
                $authorizedTeams[$team->getId()] = $team;
            }

            $stats = $stats
                ->values('team', 'team__name', 'team__flags')
                ->filter(array(
                    'dept_id__in' => $authorizedDeptIds
                        ?: array($thisstaff->getDeptId()),
                    'team_id__gt' => 0,
                    'team_id__in' => $teams,
                ))
                ->distinct('team_id');
            $times = $times
                ->values('team_id')
                ->filter(array(
                    'dept_id__in' => $authorizedDeptIds
                        ?: array($thisstaff->getDeptId()),
                    'team_id__gt' => 0,
                    'team_id__in' => $teams,
                ))
                ->distinct('team_id');
            break;
       
       case 'staff':
            $headers = array(__('Agent'));
            $header = function($row) {
                return new AgentsName(array(
                    'first' => $row['staff__firstname'],
                    'last' => $row['staff__lastname']
                ));
            };
            $pk = 'staff_id';

            $validStaffIds = array();
            foreach (Staff::objects()
                    ->filter(array(
                        'dept_id__in' => $authorizedDeptIds,
                        'isactive' => 1,
                    )) as $staff) {
                $validStaffIds[] = $staff->getId();
                $authorizedStaff[$staff->getId()] = $staff;
            }

            if (!$validStaffIds) {
                return [ 'columns' => [__('Agente')], 'data' => [] ];
            }

            $Q = Q::any(array('staff_id__in' => $validStaffIds));

            $stats = $stats
                ->values('staff_id', 'staff__firstname', 'staff__lastname')
                ->distinct('staff_id')
                ->order_by('-staff_id')
                ->filter($Q)
                ->filter(array('dept_id__in' => $authorizedDeptIds));

            $times = $times
                ->values('staff_id')
                ->distinct('staff_id')
                ->filter($Q)
                ->filter(array('dept_id__in' => $authorizedDeptIds));
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
        if ($group === 'staff') {
            foreach ($authorizedStaff as $staffId => $agent) {
                $staff[$staffId] = array(
                    'staff_id' => $staffId,
                    'staff__firstname' => $agent->getFirstName(),
                    'staff__lastname' => $agent->getLastName(),
                    'staff__isactive' => $agent->isActive() ? 1 : 0,
                    'Created' => $createdByAgent[$staffId] ?? 0,
                    'Assigned' => 0,
                    'Closed' => 0,
                    'Open' => $openTasksCount[$staffId] ?? 0,
                );
            }

            $staff = self::mergeAuthorizedActivity($staff, $stats, $group);
        }
        elseif ($group === 'team') {
            foreach ($authorizedTeams as $teamId => $team) {
                $staff[$teamId] = array(
                    'team_id' => $teamId,
                    'team' => $teamId,
                    'team__name' => $team->getName(),
                    'team__flags' => $team->flags,
                    'Assigned' => 0,
                    'Closed' => 0,
                    'Open' => $openTasksCount[$teamId] ?? 0,
                );
            }

            $staff = self::mergeAuthorizedActivity($staff, $stats, $group);
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
          $status = '';
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
          if (isset($R['team__flags'])
                  && !($R['team__flags'] & Team::FLAG_ENABLED)) {
              $status = ' - '.__('Disabled');
          }
          if (isset($R['staff__isactive']) && !$R['staff__isactive']) {
              $status = ' - '.__('Locked');
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

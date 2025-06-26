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

        // Nueva pestaña
        if ($thisstaff->getRole()->getId() == 1) {
            $tabs["response_time"] = __("Tiempo de respuesta");
        }

        return $tabs;
    }


    function getTabularData($group='dept') {
        global $thisstaff;

        $event = [
            'created' => 1,
            'assigned' => 4,
            'closed'  => 2,
            'transferred' => 6
        ];



        list($start, $stop) = $this->getDateRange();

        $dash_headers = array_merge(
            $group === 'team' ? array() : array(__('Created')),
            array(__('Assigned'), __('Closed'), __('Abiertos')),
            $group === 'dept' ? array(__('Transferidos')) : array()
        );

        $createdByAgent = [];
        $createdEvents = ThreadEvent::objects()
            ->filter([
                'event_id' =>$event['created'],
                'timestamp__range' => [$start, $stop, true],
                'thread_type' => 'A',
                'annulled' => 0
            ])
            ->values('agent');

        foreach ($createdEvents as $ev) {
            $id = $ev['agent'];
            if (!$id) continue;
            $createdByAgent[$id] = ($createdByAgent[$id] ?? 0) + 1;
        }

        $openTasks = TaskModel::objects()
            ->filter(array(
                'flags' => 1,
                'updated__range' => array($start, $stop, true)
            ))
            ->values($group === 'dept' ? 'dept_id' : ($group === 'team' ? 'team_id' : 'staff_id'))
            ->annotate(array('count' => SqlAggregate::COUNT('id')));

        $openTasksCount = [];
        foreach ($openTasks as $task) {
            $key = $group === 'dept' ? $task['dept_id'] : ($group === 'team' ? $task['team_id'] : $task['staff_id']);
            $openTasksCount[$key] += $task['count'];
        }

        $base_stats = ThreadEvent::objects()
            ->filter(array(
                'annulled' => 0,
                'timestamp__range' => array($start, $stop, true),
                'thread_type' => 'A',
                'event_id__in' => array_merge(
                    $group === 'team' ? array() : array($event['created']),
                    array($event['assigned'], $event['closed']),
                    $group === 'dept' ? array($event['transferred']) : array()
                )
            ));


        $fields = $group === 'staff'
            ? array('staff_id', 'staff__firstname', 'staff__lastname', 'agent', 'agent__firstname', 'agent__lastname')
            : ($group === 'team'
                ? array('team', 'team__name', 'team__flags')
                : array('dept__id', 'dept__name', 'dept__flags'));

        call_user_func_array(array($base_stats, 'values'), $fields);

        $stats = $base_stats->aggregate(array_merge(
            $group === 'team' ? array() : array(
                'Created' => SqlAggregate::COUNT(
                    SqlCase::N()->when(new Q(array('event_id' => $event['created'])), 1)
                )
            ),
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

        switch ($group) {
            case 'response_time':
                $headers = [__('Agente')];
                $pk = 'staff_id';

                // "<pre>=== DEBUG RESPONSE TIME ===\n";

                if ($thisstaff->getRole()->getId() !== 1) {
                    //echo "Usuario sin permiso suficiente\n</pre>";
                    return ['headers' => $headers, 'data' => []];
                }

                $adminDeptIds = [];
                foreach ($thisstaff->getDepts() as $deptId) {
                    $role = $thisstaff->getRole($deptId);
                    if ($role && $role->getId() == 1)
                        $adminDeptIds[] = $deptId;
                }

                //echo "Admin Dept IDs: " . implode(', ', $adminDeptIds) . "\n";

                if (!$adminDeptIds) {
                    //echo "No hay departamentos administrados\n</pre>";
                    return ['headers' => $headers, 'data' => []];
                }

                $validStaffIds = [];
                foreach (Staff::objects()->filter(['dept_id__in' => $adminDeptIds]) as $staff) {
                    $role = $staff->getRole($staff->getDeptId());
                    if ($role && in_array($role->getId(), [1, 2]))
                        $validStaffIds[] = $staff->getId();
                }

               //echo "Valid Staff IDs: " . implode(', ', $validStaffIds) . "\n";

                $createdEvents = ThreadEvent::objects()
                    ->filter([
                        'event_id' => $event['created'],
                        'timestamp__range' => [$start, $stop, true],
                        'thread_type' => 'A',
                        'annulled' => 0
                    ])
                    ->filter(Q::any([
                        'staff_id__in' => $validStaffIds,
                        'uid__in' => $validStaffIds,
                        'uid_type' => 'S'
                    ]))
                    ->values('thread_id', 'staff_id', 'uid', 'uid_type', 'timestamp');


                $assignedEvents = ThreadEvent::objects()
                    ->filter([
                        'event_id' => $event['assigned'],
                        'timestamp__range' => [$start, $stop, true],
                        'thread_type' => 'A',
                        'annulled' => 0,
                        'staff_id__in' => $validStaffIds
                    ])
                    ->values('thread_id', 'staff_id', 'timestamp');

                $closedEvents = ThreadEvent::objects()
                    ->filter([
                        'event_id' => $event['closed'],
                        'timestamp__range' => [$start, $stop, true],
                        'thread_type' => 'A',
                        'annulled' => 0,
                        'staff_id__in' => $validStaffIds
                    ])
                    ->values('thread_id', 'staff_id', 'timestamp');

                $responseData = [];
                foreach ($createdEvents as $ev) {
                    $staffId = $ev['staff_id'] ?: ($ev['uid_type'] === 'S' ? $ev['uid'] : null);
                    if (!$staffId) continue;
                    $responseData[$ev['thread_id']]['created'] = strtotime($ev['timestamp']);
                    $staffMap[$ev['thread_id']] = $staffId;
                }

                foreach ($assignedEvents as $ev)
                    $responseData[$ev['thread_id']]['assigned'] = strtotime($ev['timestamp']);
                foreach ($closedEvents as $ev)
                    $responseData[$ev['thread_id']]['closed'] = strtotime($ev['timestamp']);

                $createdToAssigned = [];
                $assignedToClosed = [];

                foreach ($responseData as $id => $times) {
                    $staffId = $staffMap[$id] ?? null;
                    if (!$staffId) continue;
                    if (isset($times['created'], $times['assigned'])) {
                        $createdToAssigned[$staffId][] = $times['assigned'] - $times['created'];
                        //echo "Tarea $id: creado→asignado = " . ($times['assigned'] - $times['created']) . "s\n";
                    }
                    if (isset($times['assigned'], $times['closed'])) {
                        $assignedToClosed[$staffId][] = $times['closed'] - $times['assigned'];
                        //echo "Tarea $id: asignado→cerrado = " . ($times['closed'] - $times['assigned']) . "s\n";
                    }
                }

                $rows = [];
                foreach ($validStaffIds as $staffId) {
                    $agent = Staff::lookup($staffId);
                    $name = $agent ? new AgentsName([
                        'first' => $agent->getFirstName(),
                        'last' => $agent->getLastName()
                    ]) : 'N/A';

                   $avg1 = isset($createdToAssigned[$staffId])
                        ? round(array_sum($createdToAssigned[$staffId]) / count($createdToAssigned[$staffId]) / 60, 2)
                        : '-';
                    $avg2 = isset($assignedToClosed[$staffId])
                        ? round(array_sum($assignedToClosed[$staffId]) / count($assignedToClosed[$staffId]) / 60, 2)
                        : '-';

                    //echo "Agente {$name}: prom. creado→asignado = $avg1 h, asignado→cerrado = $avg2 h\n";

                    $rows[] = [$name, $avg1 . ' min', $avg2 . ' min'];
                }

                //echo "</pre>";
                //echo "\n=== RAW EVENT DATA ===\n";
                    //foreach ($responseData as $id => $times) {
                        //echo "thread_id: $id | ";
                        //echo isset($times['created']) ? "created: {$times['created']} | " : "created: - | ";
                        //echo isset($times['assigned']) ? "assigned: {$times['assigned']} | " : "assigned: - | ";
                        //echo isset($times['closed']) ? "closed: {$times['closed']} \n" : "closed: -\n";
                   // }

                return [
                    'columns' => [__('Agente'), __('Prom. creado a asignado (min)'), __('Prom. asignado a cerrado (min)')],
                    'data' => $rows
                ];

            case 'dept':
                $headers = array(__('Dependencia'));
                $header = function($row) {
                    return Dept::getLocalNameById($row['dept_id'], $row['dept__name']);
                };
                $pk = 'dept__id';

                if ($thisstaff->getRole()->getId() !== 1) {
                    return [ 'headers' => [__('Dependencia')], 'data' => [] ];
                }

                $adminDeptIds = [];
                foreach ($thisstaff->getDepts() as $deptId) {
                    $role = $thisstaff->getRole($deptId);
                    if ($role && $role->getId() == 1) {
                        $adminDeptIds[] = $deptId;
                    }
                }

                if (!$adminDeptIds) {
                    return [ 'headers' => [__('Dependencia')], 'data' => [] ];
                }

                $stats = $stats
                    ->filter(array('dept_id__in' => $adminDeptIds))
                    ->values('dept__id', 'dept__name', 'dept__flags')
                    ->distinct('dept__id');
                $times = $times
                    ->filter(array('dept_id__in' => $adminDeptIds))
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
            $header = function($row) {
                return new AgentsName(array(
                    'first' => $row['staff__firstname'],
                    'last' => $row['staff__lastname']
                ));
            };
            $pk = 'staff_id';

            if ($thisstaff->getRole()->getId() !== 1) {
                return [ 'headers' => [__('Agente')], 'data' => [] ];
            }

            $adminDeptIds = [];
            foreach ($thisstaff->getDepts() as $deptId) {
                $role = $thisstaff->getRole($deptId);
                if ($role && $role->getId() == 1) {
                    $adminDeptIds[] = $deptId;
                }
            }

            if (!$adminDeptIds) {
                return [ 'headers' => [__('Agente')], 'data' => [] ];
            }

            $validStaffIds = array();
            foreach (Staff::objects()->filter(['dept_id__in' => $adminDeptIds]) as $staff) {
                $role = $staff->getRole($staff->getDeptId());
                if ($role && in_array($role->getId(), [1, 2])) {
                    $validStaffIds[] = $staff->getId();
                }
            }

            if (!$validStaffIds) {
                return [ 'headers' => [__('Agente')], 'data' => [] ];
            }

            $Q = Q::any(array('staff_id__in' => $validStaffIds));

            $stats = $stats
                ->values('staff_id', 'staff__firstname', 'staff__lastname', 'agent', 'agent__firstname', 'agent__lastname')
                ->distinct('staff_id', 'agent')
                ->order_by('-staff_id')
                ->filter($Q);

            $times = $times
                ->values('staff_id')
                ->distinct('staff_id')
                ->filter($Q);
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
                $staff_id = $row['staff_id'] ?: $row['agent'];
                $created = $createdByAgent[$staff_id] ?? 0;
                if ($row['staff_id'] > 0 && !isset($staff[$row['staff_id']])) {
                    $staff[$row['staff_id']] = $row;
                    $staff[$row['staff_id']]['Created'] = $created;
                    $staff[$row['staff_id']]['Assigned'] = isset($row['Assigned']) ? $row['Assigned'] : 0;
                    $staff[$row['staff_id']]['Closed'] = isset($row['Closed']) ? $row['Closed'] : 0;
                    $staff[$row['staff_id']]['Open'] = isset($openTasksCount[$row['staff_id']]) ? $openTasksCount[$row['staff_id']] : 0;

                } elseif ($row['staff_id'] > 0 && isset($staff[$row['staff_id']])) {
                    $staff[$row['staff_id']]['Created'] += $row['Created'];
                    $staff[$row['staff_id']]['Assigned'] += $row['Assigned'];
                    $staff[$row['staff_id']]['Closed'] += $row['Closed'];
                }  elseif ($row['agent'] > 0 && !isset($staff[$row['agent']])) {
                    $row['staff__firstname'] = $row['agent__firstname'];
                    $row['staff__lastname'] = $row['agent__lastname'];
                    $staff[$row['agent']] = $row;
                    $staff[$row['agent']]['Created'] = isset($row['Created']) ? $row['Created'] : 0;
                    $staff[$row['agent']]['Assigned'] = isset($row['Assigned']) ? $row['Assigned'] : 0;
                    $staff[$row['agent']]['Closed'] = isset($row['Closed']) ? $row['Closed'] : 0;
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

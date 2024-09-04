<?php
$tasks = Task::objects();
$date_header = $date_col = false;

// Make sure the cdata materialized view is available
TaskForm::ensureDynamicDataView();

// Remove some variables from query string.
$qsFilter = ['id', 'a'];
$refresh_url = Http::refresh_url($qsFilter);

$sort_options = array(
    'updated' =>            __('Most Recently Updated'),
    'created' =>            __('Most Recently Created'),
    'due' =>                __('Due Date'),
    'number' =>             __('Task Number'),
    'closed' =>             __('Most Recently Closed'),
    'hot' =>                __('Longest Thread'),
    'relevance' =>          __('Relevance'),
);

// Queues columns

$queue_columns = array(
    'number' => array(
        'width' => '10%',
        'heading' => __('Number'),
        'sort_col'  => 'number',
    ),
    'ticket' => array(
        'width' => '10%',
        'heading' => __('Expediente'),
        'sort_col'  => 'ticket__number',
    ),
    'date' => array(
        'width' => '12%',
        'heading' => __('Date Created'),
        'filter_type' => 'date',
    ),
    'last_entry' => array(
        'width' => '10%',
        'heading' => __('Última Actividad'),
        'filter_type' => 'date',
        'disabled' => true,
    ),
    'title' => array(
        'width' => '19%',
        'heading' => __('Title'),
        'sort_col' => 'cdata__title',
    ),
    'dept' => array(
        'width' => '15%',
        'heading' => __('Dependencia'),
        'sort_col'  => 'dept__name',
    ),
    'assignee' => array(
        'width' => '20%',
        'heading' => __('Assigned To'),
    ),
);



// Queue we're viewing
$queue_key = sprintf('::Q:%s', ObjectModel::OBJECT_TYPE_TASK);
$queue_name = $_SESSION[$queue_key] ?: '';

switch ($queue_name) {
    case 'closed':
        $status = 'closed';
        $results_type = __('Casos cerrados asignados a mí');
        $showassigned = true; //closed by.
        // mis casos cerrados
        $tasks->filter(array('staff_id' => $thisstaff->getId()));
        setFilter($status, $tasks);
        $queue_sort_options = array('closed', 'updated', 'created', 'number', 'hot');
        break;
    case 'closed_dept':
        $status = 'closed';
        $results_type = __('Casos cerrados asignados a Mi Dependencia');
        $showassigned = true; //closed by.
        if ($thisstaff->getManagedDepartments()) {
            $tasks->filter(array('dept_id' => $thisstaff->getDept()->getID()));
        } else {
            $tasks->filter(array('id' => 0));
        }
        setFilter($status, $tasks);
        $queue_sort_options = array('closed', 'updated', 'created', 'number', 'hot');
        break;
    case 'overdue':
        $status = 'open';
        $results_type = __('Overdue Tasks');
        $tasks->filter(array('isoverdue' => 1));
        setFilter($status, $tasks);
        $queue_sort_options = array('created', 'updated', 'number', 'hot');
        break;
    default:
    case 'assigned':
        $status = 'open';
        $results_type = __('Casos asignados a mí');
        $tasks->filter(array('staff_id' => $thisstaff->getId()));
        setFilter($status, $tasks);
        $queue_sort_options = array('created', 'updated', 'number', 'hot');
        break;
    case 'assigned_dept':
        $status = 'open';
        $results_type = __('Casos abiertos asignados a Mi Dependencia');
        if ($thisstaff->getManagedDepartments()) {
            $tasks->filter(array('dept_id' => $thisstaff->getDept()->getID()));
        } else {
            $tasks->filter(array('id' => 0));
        }
        setFilter($status, $tasks);
        $queue_sort_options = array('created', 'updated', 'number', 'hot');
        break;
    case 'unassigned_dept':
        $status = 'open';
        $results_type = __('Casos sin asignar en Mi Dependencia');
        if ($thisstaff->getManagedDepartments() || $thisstaff->getLeadedTeams()) {
            $tasks->filter(array(
                'dept_id' => $thisstaff->getDept()->getID(),
                'staff_id' => 0,
                'team_id' => 0
            ));
        } else {
            $tasks->filter(array('id' => 0));
        }
        setFilter($status, $tasks);
        $queue_sort_options = array('created', 'updated', 'number', 'hot');
        break;
    case 'dept':
        $results_type = __('Todos los casos en Mi Dependencia');
        if ($thisstaff->getManagedDepartments()) {
            $tasks->filter(array('dept_id' => $thisstaff->getDept()->getID()));
        } else {
            $tasks->filter(array('id' => 0));
        }
        setFilter($status, $tasks);
        $queue_sort_options = array('created', 'updated', 'number', 'hot');
        break;
    case 'open_me':
        $results_type = __('Creados por mí (abiertos y cerrados)');
        $staffId = $thisstaff->getId();
        $tasks->filter(
            array(
                'thread__events__agent' => $staffId,
                'thread__events__event__name' => 'created',
            ),
        );

        setFilter($status, $tasks);
        $queue_sort_options = array('created', 'updated', 'number', 'hot');
        break;
    case 'created_pairs':
        $results_type = __('Creados por un miembro de mis equipos');
        if ($teams = $thisstaff->getTeams()) {
            $pairs = TeamMember::objects();
            $pairs->distinct('staff_id');
            $pairs->filter(
                array(
                    'team_id__in' => $teams,
                    'staff_id__notequal' => $thisstaff->getId(),
                ),
            );
            $pairs->values('staff_id');
        }

        if (!$teams || !$pairs)
            $tasks->filter(array('id' => 0));
        else
            $tasks->filter(
                array(
                    'thread__events__agent__in' => $pairs,
                    'thread__events__event__name' => 'created',
                ),
            );

        setFilter($status, $tasks);
        $queue_sort_options = array('created', 'updated', 'number', 'hot');
        break;
    case 'involved':
        $results_type = __('Casos en los que he participado y no estoy asignado');
        $staffId = $thisstaff->getId();
        $tasks->distinct('id');
        $tasks->filter(
            array(
                'thread__entries__type__in' => array('N', 'R'),
                'thread__entries__staff' => $staffId,
                'staff_id__notequal' => $staffId,
            ),
        );

        setFilter($status, $tasks);
        $queue_sort_options = array('created', 'updated', 'number', 'hot');
        break;
    case 'transferred':
        $results_type = __('Transferidos a otra dependencia');
        $deptId = $thisstaff->getDept()->getID();
        if ($thisstaff->getManagedDepartments()) {
            $tasks->distinct('id');
            $tasks->filter(
                array(
                    'thread__events__dept' => $deptId,
                    'thread__events__event__name__in' => array('transferred', 'created'),
                    'dept__notequal' => $deptId,
                ),
            );
        } else {
            $tasks->filter(array('id' => 0));
        }

        setFilter($status, $tasks);
        $queue_sort_options = array('created', 'updated', 'number', 'hot');
        break;
    case 'thread_me':
        $results_type = __('Asignados por mí a otro agente');
        $staffId = $thisstaff->getId();
        $tasks->distinct('id');
        $tasks->filter(
            array(
                'thread__events__agent' => $staffId,
                'thread__events__event__name' => 'assigned',
                'thread__events__staff__notequal' => $staffId,
            ),
        );

        setFilter($status, $tasks);
        $queue_sort_options = array('created', 'updated', 'number', 'hot');
        break;
    case 'assigned_mteams':
        $status = 'open';
        $results_type = __('Casos asignados a mis equipos');
        $tasks->filter(array('team_id__in' => $thisstaff->teams->values_flat('team_id')));
        setFilter($status, $tasks);
        $queue_sort_options = array('created', 'updated', 'number', 'hot');
        break;
    case 'unassigned':
        $status = 'open';
        $results_type = __('Casos sin asignar a un agente');
        if ($thisstaff->getManagedDepartments()) {
            $tasks->filter(array(
                'dept_id' => $thisstaff->getDept()->getID(),
                'staff_id' => 0,
                'team_id__gt' => 0
            ));
        } else if ($thisstaff->getLeadedTeams()) {
            $tasks->filter(array(
                'team_id__in' => $thisstaff->teams->values_flat('team_id'),
                'staff_id' => 0
            ));
        } else {
            $tasks->filter(array('id' => 0));
        }
        setFilter($status, $tasks);
        $queue_sort_options = array('created', 'updated', 'number', 'hot');
        break;
    case 'closed_mteams':
        $status = 'closed';
        $results_type = __('Casos cerrados asignados a mis equipos');
        $showassigned = true; //closed by.
        $tasks->filter(array('team_id__in' => $thisstaff->teams->values_flat('team_id')));
        setFilter($status, $tasks);
        $queue_sort_options = array('closed', 'updated', 'created', 'number', 'hot');
        break;
    case 'created_dep':
        $results_type = __('Casos creados por alguien de mi dependencia');
        if ($thisstaff->getManagedDepartments()) {
            $tasks->filter(
                array(
                    'thread__events__agent__dept' => $thisstaff->getDept()->getID(),
                    'thread__events__event__name' => 'created',
                ),
            );
        } else {
            $tasks->filter(array('id' => 0));
        }

        setFilter($status, $tasks);
        $queue_sort_options = array('created', 'updated', 'number', 'hot');
        break;
    case 'cc':
        $results_type = __('Casos con copia a mí');
        $userId = $thisstaff->getUserIdStaff();
        if ($userId) {
            $tasks->filter(
                array(
                    'thread__collaborators__user' => $userId,
                    'thread__events__event__name' => 'created',
                    'thread__events__agent__notequal' => $thisstaff->getId()
                ),
            );
        } else {
            $tasks->filter(array('id' => 0));
        }
        setFilter($status, $tasks);
        $queue_sort_options = array('created', 'updated', 'number', 'hot');
        break;
}

// Apply filters
function setFilter($status, $tasks) {
    if ($_REQUEST['number']) {
        $tasks->filter(array('number__contains' => $_REQUEST['number']));
    }

    if ($_REQUEST['ticket']) {
        $tasks->filter(array('ticket__number__contains' => $_REQUEST['ticket']));
    }

    if ($_REQUEST['start'] || $_REQUEST['end']) {
        if ($_REQUEST['start']) {
            $initDate = $_REQUEST['start'] . ' 05:00:00';
            $tasks->filter(array('created__gt' => $initDate));
        }
        if ($_REQUEST['end']) {
            $endDate = new DateTime($_REQUEST['end']);
            $interval = new DateInterval('PT29H');
            $endDate->add($interval);
            $tasks->filter(array('created__lt' => $endDate));
        }
    } else if ($_REQUEST['date']) {
        $initDate = $_REQUEST['date'] . ' 05:00:00';
        $interval = new DateInterval('P1D');
        $endDate = new DateTime($initDate);
        $endDate->add($interval);
        $endFormat = $endDate->format('Y-m-d H:i:s');
        $column = $status === 'closed' ? 'closed' : 'created';
        $tasks->filter(array(($column . '__range') => array("'" . $initDate . "'", "'" . $endFormat . "'", true)));
    }

    if ($_REQUEST['title']) {
        $tasks->filter(array('cdata__title__contains' => $_REQUEST['title']));
    }

    if ($_REQUEST['dept']) {
        $tasks->filter(array('dept__name__contains' => $_REQUEST['dept']));
    }

    if ($_REQUEST['assignee']) {
        $tasks = $tasks->filter(Q::any(array(
            'staff__firstname__contains' => $_REQUEST['assignee'],
            'staff__lastname__contains' => $_REQUEST['assignee'],
            'team__name__contains' => $_REQUEST['assignee'],
        )));
    }
}

$filters = array();
if ($status) {
    $SQ = new Q(array('flags__hasbit' => TaskModel::ISOPEN));
    if (!strcasecmp($status, 'closed'))
        $SQ->negate();

    $filters[] = $SQ;
}

if ($filters)
    $tasks->filter($filters);

// Impose visibility constraints
// ------------------------------------------------------------
// -- Open and assigned to me
$visibility = Q::any(
    new Q(array('flags__hasbit' => TaskModel::ISOPEN, 'staff_id' => $thisstaff->getId()))
);
// -- Task for tickets assigned to me
$visibility->add(
    new Q(array(
        'ticket__staff_id' => $thisstaff->getId(),
        'ticket__status__state' => 'open'
    ))
);
// -- Routed to a department of mine
if (!$thisstaff->showAssignedOnly() && ($depts = $thisstaff->getDepts()))
    $visibility->add(new Q(array('dept_id__in' => $depts)));
// -- Open and assigned to a team of mine
if (($teams = $thisstaff->getTeams()) && count(array_filter($teams)))
    $visibility->add(new Q(array(
        'team_id__in' => array_filter($teams),
        'flags__hasbit' => TaskModel::ISOPEN
    )));
$tasks->filter(new Q($visibility));

// Add in annotations
$tasks->annotate(array(
    'collab_count' => SqlAggregate::COUNT('thread__collaborators', true),
    'attachment_count' => SqlAggregate::COUNT(
        SqlCase::N()
            ->when(new SqlField('thread__entries__attachments__inline'), null)
            ->otherwise(new SqlField('thread__entries__attachments')),
        true
    ),
    'thread_count' => SqlAggregate::COUNT(
        SqlCase::N()
            ->when(
                new Q(array('thread__entries__flags__hasbit' => ThreadEntry::FLAG_HIDDEN)),
                null
            )
            ->otherwise(new SqlField('thread__entries__id')),
        true
    ),
    'last_entry' => SqlAggregate::MAX('thread__entries__created')
));

$tasks->values(
    'id',
    'number',
    'created',
    'staff_id',
    'team_id',
    'staff__firstname',
    'staff__lastname',
    'team__name',
    'dept__name',
    'cdata__title',
    'flags',
    'ticket__number',
    'ticket__ticket_id'
);
// Apply requested quick filter

$queue_sort_key = sprintf(':Q%s:%s:sort', ObjectModel::OBJECT_TYPE_TASK, $queue_name);

if (isset($_GET['sort'])) {
    $_SESSION[$queue_sort_key] = array($_GET['sort'], $_GET['dir']);
} elseif (!isset($_SESSION[$queue_sort_key])) {
    $_SESSION[$queue_sort_key] = array($queue_sort_options[0], 0);
}

list($sort_cols, $sort_dir) = $_SESSION[$queue_sort_key];
$orm_dir = $sort_dir ? QuerySet::ASC : QuerySet::DESC;
$orm_dir_r = $sort_dir ? QuerySet::DESC : QuerySet::ASC;

if ($status == 'closed') {
    $queue_columns['date']['heading'] = __('Date Closed');
    $queue_columns['date']['sort'] = 'closed';
    $queue_columns['date']['sort_col'] = $date_col = 'closed';
    $tasks->values('closed');
}

if ($sort_cols == 'date') {
    $sort_cols = $status == 'closed' ? 'closed' : 'created';
}

switch ($sort_cols) {
    case 'number':
        $queue_columns['number']['sort_dir'] = $sort_dir;
        $tasks->order_by($sort_dir ? 'id' : '-id');
        break;
    case 'due':
        $queue_columns['date']['heading'] = __('Due Date');
        $queue_columns['date']['sort'] = 'due';
        $queue_columns['date']['sort_col'] = $date_col = 'duedate';
        $tasks->values('duedate');
        $tasks->order_by(SqlFunction::COALESCE(new SqlField('duedate'), 'zzz'), $orm_dir_r);
        break;
    case 'closed':
        $queue_columns['date']['heading'] = __('Date Closed');
        $queue_columns['date']['sort'] = $sort_cols;
        $queue_columns['date']['sort_col'] = $date_col = 'closed';
        $queue_columns['date']['sort_dir'] = $sort_dir;
        $tasks->values('closed');
        $tasks->order_by($sort_dir ? 'closed' : '-closed');
        break;
    case 'updated':
        $queue_columns['date']['heading'] = __('Last Updated');
        $queue_columns['date']['sort'] = $sort_cols;
        $queue_columns['date']['sort_col'] = $date_col = 'updated';
        $tasks->values('updated');
        $tasks->order_by($sort_dir ? 'updated' : '-updated');
        break;
    case 'hot':
        $tasks->order_by('-thread_count');
        $tasks->annotate(array(
            'thread_count' => SqlAggregate::COUNT('thread__entries'),
        ));
        break;
    case 'assignee':
        $tasks->order_by('staff__firstname', $orm_dir);
        $tasks->order_by('staff__lastname', $orm_dir);
        $tasks->order_by('team__name', $orm_dir);
        $queue_columns['assignee']['sort_dir'] = $sort_dir;
        break;
    default:
        if ($sort_cols && isset($queue_columns[$sort_cols])) {
            $queue_columns[$sort_cols]['sort_dir'] = $sort_dir;
            if (isset($queue_columns[$sort_cols]['sort_col']))
                $sort_cols = $queue_columns[$sort_cols]['sort_col'];
            $tasks->order_by($sort_cols, $orm_dir);
            break;
        }
    case 'created':
        $queue_columns['date']['heading'] = __('Date Created');
        $queue_columns['date']['sort'] = 'created';
        $queue_columns['date']['sort_col'] = $date_col = 'created';
        $tasks->order_by($sort_dir ? 'created' : '-created');
        break;
    case 'last_entry':
        $queue_columns['last_entry']['sort_dir'] = $sort_dir;
        $tasks->order_by($sort_dir ? 'last_entry' : '-last_entry');
        break;
}

if (in_array($sort_cols, array('created', 'due', 'updated')))
    $queue_columns['date']['sort_dir'] = $sort_dir;

// Apply requested pagination
$page = ($_GET['p'] && is_numeric($_GET['p'])) ? $_GET['p'] : 1;
$count = $tasks->count();
$pageNav = new Pagenate($count, $page, PAGE_LIMIT);
$pageNav->setURL('tasks.php', array_merge($args ?: array(), $_REQUEST));
$tasks = $pageNav->paginate($tasks);

// Save the query to the session for exporting
$_SESSION[':Q:tasks'] = $tasks;

// Mass actions
$actions = array();

if ($thisstaff->hasPerm(Task::PERM_ASSIGN, false)) {
    $actions += array(
        'assign' => array(
            'icon' => 'icon-user',
            'action' => __('Assign Tasks')
        )
    );
}

if ($thisstaff->hasPerm(Task::PERM_TRANSFER, false)) {
    $actions += array(
        'transfer' => array(
            'icon' => 'icon-share',
            'action' => __('Transfer Tasks')
        )
    );
}

if ($thisstaff->hasPerm(Task::PERM_DELETE, false)) {
    $actions += array(
        'delete' => array(
            'icon' => 'icon-trash',
            'action' => __('Delete Tasks')
        )
    );
}


?>
<div class="clear"></div>
<div style="margin-bottom:20px; padding-top:5px;">
    <div class="sticky bar opaque">
        <div class="content">
            <div class="pull-left flush-left">
                <h2><a href="<?php echo $refresh_url; ?>" title="<?php echo __('Refresh'); ?>"><i class="icon-refresh"></i> <?php echo
                                                                                                                            $results_type . $showing; ?></a></h2>
            </div>
            <div class="pull-right flush-right">
                <?php
                if ($count)
                    echo Task::getAgentActions($thisstaff, array('status' => $status));
                ?>
            </div>
        </div>
    </div>
    <div class="clear"></div>
    <form action="tasks.php" method="get">
        <input type="hidden" name="status" value="<?php echo $_REQUEST['status']; ?>" />
        <div id="basic_search" style="min-height:25px; margin: auto">
            <label>
                <?php echo __('Desde'); ?>:
                <input type="date" class="input-medium search-query" name="start"
                    value="<?php echo $_REQUEST['start']; ?>" />
            </label>
            <label>
                <?php echo __('Hasta'); ?>:
                <input type="date" class="input-medium search-query" name="end"
                    value="<?php echo $_REQUEST['end']; ?>" />
            </label>
            <button class="green button action-button muted" type="submit">
                <?php echo __( 'Buscar');?>
            </button>
        </div>
    </form>
    <form action="tasks.php" method="POST" name='tasks' id="tasks">
        <?php csrf_token(); ?>
        <input type="hidden" name="a" value="mass_process">
        <input type="hidden" name="do" id="action" value="">
        <input type="hidden" name="status" value="<?php echo
                                                    Format::htmlchars($_REQUEST['status'], true); ?>">
        <table class="list" border="0" cellspacing="1" cellpadding="2" width="940">
            <thead>
                <tr>
                    <?php if ($thisstaff->canManageTickets()) { ?>
                        <th width="4%">&nbsp;<form action="tasks.php" method="get"></form></th>
                    <?php } ?>

                    <?php
                    // Query string
                    unset($args['sort'], $args['dir'], $args['_pjax']);
                    $qstr = Http::build_query($args);
                    // Show headers
                    foreach ($queue_columns as $k => $column) {
                        echo sprintf(
                            '<th width="%s"><a href="?sort=%s&dir=%s&%s" class="%s">%s</a>
                                <form action="tasks.php" method="get">
                                    <input type="hidden" name="status" value="%s" />
                                    <div class="attached input">
                                        <input type="%s" class="column-search" name="%s" value="%s" %s>
                                        <button type="submit" class="attached button" %s><i class="icon-search"></i></button>
                                    </div>
                                </form>
                            </th>',
                            $column['width'],
                            $column['sort'] ?: $k,
                            $column['sort_dir'] ? 0 : 1,
                            $qstr,
                            isset($column['sort_dir'])
                                ? ($column['sort_dir'] ? 'asc' : 'desc') : '',
                            $column['heading'],
                            $_REQUEST['status'],
                            $column['filter_type'] ?: 'text',
                            $k,
                            Format::htmlchars($_REQUEST[$k], true),
                            $column['disabled'] ? 'disabled': '',
                            $column['disabled'] ? 'disabled': '',
                        );
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php
                // Setup Subject field for display
                $total = 0;
                $title_field = TaskForm::getInstance()->getField('title');
                $ids = ($errors && $_POST['tids'] && is_array($_POST['tids'])) ? $_POST['tids'] : null;
                foreach ($tasks as $T) {
                    $T['isopen'] = ($T['flags'] & TaskModel::ISOPEN != 0); //XXX:
                    $total += 1;
                    $tag = $T['staff_id'] ? 'assigned' : 'openticket';
                    $flag = null;
                    if ($T['lock__staff_id'] && $T['lock__staff_id'] != $thisstaff->getId())
                        $flag = 'locked';
                    elseif ($T['isoverdue'])
                        $flag = 'overdue';

                    $assignee = '';
                    $dept = Dept::getLocalById($T['dept_id'], 'name', $T['dept__name']);
                    $assinee = '';
                    if ($T['staff_id'] && $T['team_id']) {
                        $staff =  new AgentsName($T['staff__firstname'] . ' ' . $T['staff__lastname']);
                        $team = Team::getLocalById($T['team_id'], 'name', $T['team__name']);
                        $assignee = sprintf(
                            '<span class="Icon staffAssigned">%s</span><br><span class="Icon teamAssigned">%s</span>',
                            Format::truncate((string) $staff, 30),
                            Format::truncate((string) $team, 30)
                        );
                    } else if ($T['staff_id']) {
                        $staff =  new AgentsName($T['staff__firstname'] . ' ' . $T['staff__lastname']);
                        $assignee = sprintf(
                            '<span class="Icon staffAssigned">%s</span>',
                            Format::truncate((string) $staff, 40)
                        );
                    } elseif ($T['team_id']) {
                        $assignee = sprintf(
                            '<span class="Icon teamAssigned">%s</span>',
                            Format::truncate(Team::getLocalById($T['team_id'], 'name', $T['team__name']), 40)
                        );
                    }

                    $threadcount = $T['thread_count'];
                    $number = $T['number'];
                    if ($T['isopen'])
                        $number = sprintf('<b>%s</b>', $number);

                    $title = Format::truncate($title_field->display($title_field->to_php($T['cdata__title'])), 40);
                ?>
                    <tr id="<?php echo $T['id']; ?>">
                        <?php
                        if ($thisstaff->canManageTickets()) {
                            $sel = false;
                            if ($ids && in_array($T['id'], $ids))
                                $sel = true;
                        ?>
                            <td align="center" class="nohover">
                                <input class="ckb" type="checkbox" name="tids[]" value="<?php echo $T['id']; ?>" <?php echo $sel ? 'checked="checked"' : ''; ?>>
                            </td>
                        <?php } ?>
                        <td nowrap>
                            <a class="preview" href="tasks.php?id=<?php echo $T['id']; ?>" data-preview="#tasks/<?php echo $T['id']; ?>/preview"><?php echo $number; ?></a>
                        </td>
                        <td nowrap>
                            <a class="preview" href="tickets.php?id=<?php echo $T['ticket__ticket_id']; ?>" data-preview="#tickets/<?php echo $T['ticket__ticket_id']; ?>/preview"><?php echo $T['ticket__number']; ?></a>
                        </td>
                        <td nowrap><?php echo
                                    Format::datetime($T[$date_col ?: 'created']); ?></td>
                        <td nowrap><?php echo
                                    Format::datetime($T['last_entry']); ?></td>
                        <td><a <?php if ($flag) { ?> class="Icon <?php echo $flag; ?>Ticket" title="<?php echo ucfirst($flag); ?> Ticket" <?php } ?> href="tasks.php?id=<?php echo $T['id']; ?>"><?php
                                                                                                                                                                                                    echo $title; ?></a>
                            <?php
                            if ($threadcount > 1)
                                echo "<small>($threadcount)</small>&nbsp;" . '<i
                                class="icon-fixed-width icon-comments-alt"></i>&nbsp;';
                            if ($T['collab_count'])
                                echo '<i class="icon-fixed-width icon-group faded"></i>&nbsp;';
                            if ($T['attachment_count'])
                                echo '<i class="icon-fixed-width icon-paperclip"></i>&nbsp;';
                            ?>
                        </td>
                        <td><?php echo $dept; ?></td>
                        <td><?php echo $assignee; ?></td>
                    </tr>
                <?php
                } //end of foreach
                if (!$total)
                    $ferror = __('There are no tasks matching your criteria.');
                ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="8">
                        <?php if ($total && $thisstaff->canManageTickets()) { ?>
                            <?php echo __('Select'); ?>:&nbsp;
                            <a id="selectAll" href="#ckb"><?php echo __('All'); ?></a>&nbsp;&nbsp;
                            <a id="selectNone" href="#ckb"><?php echo __('None'); ?></a>&nbsp;&nbsp;
                            <a id="selectToggle" href="#ckb"><?php echo __('Toggle'); ?></a>&nbsp;&nbsp;
                        <?php } else {
                            echo '<i>';
                            echo $ferror ? Format::htmlchars($ferror) : __('Query returned 0 results.');
                            echo '</i>';
                        } ?>
                    </td>
                </tr>
            </tfoot>
        </table>
        <?php
        if ($total > 0) { //if we actually had any tasks returned.
            echo '<div>&nbsp;' . __('Page') . ':' . $pageNav->getPageLinks() . '&nbsp;';
            echo sprintf(
                '<a class="export-csv no-pjax" href="?%s">%s</a>',
                Http::build_query(array(
                    'a' => 'export', 'h' => $hash,
                    'status' => $_REQUEST['status']
                )),
                __('Export')
            );
            echo '&nbsp;<i class="help-tip icon-question-sign" href="#export"></i></div>';
        } ?>
    </form>
</div>

<div style="display:none;" class="dialog" id="confirm-action">
    <h3><?php echo __('Please Confirm'); ?></h3>
    <a class="close" href=""><i class="icon-remove-circle"></i></a>
    <hr />
    <p class="confirm-action" style="display:none;" id="mark_overdue-confirm">
        <?php echo __('Are you sure want to flag the selected tasks as <font color="red"><b>overdue</b></font>?'); ?>
    </p>
    <div><?php echo __('Please confirm to continue.'); ?></div>
    <hr style="margin-top:1em" />
    <p class="full-width">
        <span class="buttons pull-left">
            <input type="button" value="<?php echo __('No, Cancel'); ?>" class="close">
        </span>
        <span class="buttons pull-right">
            <input type="button" value="<?php echo __('Yes, Do it!'); ?>" class="confirm">
        </span>
    </p>
    <div class="clear"></div>
</div>
<script type="text/javascript">
    $(function() {

        $(document).off('.new-task');
        $(document).on('click.new-task', 'a.new-task', function(e) {
            e.preventDefault();
            var url = 'ajax.php/' +
                $(this).attr('href').substr(1) +
                '?_uid=' + new Date().getTime();
            var $options = $(this).data('dialogConfig');
            $.dialog(url, [201], function(xhr) {
                var tid = parseInt(xhr.responseText);
                if (tid) {
                    window.location.href = 'tasks.php?id=' + tid;
                } else {
                    $.pjax.reload('#pjax-container');
                }
            }, $options);

            return false;
        });

        $('[data-toggle=tooltip]').tooltip();
    });
</script>
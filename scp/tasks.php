<?php

/*************************************************************************
    tasks.php

    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
 **********************************************************************/

require('staff.inc.php');
require_once(INCLUDE_DIR . 'class.task.php');
require_once(INCLUDE_DIR . 'class.export.php');

$page = '';
$task = null; //clean start.
if ($_REQUEST['id']) {
    if (!($task = Task::lookup($_REQUEST['id'])))
        $errors['err'] = sprintf(__('%s: Unknown or invalid ID.'), __('task'));
    elseif (!$task->checkStaffPerm($thisstaff)) {
        $errors['err'] = __('Access denied. Contact admin if you believe this is in error');
        $task = null;
    }
}

// Configure form for file uploads
$note_attachments_form = new SimpleForm(array(
    'attachments' => new FileUploadField(array(
        'id' => 'attach',
        'name' => 'attach:note',
        'configuration' => array('extensions' => '')
    ))
));

$reply_attachments_form = new SimpleForm(array(
    'attachments' => new FileUploadField(array(
        'id' => 'attach',
        'name' => 'attach:reply',
        'configuration' => array('extensions' => '')
    ))
));

//At this stage we know the access status. we can process the post.
if ($_POST && !$errors) :
    if ($task) {
        //More coffee please.
        $errors = array();
        $role = $thisstaff->getRole($task->getDept());
        switch (strtolower($_POST['a'])):
            case 'postnote': /* Post Internal Note */
                $vars = $_POST;
                $vars['files'] = $note_attachments_form->getField('attachments')->getFiles();

                $wasOpen = ($task->isOpen());
                if (($note = $task->postNote($vars, $errors, $thisstaff))) {

                    $msg = __('Internal note posted successfully');
                    // Clear attachment list
                    $note_attachments_form->setSource(array());
                    $note_attachments_form->getField('attachments')->reset();

                    if ($wasOpen && $task->isClosed())
                        $task = null; //Going back to main listing.
                    else
                        // Task is still open -- clear draft for the note
                        Draft::deleteForNamespace(
                            'task.note.' . $task->getId(),
                            $thisstaff->getId()
                        );
                } else {
                    if (!$errors['err'])
                        $errors['err'] = __('Unable to post internal note - missing or invalid data.');

                    $errors['postnote'] = sprintf(
                        '%s %s',
                        __('Unable to post the note.'),
                        __('Correct any errors below and try again.')
                    );
                }
                break;
            case 'postreply': /* Post an update */
                $vars = $_POST;
                $vars['files'] = $reply_attachments_form->getField('attachments')->getFiles();

                $wasOpen = ($task->isOpen());
                if (($response = $task->postReply($vars, $errors))) {

                    $msg = __('Reply posted successfully');
                    // Clear attachment list
                    $reply_attachments_form->setSource(array());
                    $reply_attachments_form->getField('attachments')->reset();

                    if ($wasOpen && $task->isClosed())
                        $task = null; //Going back to main listing.
                    else
                        // Task is still open -- clear draft for the note
                        Draft::deleteForNamespace(
                            'task.response.' . $task->getId(),
                            $thisstaff->getId()
                        );
                } else {
                    if (!$errors['err'])
                        $errors['err'] = __('Unable to post the reply - missing or invalid data.');

                    $errors['postreply'] = sprintf(
                        '%s %s',
                        __('Unable to post the reply.'),
                        __('Correct any errors below and try again.')
                    );
                }
                break;
            default:
                $errors['err'] = __('Unknown action');
        endswitch;

        switch (strtolower($_POST['do'])):
            case 'addcc':
                $errors = array();
                if (!$role->hasPerm(Ticket::PERM_EDIT)) {
                    $errors['err'] = __('Permission Denied. You are not allowed to add collaborators');
                } elseif (!$_POST['user_id'] || !($user = User::lookup($_POST['user_id']))) {
                    $errors['err'] = __('Unknown user selected');
                } elseif ($c2 = $task->addCollaborator($user, array(), $errors)) {
                    $c2->setFlag(Collaborator::FLAG_CC, true);
                    $c2->save();
                    $msg = sprintf(
                        __('Collaborator %s added'),
                        Format::htmlchars($user->getName())
                    );
                } else
                    $errors['err'] = sprintf('%s %s', __('Unable to add collaborator.'), __('Please try again!'));
                break;
        endswitch;
    }
    if (!$errors)
        $thisstaff->resetStats(); //We'll need to reflect any changes just made!
endif;

/*... Quick stats ...*/
$stats = $thisstaff->getTasksStats();

// Clear advanced search upon request
if (isset($_GET['clear_filter']))
    unset($_SESSION['advsearch:tasks']);


if (!$task) {
    $queue_key = sprintf('::Q:%s', ObjectModel::OBJECT_TYPE_TASK);
    $queue_name = strtolower($_GET['status'] ?: $_GET['a']);
    if (!$queue_name && isset($_SESSION[$queue_key]))
        $queue_name = $_SESSION[$queue_key];

    // Stash current queue view
    $_SESSION[$queue_key] = $queue_name;

    // Set queue as status
    if (
        @!isset($_REQUEST['advanced'])
        && @$_REQUEST['a'] != 'search'
        && !isset($_GET['status'])
        && $queue_name
    )
        $_GET['status'] = $_REQUEST['status'] = $queue_name;
}

//Navigation
$nav->setTabActive('tasks');
$open_name = _P(
    'queue-name',
    /* This is the name of the open tasks queue */
    'assigned'
); // cambio de nombre a la cola

$nav->addSubMenu(
    array(
        'desc' => __('Asignados a mí'),
        'title' => __('Casos asignados'),
        'href' => 'tasks.php?status=assigned',
        'iconclass' => 'assignedTickets'
    ),
    ($_REQUEST['status'] == 'assigned')
);

$nav->addSubMenu(
    array(
        'desc' => __('Creados por mí'),
        'title' => __('Casos abiertos y cerrados'),
        'href' => 'tasks.php?status=open_me',
        'iconclass' => 'assignedTickets'
    ),
    ($_REQUEST['status'] == 'open_me')
);

$nav->addSubMenu(
    array(
        'desc' => __('Participaciones'),
        'title' => __('Casos en los que he participado y no estoy asignado'),
        'href' => 'tasks.php?status=involved',
        'iconclass' => 'assignedTickets'
    ),
    ($_REQUEST['status'] == 'involved')
);

$nav->addSubMenu(
    array(
        'desc' => __('Gestionados por mí'),
        'title' => __('Gestionados por mí (Abiertos y Cerrados)'),
        'href' => 'tasks.php?status=thread_me',
        'iconclass' => 'closedTickets'
    ),
    ($_REQUEST['status'] == 'thread_me')
);

$nav->addSubMenu(
    array(
        'desc' => __('Mis casos cerrados'),
        'title' => __('Casos cerrados'),
        'href' => 'tasks.php?status=closed',
        'iconclass' => 'closedTickets'
    ),
    ($_REQUEST['status'] == 'closed')
);

$nav->addSubMenu(
    array(
        'desc' => __('Transferidos por mí'),
        'title' => __('Transferidos por mí'),
        'href' => 'tasks.php?status=transferred_me',
        'iconclass' => 'departments'
    ),
    ($_REQUEST['status'] == 'transferred_me')
);

$nav->addSubMenu(
    array(
        'desc' => __('Con Copia'),
        'title' => __('Casos con copia a mí'),
        'href' => 'tasks.php?status=cc',
        'iconclass' => 'closedTickets'
    ),
    ($_REQUEST['status'] == 'cc')
);

// Queue for team members
if ($thisstaff->getTeams()) {
    $nav->addSubMenu(
        array(
            'desc' => __('Mis equipos'),
            'title' => __('Casos asignados a mis equipos'),
            'href' => 'tasks.php?status=assigned_mteams',
            'class' => 'team_queue',
            'iconclass' => 'teams'
        ),
        ($_REQUEST['status'] == 'assigned_mteams')
    );

    $nav->addSubMenu(
        array(
            'desc' => __('Cerrados en Mis equipos'),
            'title' => __('Casos cerrados asignados a mis equipos'),
            'href' => 'tasks.php?status=closed_mteams',
            'class' => 'team_queue',
            'iconclass' => 'closedTickets'
        ),
        ($_REQUEST['status'] == 'closed_mteams')
    );

    $nav->addSubMenu(
        array(
            'desc' => __('Creados por Mis equipos'),
            'title' => __('Creados otro miembro de mis equipos'),
            'href' => 'tasks.php?status=created_pairs',
            'class' => 'team_queue',
            'iconclass' => 'assignedTickets'
        ),
        ($_REQUEST['status'] == 'created_pairs')
    );
}

if (count($thisstaff->getAdminDepartments())) {
    $nav->addSubMenu(
        array(
            'desc' => __('Todo dependencia'),
            'title' => __('Todos los casos en Mi Dependencia'),
            'href' => 'tasks.php?status=dept',
            'class' => 'admin_queue',
            'iconclass' => 'assignedTickets'
        ),
        ($_REQUEST['status'] == 'dept')
    );

    $nav->addSubMenu(
        array(
            'desc' => __('Mi dependencia'),
            'title' => __('Casos asignados a Mi Dependencia'),
            'href' => 'tasks.php?status=assigned_dept',
            'class' => 'admin_queue',
            'iconclass' => 'departments'
        ),
        ($_REQUEST['status'] == 'assigned_dept')
    );

    $nav->addSubMenu(
        array(
            'desc' => __('Cerrados dependencia'),
            'title' => __('Casos cerrados asignados a Mi dependencia'),
            'href' => 'tasks.php?status=closed_dept',
            'class' => 'admin_queue',
            'iconclass' => 'closedTickets'
        ),
        ($_REQUEST['status'] == 'closed_dept')
    );

    $nav->addSubMenu(
        array(
            'desc' => __('Creados dependencia'),
            'title' => __('Casos creados por alguien de mi dependencia'),
            'href' => 'tasks.php?status=created_dep',
            'class' => 'admin_queue',
            'iconclass' => 'assignedTickets'
        ),
        ($_REQUEST['status'] == 'created_dep')
    );
}

if ($thisstaff->getManagedDepartments()) {
    $nav->addSubMenu(
        array(
            'desc' => __('Solicitados dependencia'),
            'title' => __('Casos creados por mi dependencia y asignados a otra dependencia'),
            'href' => 'tasks.php?status=requested_dep',
            'class' => 'admin_queue',
            'iconclass' => 'assignedTickets'
        ),
        ($_REQUEST['status'] == 'requested_dep')
    );

    $nav->addSubMenu(
        array(
            'desc' => __('Transferidos dependencia'),
            'title' => __('Transferidos por mi dependencia'),
            'href' => 'tasks.php?status=transferred',
            'class' => 'admin_queue',
            'iconclass' => 'departments'
        ),
        ($_REQUEST['status'] == 'transferred')
    );
}

if (count($thisstaff->getAdminDepartments()) || $thisstaff->getLeadedTeams()) {
    $nav->addSubMenu(
        array(
            'desc' => __('Sin Revisar'),
            'title' => __('Casos sin asignar en mi dependencia'),
            'href' => 'tasks.php?status=unassigned_dept',
            'class' => 'admin_queue',
            'iconclass' => 'overdueTickets'
        ),
        ($_REQUEST['status'] == 'unassigned_dept')
    );

    $nav->addSubMenu(
        array(
            'desc' => __('Sin asignar'),
            'title' => __('Casos sin asignar a un agente'),
            'href' => 'tasks.php?status=unassigned',
            'class' => 'admin_queue',
            'iconclass' => 'overdueTickets'
        ),
        ($_REQUEST['status'] == 'unassigned')
    );
}

if ($stats['overdue']) {
    $nav->addSubMenu(
        array(
            'desc' => __('Overdue') . ' (' . number_format($stats['overdue']) . ')',
            'title' => __('Stale Tasks'),
            'href' => 'tasks.php?status=overdue',
            'iconclass' => 'overdueTickets'
        ),
        ($_REQUEST['status'] == 'overdue')
    );

    if (!$sysnotice && $stats['overdue'] > 10)
        $sysnotice = sprintf(__('%d overdue tasks!'), $stats['overdue']);
}


if ($thisstaff->hasPerm(TaskModel::PERM_CREATE, false)) {
    $nav->addSubMenu(
        array(
            'desc' => __('New Task'),
            'title' => __('Open a New Task'),
            'href' => '#tasks/add',
            'iconclass' => 'newTicket new-task',
            'id' => 'new-task',
            'attr' => array(
                'data-dialog-config' => '{"size":"large"}'
            )
        ),
        ($_REQUEST['a'] == 'open')
    );
}


$ost->addExtraHeader('<script type="text/javascript" src="js/ticket.js"></script>');
$ost->addExtraHeader('<script type="text/javascript" src="js/thread.js"></script>');
$ost->addExtraHeader(
    '<meta name="tip-namespace" content="tasks.queue" />',
    "$('#content').data('tipNamespace', 'tasks.queue');"
);

if ($task) {
    $ost->setPageTitle(sprintf(__('Task #%s'), $task->getNumber()));
    $nav->setActiveSubMenu(-1);
    $inc = 'task-view.inc.php';
    if (
        $_REQUEST['a'] == 'edit'
        && $task->checkStaffPerm($thisstaff, TaskModel::PERM_EDIT)
    ) {
        $inc = 'task-edit.inc.php';
        if (!$forms) $forms = DynamicFormEntry::forObject($task->getId(), 'A');
        // Auto add new fields to the entries
        foreach ($forms as $f) $f->addMissingFields();
    } elseif ($_REQUEST['a'] == 'print' && !$task->pdfExport($_REQUEST['psize']))
        $errors['err'] = __('Unable to print to PDF.')
            . ' ' . __('Internal error occurred');
} else {
    $inc = 'tasks.inc.php';
    if (
        $_REQUEST['a'] == 'open' &&
        $thisstaff->hasPerm(Task::PERM_CREATE, false)
    )
        $inc = 'task-open.inc.php';
    elseif ($_REQUEST['a'] == 'export') {
        $timezone = new DateTimeZone('America/Bogota');
        $date = new DateTime('now', $timezone);
        $ts = join('-', array_filter(array($_REQUEST['status'], $date->format('YmdHis'))));
        if (!($query = $_SESSION[':Q:tasks']))
            $errors['err'] = __('Query token not found');
        elseif (!Export::saveTasks($query, "tasks-$ts.csv", 'csv'))
            $errors['err'] = __('Unable to dump query results.')
                . ' ' . __('Internal error occurred');
    }

    //Clear active submenu on search with no status
    if ($_REQUEST['a'] == 'search' && !$_REQUEST['status'])
        $nav->setActiveSubMenu(-1);

    //set refresh rate if the user has it configured
    if (!$_POST && !$_REQUEST['a'] && ($min = $thisstaff->getRefreshRate())) {
        $js = "clearTimeout(window.task_refresh);
               window.task_refresh = setTimeout($.refreshTaskView,"
            . ($min * 60000) . ");";
        $ost->addExtraHeader(
            '<script type="text/javascript">' . $js . '</script>',
            $js
        );
    }
}

require_once(STAFFINC_DIR . 'header.inc.php');
require_once(STAFFINC_DIR . $inc);
require_once(STAFFINC_DIR . 'footer.inc.php');

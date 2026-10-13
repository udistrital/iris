<?php
/*************************************************************************
    tickets.php

    Handles all tickets related actions.

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

require('staff.inc.php');
require_once(INCLUDE_DIR.'class.ticket.php');
require_once(INCLUDE_DIR.'class.dept.php');
require_once(INCLUDE_DIR.'class.filter.php');
require_once(INCLUDE_DIR.'class.canned.php');
require_once(INCLUDE_DIR.'class.json.php');
require_once(INCLUDE_DIR.'class.dynamic_forms.php');
require_once(INCLUDE_DIR.'class.export.php');       // For paper sizes


function iris_ticket_creator_department_expression() {
    return SqlAggregate::MAX(
        SqlCase::N()
            ->when(
                new Q(array(
                    'creator_thread__events__event__name' => 'created',
                    'creator_thread__events__uid_type' => 'S',
                )),
                new SqlField('creator_thread__events__agent__dept__name')
            )
            ->otherwise(null)
    );
}

function iris_apply_ticket_queue_filters($tickets, $queue_name, $thisstaff) {
    $staffId = $thisstaff->getId();
    $deptId = $thisstaff->getDept()->getId();
    $adminDeptIds = $thisstaff->getAdminDepartments()->values_flat('id');
    $status = null;

    switch ($queue_name) {
        case 'assigned':
            $status = 'open';
            $tickets->filter(array('staff_id' => $staffId));
            break;
        case 'open_me':
            $tickets->filter(array(
                'thread__events__agent' => $staffId,
                'thread__events__event__name' => 'created',
            ));
            break;
        case 'involved':
            $status = 'open';
            $tickets->filter(array(
                'thread__entries__type__in' => array('N', 'R'),
                'thread__entries__staff__staff_id' => $staffId,
                'staff_id__notequal' => $staffId,
            ));
            break;
        case 'thread_me':
            $status = 'open';
            $tickets->filter(array(
                'thread__events__agent' => $staffId,
                'thread__events__event__name' => 'assigned',
                'thread__events__staff__staff_id__notequal' => $staffId,
            ));
            break;
        case 'closed':
            $status = 'closed';
            $tickets->filter(array('staff_id' => $staffId));
            break;
        case 'transferred_me':
            $tickets->filter(array(
                'thread__events__event__name' => 'transferred',
                'thread__events__agent' => $staffId,
            ));
            break;
        case 'cc':
            $status = 'open';
            if ($userId = $thisstaff->getUserIdStaff())
                $tickets->filter(array('thread__collaborators__user' => $userId));
            else
                $tickets->filter(array('ticket_id' => 0));
            break;
        case 'dept':
            $tickets->filter(array('dept_id__in' => $adminDeptIds));
            break;
        case 'assigned_dept':
            $status = 'open';
            $tickets->filter(array('dept_id__in' => $adminDeptIds));
            break;
        case 'closed_dept':
            $status = 'closed';
            $tickets->filter(array('dept_id__in' => $adminDeptIds));
            break;
        case 'created_dep':
            $status = 'open';
            $tickets->filter(array(
                'thread__events__agent__dept_id__in' => $adminDeptIds,
                'thread__events__event__name' => 'created',
            ));
            break;
        case 'requested_dep':
            $status = 'open';
            if ($thisstaff->getManagedDepartments()) {
                $tickets->filter(array(
                    'thread__events__agent__dept_id' => $deptId,
                    'thread__events__event__name' => 'created',
                    'dept_id__notequal' => $deptId,
                ));
            } else {
                $tickets->filter(array('ticket_id' => 0));
            }
            break;
        case 'transferred':
            $status = 'open';
            if ($thisstaff->getManagedDepartments()) {
                $tickets->filter(array(
                    'thread__events__dept' => $deptId,
                    'thread__events__event__name__in' => array('transferred', 'created'),
                    'dept_id__notequal' => $deptId,
                ));
            } else {
                $tickets->filter(array('ticket_id' => 0));
            }
            break;
        case 'assigned_mteams':
            $status = 'open';
            $tickets->filter(array('team_id__in' => $thisstaff->teams->values_flat('team_id')));
            break;
        case 'closed_mteams':
            $status = 'closed';
            $tickets->filter(array('team_id__in' => $thisstaff->teams->values_flat('team_id')));
            break;
        case 'created_pairs':
            $pairs = null;
            if ($teams = $thisstaff->getTeams()) {
                $pairs = TeamMember::objects()
                    ->distinct('staff_id')
                    ->filter(array(
                        'team_id__in' => $teams,
                        'staff_id__notequal' => $staffId,
                    ))
                    ->values('staff_id');
            }
            if (!$teams || !$pairs)
                $tickets->filter(array('ticket_id' => 0));
            else
                $tickets->filter(array(
                    'thread__events__agent__in' => $pairs,
                    'thread__events__event__name' => 'created',
                ));
            break;
        case 'unassigned_dept':
            $status = 'open';
            $tickets->filter(array('staff_id' => 0, 'team_id' => 0));
            if (count($adminDeptIds))
                $tickets->filter(array('dept_id__in' => $adminDeptIds));
            else
                $tickets->filter(array('ticket_id' => 0));
            break;
        case 'unassigned':
            $status = 'open';
            $tickets->filter(array('staff_id' => 0));
            if (count($adminDeptIds))
                $tickets->filter(array('dept_id__in' => $adminDeptIds, 'team_id__gt' => 0));
            elseif ($thisstaff->getLeadedTeams())
                $tickets->filter(array('team_id__in' => $thisstaff->teams->values_flat('team_id')));
            else
                $tickets->filter(array('ticket_id' => 0));
            break;
        case 'overdue':
            $status = 'open';
            $tickets->filter(array('isoverdue' => 1));
            break;
        default:
            $tickets->filter(array('ticket_id' => 0));
    }

    return $status;
}

function iris_ticket_queue_count($queue_name, $thisstaff) {
    static $counts = array();
    $key = $thisstaff->getId().':'.$queue_name;
    if (array_key_exists($key, $counts))
        return $counts[$key];

    $tickets = Ticket::objects();
    iris_apply_ticket_queue_filters($tickets, $queue_name, $thisstaff);
    // Badges represent the open inventory, including queues which can show
    // both open and closed expedientes in the main table.
    $tickets->filter(array('status__state' => 'open'));
    $tickets->filter($thisstaff->getTicketsVisibility());
    $tickets->distinct('ticket_id');
    return $counts[$key] = (int) $tickets->count();
}

$page='';
$ticket = $user = $org = null;
$redirect = false;
//LOCKDOWN...See if the id provided is actually valid and if the user has access.
if(isset($_REQUEST['id']) || isset($_REQUEST['number'])) {
    if($_REQUEST['id'] && !($ticket=Ticket::lookup($_REQUEST['id'])))
         $errors['err']=sprintf(__('%s: Unknown or invalid ID.'), __('ticket'));
    elseif($_REQUEST['number'] && !($ticket=Ticket::lookup(array('number' => $_REQUEST['number']))))
         $errors['err']=sprintf(__('%s: Unknown or invalid number.'), __('ticket'));
     elseif(!$ticket || !$ticket->checkStaffPerm($thisstaff)) {
         $errors['err']=__('Access denied. Contact admin if you believe this is in error');
         $ticket=null; 
     }
}

if (!$ticket) {
    // Search for user
    if (isset($_REQUEST['uid']))
        $user = User::lookup($_REQUEST['uid']);

    if (isset($_REQUEST['email']))
        $user = User::lookupByEmail($_REQUEST['email']);

    if (isset($_GET['orgid']))
        $org = Organization::lookup($_GET['orgid']);

    $queue_key = '::IRIS:Q:T';
    $queue_name = strtolower((string) ($_GET['status'] ?: $_SESSION[$queue_key] ?: 'assigned'));
    $valid_queues = array('assigned', 'open_me', 'involved', 'thread_me',
        'closed', 'transferred_me', 'cc', 'dept', 'assigned_dept',
        'closed_dept', 'created_dep', 'requested_dep', 'transferred',
        'assigned_mteams', 'closed_mteams', 'created_pairs', 'unassigned_dept',
        'unassigned', 'overdue');
    if (!in_array($queue_name, $valid_queues, true))
        $queue_name = 'assigned';
    $_SESSION[$queue_key] = $queue_name;
    $_GET['status'] = $_REQUEST['status'] = $queue_name;
}

// Configure form for file uploads
$response_form = new SimpleForm(array(
    'attachments' => new FileUploadField(array('id'=>'attach',
        'name'=>'attach:response',
        'configuration' => array('extensions'=>'')))
));
$note_form = new SimpleForm(array(
    'attachments' => new FileUploadField(array('id'=>'attach',
        'name'=>'attach:note',
        'configuration' => array('extensions'=>'')))
));

//At this stage we know the access status. we can process the post.
if($_POST && !$errors):

    if($ticket && $ticket->getId()) {
        //More coffee please.
        $errors=array();
        $lock = $ticket->getLock(); //Ticket lock if any
        $role = $ticket->getRole($thisstaff);
        $dept = $ticket->getDept();
        $isManager = $dept->isManager($thisstaff); //Check if Agent is Manager
        switch(strtolower($_POST['a'])):
        case 'reply':
            if (!$role || !$role->hasPerm(Ticket::PERM_REPLY)) {
                $errors['err'] = __('Action denied. Contact admin for access');
            } else {
                $vars = $_POST;
                $vars['files'] = $response_form->getField('attachments')->getFiles();
                $vars['response'] = ThreadEntryBody::clean($vars['response']);
                if(!$vars['response'])
                    $errors['response']=__('Response required');

                if ($cfg->isTicketLockEnabled()) {
                    if (!$lock) {
                        $errors['err'] = sprintf('%s %s', __('This action requires a lock.'), __('Please try again!'));
                    }
                    // Use locks to avoid double replies
                    elseif ($lock->getStaffId()!=$thisstaff->getId()) {
                        $errors['err'] = __('Action Denied. Ticket is locked by someone else!');
                    }
                    // Attempt to renew the lock if possible
                    elseif (($lock->isExpired() && !$lock->renew())
                        ||($lock->getCode() != $_POST['lockCode'])
                    ) {
                        $errors['err'] = sprintf('%s %s', __('Your lock has expired.'), __('Please try again!'));
                    }
                }

                //Make sure the email is not banned
                if(!$errors['err'] && Banlist::isBanned($ticket->getEmail()))
                    $errors['err']=__('Email is in banlist. Must be removed to reply.');
            }

            $alert =  strcasecmp('none', $_POST['reply-to']);
            if (!$errors) {
                // Add new collaborators (if any)
                $_errors = array();
                if (isset($vars['ccs']) && count($vars['ccs']))
                    $ticket->addCollaborators($vars['ccs'], array(), $_errors);
                // set status of collaborators
                if ($collabs = $ticket->getCollaborators()) {
                    foreach ($collabs as $collaborator) {
                        $cid = $collaborator->getUserId();
                        // Enable collaborators if they were reselected
                        if (!$collaborator->isActive() && ($vars['ccs'] && in_array($cid, $vars['ccs'])))
                            $collaborator->setFlag(Collaborator::FLAG_ACTIVE, true);
                        // Disable collaborators if they were unchecked
                        elseif ($collaborator->isActive() && (!$vars['ccs'] || !in_array($cid, $vars['ccs'])))
                            $collaborator->setFlag(Collaborator::FLAG_ACTIVE, false);
                        $collaborator->save();
                    }
                }
            }
            if (!$errors && ($response=$ticket->postReply($vars, $errors,
                            $alert))) {
                $msg = sprintf(__('%s: Reply posted successfully'),
                        sprintf(__('Ticket #%s'),
                            sprintf('<a href="tickets.php?id=%d"><b>%s</b></a>',
                                $ticket->getId(), $ticket->getNumber()))
                        );

                // Clear attachment list
                $response_form->setSource(array());
                $response_form->getField('attachments')->reset();

                // Remove staff's locks
                $ticket->releaseLock($thisstaff->getId());

                // Cleanup response draft for this user
                Draft::deleteForNamespace(
                    'ticket.response.' . $ticket->getId(),
                    $thisstaff->getId());

                if ($ticket->isClosed())
                    $ticket = null;

                $redirect = 'tickets.php';
                if ($ticket && $thisstaff->getReplyRedirect() == 'Ticket')
                    $redirect = 'tickets.php?id='.$ticket->getId();

            } elseif (!$errors['err']) {
                $errors['err']=sprintf('%s %s',
                    __('Unable to post the reply.'),
                    __('Correct any errors below and try again.'));
            }
            break;
        case 'postnote': /* Post Internal Note */
            $vars = $_POST;
            $vars['files'] = $note_form->getField('attachments')->getFiles();
            $vars['note'] = ThreadEntryBody::clean($vars['note']);

            if ($cfg->isTicketLockEnabled()) {
                if (!$lock) {
                    $errors['err'] = sprintf('%s %s', __('This action requires a lock.'), __('Please try again!'));
                }
                // Use locks to avoid double replies
                elseif ($lock->getStaffId()!=$thisstaff->getId()) {
                    $errors['err'] = __('Action Denied. Ticket is locked by someone else!');
                }
                elseif ($lock->getCode() != $_POST['lockCode']) {
                    $errors['err'] = sprintf('%s %s', __('Your lock has expired.'), __('Please try again!'));
                }
            }

            $wasOpen = ($ticket->isOpen());
            if(($note=$ticket->postNote($vars, $errors, $thisstaff))) {

                $msg = sprintf(__('%s: %s posted successfully'),
                        sprintf(__('Ticket #%s'),
                            sprintf('<a href="tickets.php?id=%d"><b>%s</b></a>',
                                $ticket->getId(), $ticket->getNumber())),
                        __('Internal note')
                        );
                // Clear attachment list
                $note_form->setSource(array());
                $note_form->getField('attachments')->reset();

                // Remove staff's locks
                $ticket->releaseLock($thisstaff->getId());

                if($wasOpen && $ticket->isClosed())
                    $ticket = null; //Going back to main listing.
                else
                    // Ticket is still open -- clear draft for the note
                    Draft::deleteForNamespace('ticket.note.'.$ticket->getId(),
                        $thisstaff->getId());

                 $redirect = 'tickets.php';
                 if ($ticket)
                     $redirect ='tickets.php?id='.$ticket->getId();

            } else {

                if(!$errors['err'])
                    $errors['err'] = __('Unable to post internal note - missing or invalid data.');

                $errors['postnote'] = sprintf('%s %s',
                    __('Unable to post the note.'),
                    __('Correct any errors below and try again.'));
            }
            break;
        case 'edit':
        case 'update':
            if(!$ticket || !$role->hasPerm(Ticket::PERM_EDIT))
                $errors['err']=__('Permission Denied. You are not allowed to edit tickets');
            elseif($ticket->update($_POST,$errors)) {
                $msg=__('Ticket updated successfully');
                $redirect = 'tickets.php?id='.$ticket->getId();
                $_REQUEST['a'] = null; //Clear edit action - going back to view.
                //Check to make sure the staff STILL has access post-update (e.g dept change).
                if(!$ticket->checkStaffPerm($thisstaff))
                    $ticket=null;
            } elseif(!$errors['err']) {
                $errors['err']=sprintf('%s %s',
                    sprintf(__('Unable to update %s.'), __('this ticket')),
                    __('Correct any errors below and try again.')
                );
            }
            break;
        case 'process':
            switch(strtolower($_POST['do'])):
                case 'claim':
                    if(!$role->hasPerm(Ticket::PERM_EDIT)) {
                        $errors['err'] = __('Permission Denied. You are not allowed to assign/claim tickets.');
                    } elseif(!$ticket->isOpen()) {
                        $errors['err'] = __('Only open tickets can be assigned');
                    } elseif($ticket->isAssigned()) {
                        $errors['err'] = sprintf(__('Ticket is already assigned to %s'),$ticket->getAssigned());
                    } elseif ($ticket->claim()) {
                        $msg = __('Ticket is now assigned to you!');
                    } else {
                        $errors['err'] = sprintf('%s %s', __('Problems assigning the ticket.'), __('Please try again!'));
                    }
                    break;
                case 'overdue':
                    if(!$dept || !$isManager) {
                        $errors['err']=__('Permission Denied. You are not allowed to flag tickets overdue');
                    } elseif($ticket->markOverdue()) {
                        $msg=sprintf(__('Ticket flagged as overdue by %s'),$thisstaff->getName());
                        $ticket->logActivity(__('Ticket Marked Overdue'),$msg);
                    } else {
                        $errors['err']=sprintf('%s %s', __('Problems marking the the ticket overdue.'), __('Please try again!'));
                    }
                    break;
                case 'banemail':
                    if (!$thisstaff->hasPerm(Email::PERM_BANLIST)) {
                        $errors['err']=__('Permission Denied. You are not allowed to ban emails');
                    } elseif(BanList::includes($ticket->getEmail())) {
                        $errors['err']=__('Email already in banlist');
                    } elseif(Banlist::add($ticket->getEmail(),$thisstaff->getName())) {
                        $msg=sprintf(__('Email %s added to banlist'),$ticket->getEmail());
                    } else {
                        $errors['err']=__('Unable to add the email to banlist');
                    }
                    break;
                case 'unbanemail':
                    if (!$thisstaff->hasPerm(Email::PERM_BANLIST)) {
                        $errors['err'] = __('Permission Denied. You are not allowed to remove emails from banlist.');
                    } elseif(Banlist::remove($ticket->getEmail())) {
                        $msg = __('Email removed from banlist');
                    } elseif(!BanList::includes($ticket->getEmail())) {
                        $warn = __('Email is not in the banlist');
                    } else {
                        $errors['err']=sprintf('%s %s', __('Unable to remove the email from banlist.'), __('Please try again!'));
                    }
                    break;
                case 'changeuser':
                    if (!$role->hasPerm(Ticket::PERM_EDIT)) {
                        $errors['err']=__('Permission Denied. You are not allowed to edit tickets');
                    } elseif (!$_POST['user_id'] || !($user=User::lookup($_POST['user_id']))) {
                        $errors['err'] = __('Unknown user selected');
                    } elseif ($ticket->changeOwner($user)) {
                        $msg = sprintf(__('Ticket ownership changed to %s'),
                            Format::htmlchars($user->getName()));
                    } else {
                        $errors['err'] = sprintf('%s %s', __('Unable to change ticket ownership.'), __('Please try again!'));
                    }
                    break;
                case 'addcc':
                    if (!$role->hasPerm(Ticket::PERM_EDIT)) {
                        $errors['err']=__('Permission Denied. You are not allowed to add collaborators');
                    } elseif (!$_POST['user_id'] || !($user=User::lookup($_POST['user_id']))) {
                        $errors['err'] = __('Unknown user selected');
                  } elseif ($c2 = $ticket->addCollaborator($user, array(), $errors)) {
                        $c2->setFlag(Collaborator::FLAG_CC, true);
                        $c2->save();
                        $msg = sprintf(__('Collaborator %s added'),
                            Format::htmlchars($user->getName()));
                    }
                    else {
                      $errors['err'] = sprintf('%s %s', __('Unable to add collaborator.'), __('Please try again!'));
                    }
                    break;
                default:
                    $errors['err']=__('You must select action to perform');
            endswitch;
            break;
        default:
            $errors['err']=__('Unknown action');
        endswitch;
    }elseif($_POST['a']) {

        switch($_POST['a']) {
            case 'open':
                $ticket=null;
                if (!$thisstaff ||
                        !$thisstaff->hasPerm(Ticket::PERM_CREATE, false)) {
                     $errors['err'] = sprintf('%s %s',
                             sprintf(__('You do not have permission %s'),
                                 __('to create tickets')),
                             __('Contact admin for such access'));
                } else {
                    $vars = $_POST;

                    if ($vars['uid'] && !($user=User::lookup($vars['uid'])))
                        $vars['uid'] = 0;

                    $vars['files'] = $response_form->getField('attachments')->getFiles();

                    if(($ticket=Ticket::open($vars, $errors))) {
                        $msg=__('Ticket created successfully');
                        $redirect = 'tickets.php?id='.$ticket->getId();
                        $_REQUEST['a']=null;
                        if (!$ticket->checkStaffPerm($thisstaff) || $ticket->isClosed())
                            $ticket=null;
                        Draft::deleteForNamespace('ticket.staff%', $thisstaff->getId());
                        // Drop files from the response attachments widget
                        $response_form->setSource(array());
                        $response_form->getField('attachments')->reset();
                        $_SESSION[':form-data'] = null;
                        // Regenerate Session ID
                        $thisstaff->regenerateSession();
                    } elseif(!$errors['err']) {
                        // ensure that we retain the tid if ticket is created from thread
                        if ($_SESSION[':form-data']['ticketId'] || $_SESSION[':form-data']['taskId'])
                            $_GET['tid'] = $_SESSION[':form-data']['ticketId'] ?: $_SESSION[':form-data']['taskId'];

                        $errors['err']=sprintf('%s %s',
                            __('Unable to create the ticket.'),
                            __('Correct any errors below and try again.'));
                    }
                }
                break;
        }
    }
    if(!$errors)
        $thisstaff ->resetStats(); //We'll need to reflect any changes just made!
endif;

if ($redirect) {
    if ($msg)
        Messages::success($msg);
    Http::redirect($redirect);
}

//Navigation
$nav->setTabActive('tickets');
$addTicketMenu = function($name, $description, $title, $icon, $badge=null,
        $badgeClass='warning') use ($nav) {
    $item = array(
        'desc' => __($description),
        'title' => __($title),
        'href' => 'tickets.php?status='.$name,
        'iconclass' => $icon,
    );
    if ($badge !== null) {
        $item['badge'] = $badge;
        $item['badge_class'] = $badgeClass;
        $item['badge_object'] = 'expediente';
    }
    $nav->addSubMenu($item,
        isset($_REQUEST['status']) && $_REQUEST['status'] === $name);
};

$addTicketMenu('assigned', 'Asignados a mí', 'Expedientes asignados a mí',
    'assignedTickets', iris_ticket_queue_count('assigned', $thisstaff));
$addTicketMenu('open_me', 'Creados por mí', 'Expedientes creados por mí',
    'assignedTickets', iris_ticket_queue_count('open_me', $thisstaff));
$addTicketMenu('involved', 'Participaciones', 'Expedientes en los que he participado',
    'assignedTickets', iris_ticket_queue_count('involved', $thisstaff));
$addTicketMenu('thread_me', 'Gestionados por mí', 'Expedientes asignados por mí',
    'closedTickets', iris_ticket_queue_count('thread_me', $thisstaff));
$addTicketMenu('closed', 'Mis expedientes cerrados', 'Expedientes cerrados asignados a mí',
    'closedTickets');
$addTicketMenu('transferred_me', 'Transferidos por mí', 'Expedientes transferidos por mí',
    'departments');
$addTicketMenu('cc', 'Con Copia', 'Expedientes abiertos con copia a mí',
    'closedTickets', iris_ticket_queue_count('cc', $thisstaff));

if ($thisstaff->getTeams()) {
    $addTicketMenu('assigned_mteams', 'Mis equipos', 'Expedientes asignados a mis equipos',
        'teams', iris_ticket_queue_count('assigned_mteams', $thisstaff));
    $addTicketMenu('closed_mteams', 'Cerrados en Mis equipos',
        'Expedientes cerrados de mis equipos', 'closedTickets');
    $addTicketMenu('created_pairs', 'Creados por Mis equipos',
        'Expedientes creados por otro miembro de mis equipos',
        'assignedTickets');
}

if (count($thisstaff->getAdminDepartments())) {
    $addTicketMenu('dept', 'Toda dependencia', 'Todos los expedientes de Mi Dependencia',
        'departments');
    $addTicketMenu('assigned_dept', 'Mi dependencia',
        'Expedientes abiertos de Mi Dependencia', 'departments',
        iris_ticket_queue_count('assigned_dept', $thisstaff));
    $addTicketMenu('closed_dept', 'Cerrados dependencia',
        'Expedientes cerrados de Mi Dependencia', 'closedTickets');
    $addTicketMenu('created_dep', 'Creados dependencia',
        'Expedientes creados por Mi Dependencia', 'assignedTickets',
        iris_ticket_queue_count('created_dep', $thisstaff));
}

if ($thisstaff->getManagedDepartments()) {
    $addTicketMenu('requested_dep', 'Solicitados dependencia',
        'Expedientes solicitados por Mi Dependencia', 'assignedTickets',
        iris_ticket_queue_count('requested_dep', $thisstaff));
    $addTicketMenu('transferred', 'Transferidos dependencia',
        'Expedientes transferidos por Mi Dependencia', 'departments',
        iris_ticket_queue_count('transferred', $thisstaff));
}

if (count($thisstaff->getAdminDepartments()) || $thisstaff->getLeadedTeams()) {
    $addTicketMenu('unassigned_dept', 'Sin Revisar',
        'Expedientes sin asignar en Mi Dependencia', 'overdueTickets',
        iris_ticket_queue_count('unassigned_dept', $thisstaff), 'critical');
    $addTicketMenu('unassigned', 'Sin asignar',
        'Expedientes sin asignar a un agente', 'overdueTickets',
        iris_ticket_queue_count('unassigned', $thisstaff));
}

$overdueCount = iris_ticket_queue_count('overdue', $thisstaff);
if ($overdueCount)
    $addTicketMenu('overdue', 'Vencidos', 'Expedientes vencidos',
        'overdueTickets', $overdueCount, 'critical');

if ($thisstaff->hasPerm(Ticket::PERM_CREATE, false)) {
    $nav->addSubMenu(array('desc'=>__('Nuevo expediente'),
                           'title'=> __('Abrir un nuevo expediente'),
                           'href'=>'tickets.php?a=open',
                           'iconclass'=>'newTicket',
                           'id' => 'new-ticket'),
                        (isset($_REQUEST['a']) && $_REQUEST['a']=='open'));
}

$ost->addExtraHeader('<script type="text/javascript" src="js/ticket.js"></script>');
$ost->addExtraHeader('<script type="text/javascript" src="js/thread.js"></script>');
$ost->addExtraHeader('<meta name="tip-namespace" content="tickets.queue" />',
    "$('#content').data('tipNamespace', 'tickets.queue');");

if($ticket) {
    $ost->setPageTitle(sprintf(__('Expediente #%s'),$ticket->getNumber()));
    $nav->setActiveSubMenu(-1);
    $inc = 'ticket-view.inc.php';
    if ($_REQUEST['a']=='edit'
            && $ticket->checkStaffPerm($thisstaff, Ticket::PERM_EDIT)) {
        $inc = 'ticket-edit.inc.php';
        if (!$forms) $forms=DynamicFormEntry::forTicket($ticket->getId());
        // Auto add new fields to the entries
        foreach ($forms as $f) {
            $f->filterFields(function($f) { return !$f->isStorable(); });
            $f->addMissingFields();
        }
    } elseif($_REQUEST['a'] == 'print') {
        if (!extension_loaded('mbstring'))
            $errors['err'] = sprintf('%s %s',
                'mbstring',
                __('extension required to print ticket to PDF'));
        elseif (!$ticket->pdfExport($_REQUEST['psize'], $_REQUEST['notes'], $_REQUEST['events']))
            $errors['err'] = __('Unable to export the ticket to PDF for print.')
                .' '.__('Internal error occurred');
    } elseif ($_GET['a'] == 'zip' && !$ticket->zipExport($_REQUEST['notes'], $_REQUEST['tasks'])) {
        $errors['err'] = __('Unable to export the ticket to ZIP.')
            .' '.__('Internal error occurred');
    } elseif (PluginManager::auditPlugin() && $_REQUEST['a'] == 'export' && strtolower($_REQUEST['t']) == 'audits') {
      require_once(sprintf('phar:///%s/plugins/audit.phar/class.audit.php', INCLUDE_DIR));
      $show = AuditEntry::$show_view_audits;
      $filename = sprintf('%s-audits-%s.csv',
              $ticket->getNumber(), date('Ymd'));
      $tableInfo = AuditEntry::getTableInfo($ticket, true);
      if (!Export::audits('ticket', $filename, $tableInfo, $ticket, 'csv', $show))
          $errors['err'] = __('Unable to dump query results.')
              .' '.__('Internal error occurred');
    }
} else {
    $inc = 'templates/queue-tickets.tmpl.php';
    if ((isset($_REQUEST['a']) && $_REQUEST['a']=='open') &&
            $thisstaff->hasPerm(Ticket::PERM_CREATE, false)) {
        $inc = 'ticket-open.inc.php';
        if (!$user && $userId = $thisstaff->getUserIdStaff())
            $user = User::lookup($userId);
    } elseif ($_REQUEST['a'] === 'export') {
        $timezone = new DateTimeZone('America/Bogota');
        $date = new DateTime('now', $timezone);
        $filename = sprintf('expedientes-%s-%s.csv',
            $queue_name, $date->format('YmdHis'));
        if (!($query = $_SESSION[':Q:tickets']))
            $errors['err'] = __('Query token not found');
        else {
            $fields = array(
                'number' => __('Número'),
                'created' => __('Fecha de creación'),
                'lastupdate' => __('Última actividad'),
                'cdata__subject' => __('Asunto'),
                'dept::getLocalName' => __('Dependencia asignada'),
                'staff::getName' => __('Agente asignado'),
                'team::getName' => __('Equipo asignado'),
                'status::getName' => __('Estado'),
            );
            Export::saveTickets($query, $fields, $filename, 'csv');
        }
    }

    //set refresh rate if the user has it configured
    if(!$_POST && !isset($_REQUEST['a']) && ($min=(int)$thisstaff->getRefreshRate())) {
        $js = "+function(){ var qq = setInterval(function() { if ($.refreshTicketView === undefined) return; clearInterval(qq); $.refreshTicketView({$min}*60000); }, 200); }();";
        $ost->addExtraHeader('<script type="text/javascript">'.$js.'</script>',
            $js);
    }
}

require_once(STAFFINC_DIR.'header.inc.php');
require_once(STAFFINC_DIR.$inc);
print $response_form->getMedia();
require_once(STAFFINC_DIR.'footer.inc.php');

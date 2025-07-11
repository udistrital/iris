<?php
/*********************************************************************
    class.task.php

    Task

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2014 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

include_once INCLUDE_DIR.'class.role.php';


class TaskModel extends VerySimpleModel {
    static $meta = array(
        'table' => TASK_TABLE,
        'pk' => array('id'),
        'joins' => array(
            'dept' => array(
                'constraint' => array('dept_id' => 'Dept.id'),
            ),
            'lock' => array(
                'constraint' => array('lock_id' => 'Lock.lock_id'),
                'null' => true,
            ),
            'staff' => array(
                'constraint' => array('staff_id' => 'Staff.staff_id'),
                'null' => true,
            ),
            'team' => array(
                'constraint' => array('team_id' => 'Team.team_id'),
                'null' => true,
            ),
            'thread' => array(
                'constraint' => array(
                    'id'  => 'TaskThread.object_id',
                    "'A'" => 'TaskThread.object_type',
                ),
                'list' => false,
                'null' => false,
            ),
            'cdata' => array(
                'constraint' => array('id' => 'TaskCData.task_id'),
                'list' => false,
            ),
            'entries' => array(
                'constraint' => array(
                    "'A'" => 'DynamicFormEntry.object_type',
                    'id' => 'DynamicFormEntry.object_id',
                ),
                'list' => true,
            ),

            'ticket' => array(
                'constraint' => array(
                    'object_id' => 'Ticket.ticket_id',
                ),
                'null' => true,
            ),
        ),
    );

    const PERM_CREATE   = 'task.create';
    const PERM_EDIT     = 'task.edit';
    const PERM_ASSIGN   = 'task.assign';
    const PERM_TRANSFER = 'task.transfer';
    const PERM_REPLY    = 'task.reply';
    const PERM_CLOSE    = 'task.close';
    const PERM_DELETE   = 'task.delete';
    const PERM_VIEW_ALL = 'task.viewAll';

    static protected $perms = array(
            self::PERM_CREATE    => array(
                'title' =>
                /* @trans */ 'Create',
                'desc'  =>
                /* @trans */ 'Ability to create tasks'),
            self::PERM_EDIT      => array(
                'title' =>
                /* @trans */ 'Edit',
                'desc'  =>
                /* @trans */ 'Ability to edit tasks'),
            self::PERM_ASSIGN    => array(
                'title' =>
                /* @trans */ 'Assign',
                'desc'  =>
                /* @trans */ 'Ability to assign tasks to agents or teams'),
            self::PERM_TRANSFER  => array(
                'title' =>
                /* @trans */ 'Transfer',
                'desc'  =>
                /* @trans */ 'Ability to transfer tasks between departments'),
            self::PERM_REPLY => array(
                'title' =>
                /* @trans */ 'Post Reply',
                'desc'  =>
                /* @trans */ 'Ability to post task update'),
            self::PERM_CLOSE     => array(
                'title' =>
                /* @trans */ 'Close',
                'desc'  =>
                /* @trans */ 'Ability to close tasks'),
            self::PERM_DELETE    => array(
                'title' =>
                /* @trans */ 'Delete',
                'desc'  =>
                /* @trans */ 'Ability to delete tasks'),
            self::PERM_VIEW_ALL    => array(
                'title' =>
                /* @trans */ 'Ver todas',
                'desc'  =>
                /* @trans */ 'Permiso para ver todas las tareas de la dependencia'),
            );

    const ISOPEN    = 0x0001;
    const ISOVERDUE = 0x0002;


    protected function hasFlag($flag) {
        return ($this->get('flags') & $flag) !== 0;
    }

    protected function clearFlag($flag) {
        return $this->set('flags', $this->get('flags') & ~$flag);
    }

    protected function setFlag($flag) {
        return $this->set('flags', $this->get('flags') | $flag);
    }

    function getId() {
        return $this->id;
    }

    function getNumber() {
        return $this->number;
    }

    function getStaffId() {
        return $this->staff_id;
    }

    function getStaff() {
        return $this->staff;
    }

    function getTeamId() {
        return $this->team_id;
    }

    function getTeam() {
        return $this->team;
    }

    function getDeptId() {
        return $this->dept_id;
    }

    function getDept() {
        return $this->dept;
    }

    function getCreateDate() {
        return $this->created;
    }

    function getDueDate() {
        return $this->duedate;
    }

    function getLastActivityDate() {
        return $this->last_entry;
    }

    function getCloseDate() {
        return $this->isClosed() ? $this->closed : '';
    }

    function getSubmitter() {
        return $this->submitter;
    }

    function getLastTaskAssigner() {
        $task_id = $this->getId();
    
        if (!$task_id) return 'No disponible';
    
        $sql = "SELECT COALESCE((
                    SELECT oute.poster
                    FROM ost_ud_thread_entry oute  
                    WHERE oute.thread_id = (
                        SELECT id 
                        FROM ost_ud_thread o
                        WHERE o.object_id = " . db_input($task_id) . "
                        LIMIT 1
                    )
                    AND (
                        oute.title LIKE '%asign%' OR
                        oute.title LIKE '%assigned%'
                    )
                    ORDER BY oute.created DESC
                    LIMIT 1
                ), 'No ha sido asignada') AS ultimo_asignador";
    
        if (($row = db_fetch_array(db_query($sql))))
            return $row['ultimo_asignador'];
    
        return 'No ha sido asignada';
    }

    function getTaskCreator() {
        $task_id = $this->getId();
    
        if (!$task_id) return 'No disponible';
    
        $sql = "SELECT COALESCE((
                    SELECT oute.poster
                    FROM ost_ud_thread_entry oute  
                    WHERE oute.thread_id = (
                        SELECT id 
                        FROM ost_ud_thread o
                        WHERE o.object_id = " . db_input($task_id) . "
                        LIMIT 1
                    )
                    AND oute.type = 'M'
                    LIMIT 1
                ), '') AS ultimo_asignador";
    
        if (($row = db_fetch_array(db_query($sql))))
            return $row['ultimo_asignador'];
        
        //si no llega encontrar nada, lo deja vacio
        return '';
    }

    function isOpen() {
        return $this->hasFlag(self::ISOPEN);
    }

    function isClosed() {
        return !$this->isOpen();
    }

    function isCreator() {
        global $thisstaff;
        $this->isCreator = Task::objects()
            ->filter(
                array(
                    'id' => $this->getId(),
                    'thread__events__agent' => $thisstaff->getId(),
                    'thread__events__event__name' => 'created',
                )
            )
            ->values_flat('id')
            ->first()
            ?: false;

        return $this->isCreator;
    }

    function isCloseable() {

        if ($this->isClosed())
            return true;

        $warning = null;
        if ($this->getMissingRequiredFields()) {
            $warning = sprintf(
                    __( '%1$s is missing data on %2$s one or more required fields %3$s and cannot be closed'),
                    __('This task'),
                    '', '');
        }

        return $warning ?: true;
    }

    protected function close() {
        return $this->clearFlag(self::ISOPEN);
    }

    protected function reopen() {
        return $this->setFlag(self::ISOPEN);
    }

    function isAssigned($to=null) {
        if (!$this->isOpen())
            return false;

        if (is_null($to))
            return ($this->getStaffId() || $this->getTeamId());

        switch (true) {
        case $to instanceof Staff:
            return ($to->getId() == $this->getStaffId() ||
                    $to->isTeamMember($this->getTeamId()));
            break;
        case $to instanceof Team:
            return ($to->getId() == $this->getTeamId());
            break;
        }

        return false;
    }

    function isOverdue() {
        return $this->hasFlag(self::ISOVERDUE);
    }

    static function getPermissions() {
        return self::$perms;
    }

}

RolePermission::register(/* @trans */ 'Tasks', TaskModel::getPermissions());


class Task extends TaskModel implements RestrictedAccess, Threadable {
    var $form;
    var $entry;

    var $_thread;
    var $_entries;
    var $_answers;

    var $lastrespondent;

    function __onload() {
        $this->loadDynamicData();
    }

    function loadDynamicData() {
        if (!isset($this->_answers)) {
            $this->_answers = array();
            foreach (DynamicFormEntryAnswer::objects()
                ->filter(array(
                    'entry__object_id' => $this->getId(),
                    'entry__object_type' => ObjectModel::OBJECT_TYPE_TASK
                )) as $answer
            ) {
                $tag = mb_strtolower($answer->field->name)
                    ?: 'field.' . $answer->field->id;
                    $this->_answers[$tag] = $answer;
            }
        }
        return $this->_answers;
    }

    function getStatus() {
        return $this->isOpen() ? __('Open') : __('Completed');
    }

    function getStatusExport() {
        return $this->isOpen() ? 'Abierto' : 'Cerrado';
    }

    function getCreateDateExport() {
        return Format::datetimeLocal($this->getCreateDate());
    }

    function getDueDateExport() {
        return Format::datetimeLocal($this->getDueDate());
    }

    function getCloseDateExport() {
        return Format::datetimeLocal($this->getCloseDate());
    }

    function getLastActivityDateExport() {
        return Format::datetimeLocal($this->getLastActivityDate());
    }

    function getTaskStaffLink() {
        global $cfg;
        return sprintf('%s/scp/tasks.php?id=%d', $cfg->getBaseUrl(), $this->getId());
    }

    function getTitle() {
        return $this->__cdata('title', ObjectModel::OBJECT_TYPE_TASK);
    }

    function checkStaffPerm($staff, $perm=null) {

        // Must be a valid staff
        if (!$staff instanceof Staff && !($staff=Staff::lookup($staff)))
            return false;

        // Check access based on department or assignment
        if (!$staff->canAccessDept($this->getDept())
                && $this->isOpen()
                && $staff->getId() != $this->getStaffId()
                && !$staff->isTeamMember($this->getTeamId()))
            return false;

        // At this point staff has access unless a specific permission is
        // requested
        if ($perm === null)
            return true;

        // Permission check requested -- get role.
        if (!($role=$staff->getRole($this->getDept())))
            return false;

        // Check permission based on the effective role
        return $role->hasPerm($perm);
    }

    function getAssignee() {

        if (!$this->isOpen() || !$this->isAssigned())
            return false;

        if ($this->staff)
            return $this->staff;

        if ($this->team)
            return $this->team;

        return null;
    }

    function getAssigneeId() {

        if (!($assignee=$this->getAssignee()))
            return null;

        $id = '';
        if ($assignee instanceof Staff)
            $id = 's'.$assignee->getId();
        elseif ($assignee instanceof Team)
            $id = 't'.$assignee->getId();

        return $id;
    }

    function getAssignees() {

        $assignees=array();
        if ($this->staff)
            $assignees[] = $this->staff->getName();

        //Add team assignment
        if ($this->team)
            $assignees[] = $this->team->getName();

        return $assignees;
    }

    function getAssigned($glue='/') {
        $assignees = $this->getAssignees();

        return $assignees ? implode($glue, $assignees):'';
    }

    function getLastRespondent() {

        if (!isset($this->lastrespondent)) {
            $this->lastrespondent = Staff::objects()
                ->filter(array(
                'staff_id' => static::objects()
                    ->filter(array(
                        'thread__entries__type' => 'R',
                        'thread__entries__staff_id__gt' => 0
                    ))
                    ->values_flat('thread__entries__staff_id')
                    ->order_by('-thread__entries__id')
                    ->limit('1,1')
                ))
                ->first()
                ?: false;
        }

        return $this->lastrespondent;
    }

    function getField($fid) {
        if (is_numeric($fid))
            return $this->getDymanicFieldById($fid);

        // Special fields
        switch ($fid) {
        case 'duedate':
            return DateTimeField::init(array(
                'id' => $fid,
                'name' => $fid,
                'default' => Misc::db2gmtime($this->getDueDate()),
                'label' => __('Due Date'),
                'configuration' => array(
                    'min' => Misc::gmtime(),
                    'time' => true,
                    'gmt' => false,
                    'future' => true,
                    )
                ));
        }
    }

    function getDymanicFieldById($fid) {
        foreach (DynamicFormEntry::forObject($this->getId(),
            ObjectModel::OBJECT_TYPE_TASK) as $form) {
                foreach ($form->getFields() as $field)
                    if ($field->getId() == $fid)
                        return $field;
        }
    }

    function getDynamicFields($criteria=array()) {

        $fields = DynamicFormField::objects()->filter(array(
                    'id__in' => $this->entries
                    ->filter($criteria)
                ->values_flat('answers__field_id')));

        return ($fields && count($fields)) ? $fields : array();
    }

    function getMissingRequiredFields() {

        return $this->getDynamicFields(array(
                    'answers__field__flags__hasbit' => DynamicFormField::FLAG_ENABLED,
                    'answers__field__flags__hasbit' => DynamicFormField::FLAG_CLOSE_REQUIRED,
                    'answers__value__isnull' => true,
                    ));
    }

    function getParticipants() {
        $participants = array();
        foreach ($this->getThread()->collaborators as $c)
            $participants[] = $c->getName();

        return $participants ? implode(', ', $participants) : ' ';
    }

    function getThreadId() {
        return $this->thread->getId();
    }

    function getThread() {
        return $this->thread;
    }

    function getThreadEntry($id) {
        return $this->getThread()->getEntry($id);
    }

    function getThreadEntries($type=false) {
        $thread = $this->getThread()->getEntries();
        if ($type && is_array($type))
            $thread->filter(array('type__in' => $type));
        return $thread;
    }

    function postThreadEntry($type, $vars, $options=array()) {
        $errors = array();
        $poster = isset($options['poster']) ? $options['poster'] : null;
        $alert = isset($options['alert']) ? $options['alert'] : true;
        switch ($type) {
        case 'N':
        case 'M':
            return $this->getThread()->addDescription($vars);
            break;
        default:
            return $this->postNote($vars, $errors, $poster, $alert);
        }
    }

    function getForm() {
        if (!isset($this->form)) {
            // Look for the entry first
            if ($this->form = DynamicFormEntry::lookup(
                        array('object_type' => ObjectModel::OBJECT_TYPE_TASK))) {
                return $this->form;
            }
            // Make sure the form is in the database
            elseif (!($this->form = DynamicForm::lookup(
                            array('type' => ObjectModel::OBJECT_TYPE_TASK)))) {
                $this->__loadDefaultForm();
                return $this->getForm();
            }
            // Create an entry to be saved later
            $this->form = $this->form->instanciate();
            $this->form->object_type = ObjectModel::OBJECT_TYPE_TASK;
        }

        return $this->form;
    }

    // Unassign primary assignee
    function unassign() {
        // We can't release what is not assigned buddy!
        if (!$this->isAssigned())
            return true;

        // We can only unassign OPEN tickets.
        if ($this->isClosed())
            return false;

        // Unassign staff (if any)
        if ($this->getStaffId() && !$this->setStaffId(0))
            return false;

        // Unassign team (if any)
        if ($this->getTeamId() && !$this->setTeamId(0))
            return false;

        return true;
    }

    function setTeamId($teamId) {
        if (!is_numeric($teamId))
            return false;

        $this->team = Team::lookup($teamId);
        return $this->save();
    }

    function setStaffId($staffId) {
        if (!is_numeric($staffId))
            return false;

        $this->staff = Staff::lookup($staffId);
        return $this->save();
    }

    function getAssignmentForm($source=null, $options=array()) {
        global $thisstaff;

        $prompt = $assignee = '';
        // Possible assignees
        $dept = $this->getDept();
        $deptid = $this->getDeptId();
        switch (strtolower($options['target'])) {
            case 'agents':
                if (!$source && $this->isOpen() && $this->staff)
                    $assignee = sprintf('s%d', $this->staff->getId());
                $prompt = __('Select an Agent');
                break;
            case 'teams':
                if (!$source && $this->isOpen() && $this->team)
                    $assignee = sprintf('t%d', $this->team->getId());
                $prompt = __('Select a Team');
                break;
        }

        // Default to current assignee if source is not set
        if (!$source)
            $source = array('assignee' => array($assignee));

        $form = AssignmentForm::instantiate($source, $options);

        // Field configurations
        if ($f=$form->getField('assignee')) {
            $f->configure('dept', $dept);
            $f->configure('deptid', $deptid);
            $f->configure('staff', $thisstaff);
            if ($prompt)
                $f->configure('prompt', $prompt);
            if ($options['target'])
                $f->configure('target', $options['target']);
        }

        return $form;
    }

    function getClaimForm($source=null, $options=array()) {
        global $thisstaff;

        $id = sprintf('s%d', $thisstaff->getId());
        if(!$source)
            $source = array('assignee' => array($id));

        $form = ClaimForm::instantiate($source, $options);
        $form->setAssignees(array($id => $thisstaff->getName()));

        return $form;

    }


    function getTransferForm($source=null) {

        if (!$source)
            $source = array('dept' => array($this->getDeptId()));

        return TransferForm::instantiate($source);
    }

    function addDynamicData($data) {

        $tf = TaskForm::getInstance($this->id, true);
        foreach ($tf->getFields() as $f)
            if (isset($data[$f->get('name')]))
                $tf->setAnswer($f->get('name'), $data[$f->get('name')]);

        $tf->save();

        return $tf;
    }

    function getDynamicData($create=true) {
        if (!isset($this->_entries)) {
            $this->_entries = DynamicFormEntry::forObject($this->id,
                    ObjectModel::OBJECT_TYPE_TASK)->all();
            if (!$this->_entries && $create) {
                $f = TaskForm::getInstance($this->id, true);
                $f->save();
                $this->_entries[] = $f;
            }
        }

        return $this->_entries ?: array();
    }

    function setStatus($status, $comments='', &$errors=array()) {
        global $thisstaff;

        $ecb = null;
        switch($status) {
        case 'open':
            if ($this->isOpen())
                return false;

            $this->reopen();
            $this->closed = null;

            $ecb = function ($t) use($thisstaff) {
                $t->logEvent('reopened', false, null, 'closed');

                if ($t->ticket) {
                    $t->ticket->reopen();
                    $vars = array(
                            'title' => sprintf('Task %s Reopened',
                                $t->getNumber()),
                            'note' => __('Task reopened')
                            );
                    $t->ticket->logNote($vars['title'], $vars['note'], $thisstaff);
                }
            };
            break;
        case 'closed':
            if ($this->isClosed())
                return false;

            // Check if task is closeable
            $closeable = $this->isCloseable();
            if ($closeable !== true)
                $errors['err'] = $closeable ?: sprintf(__('%s cannot be closed'), __('This task'));

            if ($errors)
                return false;

            $this->close();
            $this->closed = SqlFunction::NOW();
            $ecb = function($t) use($thisstaff) {
                $t->logEvent('closed');

                if ($t->ticket) {
                    $vars = array(
                            'title' => sprintf('Task %s Closed',
                                $t->getNumber()),
                            'note' => __('Task closed')
                            );
                    $t->ticket->logNote($vars['title'], $vars['note'], $thisstaff);
                }
            };
            break;
        default:
            return false;
        }

        if (!$this->save(true))
            return false;

        // Log events via callback
        if ($ecb) $ecb($this);

        if ($comments) {
            $errors = array();
            $this->postNote(array(
                        'note' => $comments,
                        'title' => sprintf(
                            __('Status changed to %s'),
                            $this->getStatus())
                        ),
                    $errors,
                    $thisstaff);
        }

        return true;
    }

    function to_json() {

        $info = array(
                'id'  => $this->getId(),
                'title' => $this->getTitle()
                );

        return JsonDataEncoder::encode($info);
    }

    function __cdata($field, $ftype=null) {

        foreach ($this->getDynamicData() as $e) {
            // Make sure the form type matches
            if (!$e->form
                    || ($ftype && $ftype != $e->form->get('type')))
                continue;

            // Get the named field and return the answer
            if ($a = $e->getAnswer($field))
                return $a;
        }

        return null;
    }

    function __toString() {
        return (string) $this->getTitle();
    }

    /* util routines */

    function logEvent($state, $data=null, $user=null, $annul=null) {
        switch ($state) {
            case 'transferred':
            case 'edited':
                $type = $data;
                $type['type'] = $state;
                break;
            case 'assigned':
                break;
            default:
                $type = array('type' => $state);
                break;
        }
        if ($type)
            Signal::send('object.created', $this, $type);
        $this->getThread()->getEvents()->log($this, $state, $data, $user, $annul);
    }

    function claim(ClaimForm $form, &$errors) {
        global $thisstaff;

        $dept = $this->getDept();
        $assignee = $form->getAssignee();
        if (!($assignee instanceof Staff)
                || !$thisstaff
                || $thisstaff->getId() != $assignee->getId()) {
            $errors['err'] = __('Unknown assignee');
        } elseif (!$assignee->isAvailable()) {
            $errors['err'] = __('Agent is unavailable for assignment');
        } elseif (!$dept->canAssign($assignee)) {
            $errors['err'] = __('Permission denied');
        }

        if ($errors)
            return false;

        $type = array('type' => 'assigned', 'claim' => true);
        Signal::send('object.edited', $this, $type);

        return $this->assignToStaff($assignee, $form->getComments(), false);
    }

    function assignToStaff($staff, $note, $alert=true) {

        if(!is_object($staff) && !($staff = Staff::lookup($staff)))
            return false;

        if (!$staff->isAvailable())
            return false;

        $this->staff_id = $staff->getId();

        if (!$this->save())
            return false;

        $this->onAssignment($staff, $note, $alert);

        global $thisstaff;
        $data = array();
        if ($thisstaff && $staff->getId() == $thisstaff->getId())
            $data['claim'] = true;
        else
            $data['staff'] = $staff->getId();

        $this->logEvent('assigned', $data);

        return true;
    }


    function assign(AssignmentForm $form, &$errors, $alert=true) {
        global $thisstaff;

        $evd = array();
        $audit = array();
        $assignee = $form->getAssignee();
        if ($assignee instanceof Staff) {
            $dept = $this->getDept();
            if ($this->getStaffId() == $assignee->getId()) {
                $errors['assignee'] = sprintf(__('%s already assigned to %s'),
                        __('Task'),
                        __('the agent')
                        );
            } elseif(!$assignee->isAvailable()) {
                $errors['assignee'] = __('Agent is unavailable for assignment');
              } elseif (!$dept->canAssign($assignee)) {
                $errors['err'] = __('Permission denied');
            }
            else {
                $this->staff_id = $assignee->getId();
                if ($dept->autoAssignTeam() && ($teams = $assignee->getTeams()) && count($teams) == 1 && $this->team_id != $teams[0]) {
                    $teamForm = AssignmentForm::instantiate(array('assignee' => array(sprintf('t%s', $teams[0]))));
                    $this->assign($teamForm, $errors);
                }
                if ($thisstaff && $thisstaff->getId() == $assignee->getId())
                    $evd['claim'] = true;
                else
                    $evd['staff'] = array($assignee->getId(), $assignee->getName());
                    $audit = array('staff' => $assignee->getName()->name);
            }
        } elseif ($assignee instanceof Team) {
            if ($this->getTeamId() == $assignee->getId()) {
                $errors['assignee'] = sprintf(__('%s already assigned to %s'),
                        __('Task'),
                        __('the team')
                        );
            } else {
                $this->team_id = $assignee->getId();
                $evd = array('team' => $assignee->getId());
                $audit = array('team' => $assignee->getName());
            }
        } else {
            $errors['assignee'] = __('Unknown assignee');
        }

        if ($errors || !$this->save(true))
            return false;

        $this->logEvent('assigned', $evd);

        $type = array('type' => 'assigned');
        $type += $audit;
        Signal::send('object.edited', $this, $type);

        $this->onAssignment($assignee,
                $form->getField('comments')->getClean(),
                $alert);

        return true;
    }

    function onAssignment($assignee, $comments='', $alert=true) {
        global $thisstaff, $cfg;

        if (!is_object($assignee))
            return false;

        $assigner = $thisstaff ?: __('SYSTEM (Auto Assignment)');

        //Assignment completed... post internal note.
        $note = null;
        if ($comments) {

            $title = sprintf(__('Task assigned to %s'),
                    (string) $assignee);

            $errors = array();
            $note = $this->postNote(
                    array('note' => $comments, 'title' => $title),
                    $errors,
                    $assigner,
                    false);
        }

        // Send alerts out if enabled.
        if (!$alert || !$cfg->alertONTaskAssignment())
            return false;

        if (!($dept=$this->getDept())
            || !($tpl = $dept->getTemplate())
            || !($email = $dept->getAlertEmail())
        ) {
            return true;
        }

        // Recipients
        $recipients = array();
        if ($assignee instanceof Staff) {
            if ($cfg->alertStaffONTaskAssignment())
                $recipients[] = $assignee;
        } elseif (($assignee instanceof Team) && $assignee->alertsEnabled()) {
            if (($cfg->alertTeamMembersONTaskAssignment() || $assignee->alertAll()) && ($members=$assignee->getMembersForAlerts()))
                $recipients = array_merge($recipients, $members);
            elseif ($cfg->alertTeamLeadONTaskAssignment() && ($lead=$assignee->getTeamLead()))
                $recipients[] = $lead;
        }

        if ($recipients
            && ($msg=$tpl->getTaskAssignmentAlertMsgTemplate())) {

            $msg = $this->replaceVars($msg->asArray(),
                array('comments' => $comments,
                      'assignee' => $assignee,
                      'assigner' => $assigner
                )
            );
            // Send the alerts.
            $sentlist = array();
            $options = $note instanceof ThreadEntry
                ? array('thread' => $note)
                : array();

            foreach ($recipients as $k => $staff) {
                if (!is_object($staff)
                    || !$staff->isAvailable()
                    || in_array($staff->getEmail(), $sentlist)) {
                    continue;
                }

                $alert = $this->replaceVars($msg, array('recipient' => $staff));
                $email->sendAlert($staff, $alert['subj'], $alert['body'], null, $options);
                $sentlist[] = $staff->getEmail();
            }
        }

        return true;
    }

    function transfer(TransferForm $form, &$errors, $alert=true) {
        global $thisstaff, $cfg;

        $cdept = $this->getDept();
        $dept = $form->getDept();
        if (!$dept || !($dept instanceof Dept))
            $errors['dept'] = __('Department selection is required');
        elseif ($dept->getid() == $this->getDeptId())
            $errors['dept'] = __('Task already in the department');
        else
            $this->dept_id = $dept->getId();

        $this->reopen(); 
        $this->unassign();
        if ($errors || !$this->save(true))
            return false;

        // Log transfer event
        $this->logEvent('transferred', array('dept' => $dept->getName()));

        // Post internal note if any
        $note = $form->getField('comments')->getClean();
        if ($note) {
            $title = sprintf(__('%1$s transferred from %2$s to %3$s'),
                    __('Task'),
                   $cdept->getName(),
                    $dept->getName());

            $_errors = array();
            $note = $this->postNote(
                    array('note' => $note, 'title' => $title),
                    $_errors, $thisstaff, false);
        }

        // Send alerts if requested && enabled.
        if (!$alert || !$cfg->alertONTaskTransfer())
            return true;

        if (($email = $dept->getAlertEmail())
             && ($tpl = $dept->getTemplate())
             && ($msg=$tpl->getTaskTransferAlertMsgTemplate())) {

            $msg = $this->replaceVars($msg->asArray(),
                array('comments' => $note, 'staff' => $thisstaff));
            // Recipients
            $recipients = array();
            // Assigned staff or team... if any
            if ($this->isAssigned() && $cfg->alertAssignedONTaskTransfer()) {
                if($this->getStaffId())
                    $recipients[] = $this->getStaff();
                elseif ($this->getTeamId()
                    && ($team=$this->getTeam())
                    && ($members=$team->getMembersForAlerts())
                ) {
                    $recipients = array_merge($recipients, $members);
                }
            } elseif ($cfg->alertDeptMembersONTaskTransfer() && !$this->isAssigned()) {
                // Only alerts dept members if the task is NOT assigned.
                foreach ($dept->getMembersForAlerts() as $M)
                    $recipients[] = $M;
            }

            // Always alert dept manager??
            if ($cfg->alertDeptManagerONTaskTransfer()
                && ($manager=$dept->getManager())) {
                $recipients[] = $manager;
            }

            $sentlist = $options = array();
            if ($note instanceof ThreadEntry) {
                $options += array('thread'=>$note);
            }

            foreach ($recipients as $k=>$staff) {
                if (!is_object($staff)
                    || !$staff->isAvailable()
                    || in_array($staff->getEmail(), $sentlist)
                ) {
                    continue;
                }
                $alert = $this->replaceVars($msg, array('recipient' => $staff));
                $email->sendAlert($staff, $alert['subj'], $alert['body'], null, $options);
                $sentlist[] = $staff->getEmail();
            }
        }

        return true;
    }

    function postNote($vars, &$errors, $poster='', $alert=true) {
        global $cfg, $thisstaff;

        $vars['staffId'] = 0;
        $vars['poster'] = 'SYSTEM';
        if ($poster && is_object($poster)) {
            $vars['staffId'] = $poster->getId();
            $vars['poster'] = $poster->getName();
        } elseif ($poster) { //string
            $vars['poster'] = $poster;
        }

        if ($this->isClosing(newState: $vars['task:status'])) {
            // Claim if unassigned, in my dept, teams and closing
            if (!$this->getStaffId() &&
                $thisstaff && $this->getDeptId() == $thisstaff->getDeptId() &&
                $thisstaff->isTeamMember(teamId: $this->getTeamId())) {
                $cform = $this->getClaimForm();
                if (!$this->claim(form: $cform, errors: $errors));
                    return null;
            }

            if (Misc::isCommentEmpty(comment: $vars['note'])) {
                $this->setStatus(status: $vars['task:status'], errors: $errors);
                return;
            }
        }

        if (!($note=$this->getThread()->addNote($vars, $errors)))
            return null;

        $assignee = $this->getStaff();

        if (isset($vars['task:status']))
            $this->setStatus($vars['task:status']);

        $this->onActivity(array(
            'activity' => $note->getActivity(),
            'threadentry' => $note,
            'assignee' => $assignee
        ), $alert);

        $type = array('type' => 'note');
        Signal::send('object.created', $this, $type);

        return $note;
    }

    /* public */
    function postReply($vars, &$errors, $alert = true) {
        global $thisstaff, $cfg;

        $wasClosed = $this->isClosed();

        if (!$vars['poster'] && $thisstaff)
            $vars['poster'] = $thisstaff;

        if (!$vars['staffId'] && $thisstaff)
            $vars['staffId'] = $thisstaff->getId();

        if (!$vars['ip_address'] && $_SERVER['REMOTE_ADDR'])
            $vars['ip_address'] = $_SERVER['REMOTE_ADDR'];

        if ($this->isClosing(newState: $vars['task:status'])) {
            if (!$this->getStaffId() &&
                $thisstaff && $thisstaff->getDeptId() == $this->getDeptId() &&
                $thisstaff->isTeamMember(teamId: $this->getTeamId())) {
                $cform = $this->getClaimForm();
                if (!$this->claim(form: $cform, errors: $errors))
                    return null;
            }

            if (Misc::isCommentEmpty(comment: $vars['response'])) {
                $this->setStatus(status: $vars['task:status'], errors: $errors);
                return true;
            }
        }

        if (!($response = $this->getThread()->addResponse($vars, $errors)))
            return null;

        $assignee = $this->getStaff();

        if (isset($vars['task:status']))
            $this->setStatus($vars['task:status']);

        $activity = $vars['activity'] ?: $response->getActivity();
        $agentRecipients = $this->onActivity([
            'activity' => $activity,
            'threadentry' => $response,
            'assignee' => $assignee,
        ]);

        if ($wasClosed) {
            $this->reopen();
        }

        $this->lastrespondent = $response->staff;
        $this->save();

        // Send alert to collaborators
        if ($alert && $vars['emailcollab']) {
            if (!($dept = $this->getDept())
                || !($tpl = $dept->getTemplate())
                || !($email = $dept->getAlertEmail())
                || !($msg = $tpl->getTaskCopyAlertMsgTemplate())
            ) {
                return $response;
            }

            $assigner = $thisstaff ?: __('SYSTEM (Auto Notification)');
            $comments = $vars['response'] ?? '';

            $msg = $this->replaceVars($msg->asArray(), [
                'comments'  => $comments,
                'assigner'  => $assigner,
                'task'      => $this,
            ]);

            $recipients = [];
            foreach ($this->getThread()->getRecipients() as $recipient) {
                $contact = $recipient->getContact();
                if ($contact instanceof Collaborator) {
                    $user = $contact->getUser();
                    if ($user && ($emailObj = $user->getDefaultEmail())) {
                        if (method_exists($recipient, 'setEmail')) {
                            $recipient->setEmail($emailObj);
                        }
                        $recipient->user = $user;
                        $recipients[] = $recipient;
                    }
                }
            }

            $sentlist = [];
            $options = ['thread' => $response];

            foreach ($recipients as $recipient) {
                $user = $recipient->user;
                $emailObj = $user->getDefaultEmail();

                if (!$emailObj || in_array((string)$emailObj, $sentlist)) continue;

                $correo = (string)$emailObj;
                $alert = $this->replaceVars($msg, ['recipient' => $user]);


                $ok = $email->send($correo, $alert['subj'], $alert['body']);
            }

        }
        return $response;
    }


    function pdfExport($options=array()) {
        global $thisstaff;

        require_once(INCLUDE_DIR.'class.pdf.php');
        if (!isset($options['psize'])) {
            if ($_SESSION['PAPER_SIZE'])
                $psize = $_SESSION['PAPER_SIZE'];
            elseif (!$thisstaff || !($psize = $thisstaff->getDefaultPaperSize()))
                $psize = 'Letter';

            $options['psize'] = $psize;
        }

        $pdf = new Task2PDF($this, $options);
        $name = 'Task-'.$this->getNumber().'.pdf';
        Http::download($name, 'application/pdf', $pdf->output($name, 'S'));
        //Remember what the user selected - for autoselect on the next print.
        $_SESSION['PAPER_SIZE'] = $options['psize'];
        exit;
    }

    /* util routines */
    function replaceVars($input, $vars = array()) {
        global $ost;

        return $ost->replaceTemplateVariables($input,
                array_merge($vars, array('task' => $this)));
    }

    function asVar() {
       return $this->getNumber();
    }

    function getVar($tag) {
        global $cfg;

        if ($tag && is_callable(array($this, 'get'.ucfirst($tag))))
            return call_user_func(array($this, 'get'.ucfirst($tag)));

        switch(mb_strtolower($tag)) {
        case 'phone':
        case 'phone_number':
            return $this->getPhoneNumber();
        case 'ticket_link':
            if ($ticket = $this->ticket) {
                return sprintf('%s/scp/tickets.php?id=%d#tasks',
                    $cfg->getBaseUrl(), $ticket->getId());
            }
        case 'staff_link':
            return sprintf('%s/scp/tasks.php?id=%d', $cfg->getBaseUrl(), $this->getId());
        case 'create_date':
            return new FormattedDate($this->getCreateDate());
         case 'due_date':
            if ($due = $this->getDueDate())
                return new FormattedDate($due);
            break;
        case 'close_date':
            if ($this->isClosed())
                return new FormattedDate($this->getCloseDate());
            break;
        case 'last_update':
            return new FormattedDate($this->updated);
        case 'description':
            return Format::display($this->getThread()->getVar('original') ?: '');
        case 'subject':
            return Format::htmlchars($this->getTitle());
        default:
            if (isset($this->_answers[$tag]))
                // The answer object is retrieved here which will
                // automatically invoke the toString() method when the
                // answer is coerced into text
                return $this->_answers[$tag];
        }
        return false;
    }

    static function getVarScope() {
        $base = array(
            'assigned' => __('Assigned Agent / Team'),
            'close_date' => array(
                'class' => 'FormattedDate', 'desc' => __('Date Closed'),
            ),
            'create_date' => array(
                'class' => 'FormattedDate', 'desc' => __('Date Created'),
            ),
            'dept' => array(
                'class' => 'Dept', 'desc' => __('Department'),
            ),
            'description' => __('Description'),
            'due_date' => array(
                'class' => 'FormattedDate', 'desc' => __('Due Date'),
            ),
            'number' => __('Task Number'),
            'recipients' => array(
                'class' => 'UserList', 'desc' => __('List of all recipient names'),
            ),
            'status' => __('Status'),
            'staff' => array(
                'class' => 'Staff', 'desc' => __('Assigned/closing agent'),
            ),
            'subject' => 'Subject',
            'team' => array(
                'class' => 'Team', 'desc' => __('Assigned/closing team'),
            ),
            'thread' => array(
                'class' => 'TaskThread', 'desc' => __('Task Thread'),
            ),
            'staff_link' => __('Link to view the task'),
            'ticket_link' => __('Link to view the task inside the ticket'),
            'last_update' => array(
                'class' => 'FormattedDate', 'desc' => __('Time of last update'),
            ),
        );

        $extra = VariableReplacer::compileFormScope(TaskForm::getInstance());
        return $base + $extra;
    }

    function onActivity($vars, $alert=true) {
        global $cfg, $thisstaff;

        if (!$alert // Check if alert is enabled
            || !$cfg->alertONTaskActivity()
            || !($dept=$this->getDept())
            || !($email=$cfg->getAlertEmail())
            || !($tpl = $dept->getTemplate())
            || !($msg=$tpl->getTaskActivityAlertMsgTemplate())
        ) {
            return;
        }

        // Alert recipients
        $recipients = array();
        //Last respondent.
        if ($cfg->alertLastRespondentONTaskActivity())
            $recipients[] = $this->getLastRespondent();

        // Assigned staff / team
        if ($cfg->alertAssignedONTaskActivity()) {
            if (isset($vars['assignee'])
                    && $vars['assignee'] instanceof Staff)
                 $recipients[] = $vars['assignee'];
            elseif ($this->isOpen() && ($assignee = $this->getStaff()))
                $recipients[] = $assignee;

            if (($team = $this->getTeam()) && ($team->alertAll() || !$this->getStaff() || ($this->getStaff() && !$team->hasMember($this->getStaff()))))
                $recipients = array_merge($recipients, $team->getMembersForAlerts());
        }

        // Dept manager
        if ($cfg->alertDeptManagerONTaskActivity() && $dept && $dept->getManagerId())
            $recipients[] = $dept->getManager();

        $options = array();
        $staffId = $thisstaff ? $thisstaff->getId() : 0;
        if ($vars['threadentry'] && $vars['threadentry'] instanceof ThreadEntry) {
            $options = array('thread' => $vars['threadentry']);

            // Activity details
            if (!$vars['message'])
                $vars['message'] = $vars['threadentry'];

            // Staff doing the activity
            $staffId = $vars['threadentry']->getStaffId() ?: $staffId;
        }

        $msg = $this->replaceVars($msg->asArray(),
                array(
                    'note' => $vars['threadentry'], // For compatibility
                    'activity' => $vars['activity'],
                    'message' => $vars['message']));

        $isClosed = $this->isClosed();
        $sentlist=array();
        foreach ($recipients as $k=>$staff) {
            if (!is_object($staff)
                // Don't bother vacationing staff.
                || !$staff->isAvailable()
                // No need to alert the poster!
                || $staffId == $staff->getId()
                // No duplicates.
                || isset($sentlist[$staff->getEmail()])
                // Make sure staff has access to task
                || ($isClosed && !$this->checkStaffPerm($staff))
            ) {
                continue;
            }
            $alert = $this->replaceVars($msg, array('recipient' => $staff));
            $email->sendAlert($staff, $alert['subj'], $alert['body'], null, $options);
            $sentlist[$staff->getEmail()] = 1;
        }

        return $sentlist;
    }

    function addCollaborator($user, $vars, &$errors, $event=true) {
        if ($c = $this->getThread()->addCollaborator($user, $vars, $errors, $event)) {
            $this->collaborators = null;
            $this->recipients = null;
        }
        return $c;
    }

    /*
     * Notify collaborators on response or new message
     *
     */
    function notifyCollaborators($entry, $vars = array()) {
        global $cfg, $thisstaff;

        if (!$entry instanceof ThreadEntry
            || !($dept = $this->getDept())
            || !($tpl = $dept->getTemplate())
            || !($msgTpl = $tpl->getTaskAssignmentAlertMsgTemplate())
            || !($email = $dept->getAlertEmail())
        ) {
            return;
        }

        // Crear nota interna si hay comentario
        $assigner = $thisstaff ?: __('SYSTEM (Auto Notification)');
        $note = null;
        if (!empty($vars['comments'])) {
            $title = __('Task shared with collaborators');
            $errors = array();
            $note = $this->postNote([
                'note' => $vars['comments'],
                'title' => $title
            ], $errors, $assigner, false);
        }

        // Reunir destinatarios: solo usuarios copiados (con FLAG_CC)
        $recipients = [];
        foreach ($this->getThread()->getRecipients() as $user) {
            // Solo usuarios (no agentes) y sin repetir
            if ($user instanceof User && !in_array($user, $recipients)) {
                $recipients[] = $user;
            }
        }


        if (empty($recipients)) return;

        // Preparar plantilla
        $msgTpl = $this->replaceVars($msgTpl->asArray(), array_merge($vars, [
            'assigner' => $assigner,
            'assignee' => __('Collaborator'),
            'message'  => (string) $entry,
        ]));

        $options = $note instanceof ThreadEntry ? ['thread' => $note] : ['thread' => $entry];
        $sentlist = [];

        foreach ($recipients as $recipient) {
            if (!$recipient || in_array($recipient->getEmail(), $sentlist))
                continue;

            $personalized = $this->replaceVars($msgTpl, ['recipient' => $recipient]);
            $email->sendAlert($recipient, $personalized['subj'], $personalized['body'], null, $options);
            $sentlist[] = $recipient->getEmail();
        }
    }


    function update($forms, $vars, &$errors) {
        global $thisstaff;

        if (!$forms || !$this->checkStaffPerm($thisstaff, Task::PERM_EDIT))
            return false;


        foreach ($forms as $form) {
            $form->setSource($vars);
            if (!$form->isValid(function($f) {
                return $f->isVisibleToStaff() && $f->isEditableToStaff();
            }, array('mode'=>'edit'))) {
                $errors = array_merge($errors, $form->errors());
            }
        }

        if ($errors)
            return false;

        // Update dynamic meta-data
        $changes = array();
        foreach ($forms as $f) {
            $changes += $f->getChanges();
            $f->save();
        }


        if ($vars['note']) {
            $_errors = array();
            $this->postNote(array(
                        'note' => $vars['note'],
                        'title' => _S('Task Updated'),
                        ),
                    $_errors,
                    $thisstaff);
        }

        $this->updated = SqlFunction::NOW();

        if ($changes)
            $this->logEvent('edited', array('fields' => $changes));

        Signal::send('model.updated', $this);
        return $this->save();
    }

    function updateField($form, &$errors) {
        global $thisstaff, $cfg;

        if (!($field = $form->getField('field')))
            return null;

        if (!($changes = $field->getChanges()))
            $errors['field'] = sprintf(__('%s is already assigned this value'),
                    __($field->getLabel()));
        else {
            if ($field->answer) {
                if (!$field->isEditableToStaff())
                    $errors['field'] = sprintf(__('%s can not be edited'),
                            __($field->getLabel()));
                elseif (!$field->save(true))
                    $errors['field'] =  __('Unable to update field');
                $changes['fields'] = array($field->getId() => $changes);
            } else {
                $val =  $field->getClean();
                $fid = $field->get('name');

                // Convert duedate to DB timezone.
                if ($fid == 'duedate') {
                    if (empty($val))
                        $val = null;
                    elseif ($dt = Format::parseDateTime($val)) {
                      // Make sure the due date is valid
                      if (Misc::user2gmtime($val) <= Misc::user2gmtime())
                          $errors['field']=__('Due date must be in the future');
                      else {
                          $dt->setTimezone(new DateTimeZone($cfg->getDbTimezone()));
                          $val = $dt->format('Y-m-d H:i:s');
                      }
                   }
                } elseif (is_object($val))
                    $val = $val->getId();

                $changes = array();
                $this->{$fid} = $val;
                foreach ($this->dirty as $F=>$old) {
                    switch ($F) {
                    case 'sla_id':
                    case 'topic_id':
                    case 'user_id':
                    case 'source':
                        $changes[$F] = array($old, $this->{$F});
                    }
                }

                if (!$errors && !$this->save())
                    $errors['field'] =  __('Unable to update field');
            }
        }

        if ($errors)
            return false;

        // Record the changes
        $this->logEvent('edited', $changes);

        // Log comments (if any)
        if (($comments = $form->getField('comments')->getClean())) {
            $title = sprintf(__('%s updated'), __($field->getLabel()));
            $_errors = array();
            $this->postNote(
                    array('note' => $comments, 'title' => $title),
                    $_errors, $thisstaff, false);
        }

        $this->updated = SqlFunction::NOW();

        $this->save();

        Signal::send('model.updated', $this);

        return true;
    }

    /* static routines */
    static function lookupIdByNumber($number) {

        if (($task = self::lookup(array('number' => $number))))
            return $task->getId();

    }

    static function isNumberUnique($number) {
        return !self::lookupIdByNumber($number);
    }

    static function create($vars=false) {
        global $thisstaff, $cfg;

        if (!is_array($vars)
                || !$thisstaff
                || !$thisstaff->hasPerm(Task::PERM_CREATE, false))
            return null;

        $task = new static(array(
            'flags' => self::ISOPEN,
            'object_id' => $vars['object_id'],
            'object_type' => $vars['object_type'],
            'number' => $cfg->getNewTaskNumber(),
            'created' => new SqlFunction('NOW'),
            'updated' => new SqlFunction('NOW'),
        ));

        if ($vars['internal_formdata']['dept_id'])
            $task->dept_id = $vars['internal_formdata']['dept_id'];

        if ($vars['internal_formdata']['duedate']) {
            $time = new DateTime($vars['internal_formdata']['duedate']);
            $time->setTime(23, 59, 59);
	        $task->duedate = date('Y-m-d G:i:s', $time->getTimestamp());
        }

        if (!$task->save(true))
            return false;

        // Add dynamic data
        $task->addDynamicData($vars['default_formdata']);

        // Create a thread + message.
        $thread = TaskThread::create($task);
        $desc = $thread->addDescription($vars);
        // Set the ORIGINAL_MESSAGE Flag if Description is added
        if ($desc) {
            $desc->setFlag(ThreadEntry::FLAG_ORIGINAL_MESSAGE);
            $desc->save();
        }

        $userId = $thisstaff->getUserIdStaff();
        if ($userId)
            $thread->addCollaboratorTask($userId);

        $task->logEvent('created', null, $thisstaff);

        // Get role for the dept
        $role = $thisstaff->getRole($task->getDept());
        // Assignment
        $assignee = $vars['internal_formdata']['assignee'] ?: $vars['team_formdata']['team'];
        if ($assignee) {
                // skip assignment if the user doesn't have perm.
            // $role->hasPerm(Task::PERM_ASSIGN))
            $_errors = array();
            $assigneeId = sprintf('%s%d',
                    ($assignee  instanceof Staff) ? 's' : 't',
                    $assignee->getId());

            $form = AssignmentForm::instantiate(array('assignee' => $assigneeId));

            $task->assign($form, $_errors);
        }

        $task->onNewTask();

        Signal::send('task.created', $task);

        return $task;
    }

    function onNewTask($vars=array()) {
        global $cfg, $thisstaff;

        if (!$cfg->alertONNewTask() // Check if alert is enabled
            || !($dept=$this->getDept())
            || ($dept->isGroupMembershipEnabled() == Dept::ALERTS_DISABLED)
            || !($email=$cfg->getAlertEmail())
            || !($tpl = $dept->getTemplate())
            || !($msg=$tpl->getNewTaskAlertMsgTemplate())
        ) {
            return;
        }

        // Check if Dept recipients is Admin Only
        $adminOnly = ($dept->isGroupMembershipEnabled() == Dept::ALERTS_ADMIN_ONLY);

        // Alert recipients
        $recipients = array();

        // Department Manager
        if ($cfg->alertDeptManagerONNewTask()
            && $dept->getManagerId()
            && !$adminOnly)
            $recipients[] = $dept->getManager();

        // Department Members
        if ($cfg->alertDeptMembersONNewTask() && !$adminOnly)
            foreach ($dept->getMembersForAlerts() as $M)
                $recipients[] = $M;

        $options = array();
        $staffId = $thisstaff ? $thisstaff->getId() : 0;

        $msg = $this->replaceVars($msg->asArray(), $vars);

        $sentlist=array();
        foreach ($recipients as $k=>$staff) {
            if (!is_object($staff)
                // Don't bother vacationing staff.
                || !$staff->isAvailable()
                // No need to alert the poster!
                || $staffId == $staff->getId()
                // No duplicates.
                || isset($sentlist[$staff->getEmail()])
                // Make sure staff has access to task
                || !$this->checkStaffPerm($staff)
            ) {
                continue;
            }
            $alert = $this->replaceVars($msg, array('recipient' => $staff));
            $email->sendAlert($staff, $alert['subj'], $alert['body'], null, $options);
            $sentlist[$staff->getEmail()] = 1;
        }

        // Alert Admin ONLY if not already a staff
        if ($cfg->alertAdminONNewTask()
                && !in_array($cfg->getAdminEmail(), $sentlist)) {
            $alert = $this->replaceVars($msg, array('recipient' => __('Admin')));
            $email->sendAlert($cfg->getAdminEmail(), $alert['subj'],
                    $alert['body'], null, $options);
        }
    }

    function delete($comments='') {
        global $ost, $thisstaff;

        $thread = $this->getThread();

        if (!parent::delete())
            return false;

        $thread->delete();
        $this->logEvent('deleted');
        Draft::deleteForNamespace('task.%.' . $this->getId());

        foreach (DynamicFormEntry::forObject($this->getId(), ObjectModel::OBJECT_TYPE_TASK) as $form)
            $form->delete();

        // Log delete
        $log = sprintf(__('Task #%1$s deleted by %2$s'),
                $this->getNumber(),
                $thisstaff ? $thisstaff->getName() : __('SYSTEM'));

        if ($comments)
            $log .= sprintf('<hr>%s', $comments);

        $ost->logDebug(
                sprintf( __('Task #%s deleted'), $this->getNumber()),
                $log);

        return true;

    }

    function isClosing($newState): bool {
        return $this->isOpen() && $newState == 'closed';
    }

    static function __loadDefaultForm() {

        require_once INCLUDE_DIR.'class.i18n.php';

        $i18n = new Internationalization();
        $tpl = $i18n->getTemplate('form.yaml');
        foreach ($tpl->getData() as $f) {
            if ($f['type'] == ObjectModel::OBJECT_TYPE_TASK) {
                $form = DynamicForm::create($f);
                $form->save();
                break;
            }
        }
    }

    /* Quick staff's stats */
    static function getStaffStats($staff) {
        global $cfg;

        /* Unknown or invalid staff */
        if (!$staff
                || (!is_object($staff) && !($staff=Staff::lookup($staff)))
                || !$staff->isStaff())
            return null;

        $where = array('(task.staff_id='.db_input($staff->getId())
                    .sprintf(' AND task.flags & %d != 0 ', TaskModel::ISOPEN)
                    .') ');
        $where2 = '';

        if(($teams=$staff->getTeams()))
            $where[] = ' ( task.team_id IN('.implode(',', db_input(array_filter($teams)))
                        .') AND '
                        .sprintf('task.flags & %d != 0 ', TaskModel::ISOPEN)
                        .')';

        if(!$staff->showAssignedOnly() && ($depts=$staff->getDepts())) //Staff with limited access just see Assigned tasks.
            $where[] = 'task.dept_id IN('.implode(',', db_input($depts)).') ';

        $where = implode(' OR ', $where);
        if ($where) $where = 'AND ( '.$where.' ) ';

        $sql =  'SELECT \'open\', count(task.id ) AS tasks '
                .'FROM ' . TASK_TABLE . ' task '
                . sprintf(' WHERE task.flags & %d != 0 ', TaskModel::ISOPEN)
                . $where . $where2

                .'UNION SELECT \'overdue\', count( task.id ) AS tasks '
                .'FROM ' . TASK_TABLE . ' task '
                . sprintf(' WHERE task.flags & %d != 0 ', TaskModel::ISOPEN)
                . sprintf(' AND task.flags & %d != 0 ', TaskModel::ISOVERDUE)
                . $where

                .'UNION SELECT \'assigned\', count( task.id ) AS tasks '
                .'FROM ' . TASK_TABLE . ' task '
                . sprintf(' WHERE task.flags & %d != 0 ', TaskModel::ISOPEN)
                .'AND task.staff_id = ' . db_input($staff->getId()) . ' '
                . $where

                .'UNION SELECT \'closed\', count( task.id ) AS tasks '
                .'FROM ' . TASK_TABLE . ' task '
                . sprintf(' WHERE task.flags & %d = 0 ', TaskModel::ISOPEN)
                . $where;

        $res = db_query($sql);
        $stats = array();
        while ($row = db_fetch_row($res))
            $stats[$row[0]] = $row[1];

        return $stats;
    }

    static function getAgentActions($agent, $options=array()) {
        if (!$agent)
            return;

        require STAFFINC_DIR.'templates/tasks-actions.tmpl.php';
    }
}


class TaskCData extends VerySimpleModel {
    static $meta = array(
        'pk' => array('task_id'),
        'table' => TASK_CDATA_TABLE,
        'joins' => array(
            'task' => array(
                'constraint' => array('task_id' => 'TaskModel.task_id'),
            ),
        ),
    );
}


class TaskForm extends DynamicForm {
    static $instance;
    static $defaultForm;
    static $internalForm;
    static $teamForm;

    static $forms;

    static $cdata = array(
            'table' => TASK_CDATA_TABLE,
            'object_id' => 'task_id',
            'object_type' => ObjectModel::OBJECT_TYPE_TASK,
        );

    static function objects() {
        $os = parent::objects();
        return $os->filter(array('type'=>ObjectModel::OBJECT_TYPE_TASK));
    }

    static function getDefaultForm() {
        if (!isset(static::$defaultForm)) {
            if (($o = static::objects()) && $o[0])
                static::$defaultForm = $o[0];
        }

        return static::$defaultForm;
    }

    static function getInstance($object_id=0, $new=false) {
        if ($new || !isset(static::$instance))
            static::$instance = static::getDefaultForm()->instanciate();

        static::$instance->object_type = ObjectModel::OBJECT_TYPE_TASK;

        if ($object_id)
            static::$instance->object_id = $object_id;

        return static::$instance;
    }

    static function getInternalForm($source=null, $options=array()) {
        if (!isset(static::$internalForm))
            static::$internalForm = new TaskInternalForm($source, $options);

        return static::$internalForm;
    }

    static function getTeamForm($deptId, $source=null, $options=array()) {
        if (!isset(static::$teamForm))
            static::$teamForm = new TaskTeamForm($deptId, $source, $options);

        return static::$teamForm;
    }
}

class TaskInternalForm
extends AbstractForm {
    static $layout = 'GridFormLayout';

    function buildFields() {

        $fields = array(
                'dept_id' => new DepartmentField(array(
                    'id'=>1,
                    'label' => 'Dependencia',
                    'required' => true,
                    'layout' => new GridFluidCell(6),
                    )),
                'duedate'  =>  new DatetimeField(array(
                    'id' => 3,
                    'label' => __('Due Date'),
                    'required' => true,
                    'configuration' => array(
                        'min' => Misc::bogTimeStartToday(),
                        'time' => false,
                        'gmt' => false,
                        'future' => true,
                        'timezone' => 'America/Bogota',
                        ),
                    )),

            );

        $mode = @$this->options['mode'];
        if ($mode && $mode == 'edit') {
            unset($fields['dept_id']);
            unset($fields['assignee']);
        }

        return $fields;
    }
}

class TaskTeamForm
extends AbstractForm {
    static $layout = 'GridFormLayout';
    static $deptId;

    public function __construct($deptId, $source=null, $options=array()) {
        $this->deptId = $deptId;
        parent::__construct($source, $options);
    }

    function buildFields() {
        global $thisstaff;
        $fields = array(
            'team' => new AssigneeField(array(
                'id' => 4,
                'label' => 'Equipo (opcional). Si no está seguro de qué equipo seleccionar, favor ignore este campo. Tenga en cuenta que si no selecciona el equipo adecuado, generará retrasos en la solicitud',
                'required' => false,
                'layout' => new GridFluidCell(6),
                'configuration' => array(
                    'deptid' => $this->deptId,
                    'directRequest' => $this->deptId != $thisstaff->getDeptId(),
                    'target' => 'teams',
                ),
            )),

        );
        $mode = @$this->options['mode'];
        if ($mode && $mode == 'edit') {
            unset($fields['team']);
        }

        return $fields;
    }
}

// Task thread class
class TaskThread extends ObjectThread {

    function addDescription($vars, &$errors=array()) {

        $vars['threadId'] = $this->getId();
        if (!isset($vars['message']) && $vars['description'])
            $vars['message'] = $vars['description'];
        unset($vars['description']);
        return MessageThreadEntry::add($vars, $errors);
    }

    function addCollaboratorTask($user) {
        $err = array();
        $vars = array(
            'threadId' => $this->getId(),
            'userId' => $user);
        return Collaborator::add($vars, $err);
    }

    static function create($task=false) {
        assert($task !== false);

        $id = is_object($task) ? $task->getId() : $task;
        $thread = parent::create(array(
                    'object_id' => $id,
                    'object_type' => ObjectModel::OBJECT_TYPE_TASK
                    ));
        if ($thread->save())
            return $thread;
    }

}
?>

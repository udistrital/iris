<?php
/* Fixed expediente listing. Ticket queues are intentionally not used here. */
$tickets = Ticket::objects();
TicketForm::ensureDynamicDataView();

$queue_columns = array(
    'number' => array('width' => '10%', 'heading' => __('Number').'<br>&nbsp;'),
    'date' => array('width' => '11%', 'heading' => 'Fecha de<br>Creación', 'filter_type' => 'date'),
    'last_entry' => array('width' => '11%', 'heading' => 'Última<br>Actividad', 'filter_type' => 'date'),
    'subject' => array('width' => '20%', 'heading' => __('Subject').'<br>&nbsp;'),
    'dept' => array('width' => '14%', 'heading' => 'Dependencia<br>Asignada'),
    'assignee' => array('width' => '14%', 'heading' => __('Assigned To').'<br>&nbsp;'),
    'origin_dept' => array('width' => '16%', 'heading' => 'Dependencia<br>Creadora'),
);

$results = array(
    'assigned' => __('Expedientes asignados a mí'),
    'open_me' => __('Expedientes creados por mí'),
    'involved' => __('Expedientes en los que he participado'),
    'thread_me' => __('Expedientes gestionados por mí'),
    'closed' => __('Mis expedientes cerrados'),
    'transferred_me' => __('Expedientes transferidos por mí'),
    'cc' => __('Expedientes con copia a mí'),
    'dept' => __('Todos los expedientes de Mi Dependencia'),
    'assigned_dept' => __('Expedientes abiertos de Mi Dependencia'),
    'closed_dept' => __('Expedientes cerrados de Mi Dependencia'),
    'created_dep' => __('Expedientes creados por Mi Dependencia'),
    'requested_dep' => __('Expedientes solicitados por Mi Dependencia'),
    'transferred' => __('Expedientes transferidos por Mi Dependencia'),
    'assigned_mteams' => __('Expedientes asignados a mis equipos'),
    'closed_mteams' => __('Expedientes cerrados de mis equipos'),
    'created_pairs' => __('Expedientes creados por un miembro de mis equipos'),
    'unassigned_dept' => __('Expedientes sin revisar de Mi Dependencia'),
    'unassigned' => __('Expedientes sin asignar'),
    'overdue' => __('Expedientes vencidos'),
);
$results_type = $results[$queue_name] ?: __('Expedientes');
$state_filter_queues = array(
    'dept' => 'all',
    'open_me' => 'all',
    'involved' => 'open',
    'thread_me' => 'open',
    'transferred_me' => 'all',
    'cc' => 'open',
    'created_dep' => 'open',
    'transferred' => 'open',
);
$ticket_state = isset($state_filter_queues[$queue_name])
    ? $state_filter_queues[$queue_name] : null;
if ($ticket_state !== null
        && isset($_REQUEST['ticket_state'])
        && in_array($_REQUEST['ticket_state'], array('all', 'open', 'closed'), true))
    $ticket_state = $_REQUEST['ticket_state'];

$status = null;
if (!$user && !$org) {
    $status = iris_apply_ticket_queue_filters($tickets, $queue_name, $thisstaff);
    if (isset($state_filter_queues[$queue_name]))
        $status = $ticket_state === 'all' ? null : $ticket_state;
} else {
    $results_type = $user
        ? sprintf(__('Expedientes de %s'), $user->getName())
        : sprintf(__('Expedientes de %s'), $org->getName());
}

// Preserve user and organization entry points used elsewhere in the UI.
if ($user) {
    $tickets->filter(Q::any(array(
        'user_id' => $user->getId(),
        'thread__collaborators__user_id' => $user->getId(),
    )));
} elseif ($org) {
    $tickets->filter(array('user__org' => $org));
}

$filters = array();
if ($_REQUEST['number'])
    $filters[] = new Q(array('number__contains' => $_REQUEST['number']));
if ($_REQUEST['subject'])
    $filters[] = new Q(array('cdata__subject__contains' => $_REQUEST['subject']));
if ($_REQUEST['dept'])
    $filters[] = new Q(array('dept__name__contains' => $_REQUEST['dept']));
if ($_REQUEST['assignee']) {
    $filters[] = Q::any(array(
        'staff__firstname__contains' => $_REQUEST['assignee'],
        'staff__lastname__contains' => $_REQUEST['assignee'],
        'team__name__contains' => $_REQUEST['assignee'],
    ));
}
if ($_REQUEST['origin_dept']) {
    $originTickets = Ticket::objects()
        ->filter(array(
            'thread__events__event__name' => 'created',
            'thread__events__uid_type' => 'S',
            'thread__events__agent__dept__name__contains' => $_REQUEST['origin_dept'],
        ))
        ->values_flat('ticket_id');
    $filters[] = new Q(array('ticket_id__in' => $originTickets));
}

$createdRange = TaskModel::getDueDateSearchRange(
    $_REQUEST['start'] ?? '', $_REQUEST['end'] ?? '',
    $cfg->getTimezone($thisstaff), $cfg->getDbTimezone());
if ($createdRange['start'])
    $filters[] = new Q(array('created__gte' => $createdRange['start']));
if ($createdRange['end'])
    $filters[] = new Q(array('created__lt' => $createdRange['end']));

if ($_REQUEST['date']) {
    $dateRange = TaskModel::getDueDateSearchRange(
        $_REQUEST['date'], $_REQUEST['date'],
        $cfg->getTimezone($thisstaff), $cfg->getDbTimezone());
    if ($dateRange['start'] && $dateRange['end'])
        $filters[] = new Q(array('created__range' => array(
            "'".$dateRange['start']."'", "'".$dateRange['end']."'", true)));
}

$dueRange = TaskModel::getDueDateSearchRange(
    $_REQUEST['due_start'] ?? '', $_REQUEST['due_end'] ?? '',
    $cfg->getTimezone($thisstaff), $cfg->getDbTimezone());
if ($dueRange['start'])
    $filters[] = Q::any(array(
        'duedate__gte' => $dueRange['start'],
        'est_duedate__gte' => $dueRange['start'],
    ));
if ($dueRange['end'])
    $filters[] = Q::any(array(
        'duedate__lt' => $dueRange['end'],
        'est_duedate__lt' => $dueRange['end'],
    ));

if ($_REQUEST['last_entry']) {
    $activityRange = TaskModel::getDueDateSearchRange(
        $_REQUEST['last_entry'], $_REQUEST['last_entry'],
        $cfg->getTimezone($thisstaff), $cfg->getDbTimezone());
    if ($activityRange['start'] && $activityRange['end'])
        $filters[] = new Q(array('lastupdate__range' => array(
            "'".$activityRange['start']."'", "'".$activityRange['end']."'", true)));
}

if ($_REQUEST['text_search']) {
    $filters[] = Q::any(array(
        'cdata__subject__contains' => $_REQUEST['text_search'],
        'thread__entries__body__contains' => $_REQUEST['text_search'],
    ));
}
if ($status)
    $filters[] = new Q(array('status__state' => $status));
else
    $filters[] = new Q(array('status__state__in' => array('open', 'closed')));
if ($filters)
    $tickets->filter($filters);

// Keep parent expedientes and explicitly linked records, matching the former
// operational queue behavior.
$tickets->filter(Q::any(array(
    'ticket_pid' => null,
    'flags__hasbit' => Ticket::FLAG_LINKED,
)));
$tickets->filter($thisstaff->getTicketsVisibility());
$tickets->constrain(array('lock' => array(
    'lock__expire__gt' => SqlFunction::NOW(),
)));
$tickets->distinct('ticket_id');

$count = $tickets->count();
$tickets->annotate(array(
    'collab_count' => SqlAggregate::COUNT('thread__collaborators', true),
    'attachment_count' => SqlAggregate::COUNT(
        SqlCase::N()
            ->when(new SqlField('thread__entries__attachments__inline'), null)
            ->otherwise(new SqlField('thread__entries__attachments')),
        true
    ),
    'thread_count' => SqlAggregate::COUNT(
        SqlCase::N()
            ->when(new Q(array(
                'thread__entries__flags__hasbit' => ThreadEntry::FLAG_HIDDEN)), null)
            ->otherwise(new SqlField('thread__entries__id')),
        true
    ),
    'last_entry' => SqlAggregate::MAX('thread__entries__created'),
    'origin_dept_name' => iris_ticket_creator_department_expression(),
));
$tickets->values(
    'ticket_id', 'number', 'created', 'lastupdate', 'closed', 'isoverdue',
    'staff_id', 'staff__firstname', 'staff__lastname',
    'team_id', 'team__name', 'dept_id', 'dept__name',
    'status_id', 'status__name', 'status__state', 'cdata__subject',
    'source', 'lock__lock_id', 'lock__staff_id'
);

$sort_options = array('number', 'date', 'last_entry', 'subject', 'dept',
    'assignee', 'origin_dept');
$sort = in_array($_GET['sort'] ?? '', $sort_options, true)
    ? $_GET['sort'] : 'date';
$sort_dir = !empty($_GET['dir']) ? 1 : 0;
$orm_dir = $sort_dir ? QuerySet::ASC : QuerySet::DESC;
$queue_columns[$sort]['sort_dir'] = $sort_dir;
switch ($sort) {
    case 'number':
        $tickets->order_by($sort_dir ? 'ticket_id' : '-ticket_id');
        break;
    case 'last_entry':
        $tickets->order_by($sort_dir ? 'last_entry' : '-last_entry');
        break;
    case 'subject':
        $tickets->order_by('cdata__subject', $orm_dir);
        break;
    case 'dept':
        $tickets->order_by('dept__name', $orm_dir);
        break;
    case 'assignee':
        $tickets->order_by('staff__firstname', $orm_dir)
            ->order_by('staff__lastname', $orm_dir)
            ->order_by('team__name', $orm_dir);
        break;
    case 'origin_dept':
        $tickets->order_by(iris_ticket_creator_department_expression(), $orm_dir);
        break;
    default:
        $tickets->order_by($sort_dir ? 'created' : '-created');
}

// Store the complete filtered query before pagination for direct CSV export.
$_SESSION[':Q:tickets'] = clone $tickets;
$page = (isset($_GET['p']) && is_numeric($_GET['p'])) ? $_GET['p'] : 1;
$pageNav = new Pagenate($count, $page, PAGE_LIMIT);
$pageArgs = $_GET;
unset($pageArgs['p'], $pageArgs['_pjax'], $pageArgs['a']);
$pageNav->setURL('tickets.php', $pageArgs);
$tickets = $pageNav->paginate($tickets);

$refresh_url = 'tickets.php?'.Http::build_query(array('status' => $queue_name));
$subject_field = TicketForm::getInstance()->getField('subject');
?>
<div class="clear"></div>
<div style="margin-bottom:20px; padding-top:5px; overflow-x:auto">
  <div class="sticky bar opaque">
    <div class="content">
      <div class="pull-left flush-left">
        <h2><a href="<?php echo $refresh_url; ?>" title="<?php echo __('Refresh'); ?>">
          <i class="icon-refresh"></i> <?php echo $results_type; ?></a></h2>
      </div>
      <div class="pull-right flush-right">
        <?php if ($count) Ticket::agentActions($thisstaff, array('status' => $status)); ?>
      </div>
    </div>
  </div>
  <div class="clear"></div>

  <form action="tickets.php" method="GET" id="query">
    <input type="hidden" name="status" value="<?php echo Format::htmlchars($queue_name); ?>">
    <div id="basic_search" style="min-height:25px; margin:auto">
      <label><?php echo __('Creada desde'); ?>:
        <input type="date" class="input-medium search-query" name="start"
          value="<?php echo Format::htmlchars($_REQUEST['start'] ?? '', true); ?>">
      </label>
      <label><?php echo __('Creada hasta'); ?>:
        <input type="date" class="input-medium search-query" name="end"
          value="<?php echo Format::htmlchars($_REQUEST['end'] ?? '', true); ?>">
      </label>
      <label><?php echo __('Vence desde'); ?>:
        <input type="date" class="input-medium search-query" name="due_start"
          value="<?php echo Format::htmlchars($_REQUEST['due_start'] ?? '', true); ?>">
      </label>
      <label><?php echo __('Vence hasta'); ?>:
        <input type="date" class="input-medium search-query" name="due_end"
          value="<?php echo Format::htmlchars($_REQUEST['due_end'] ?? '', true); ?>">
      </label>
      <?php if (isset($state_filter_queues[$queue_name])) { ?>
      <label><?php echo __('Estado'); ?>:
        <select class="input-medium search-query" name="ticket_state">
          <option value="all" <?php echo $ticket_state === 'all' ? 'selected' : ''; ?>><?php echo __('Todos'); ?></option>
          <option value="open" <?php echo $ticket_state === 'open' ? 'selected' : ''; ?>><?php echo __('Abiertos'); ?></option>
          <option value="closed" <?php echo $ticket_state === 'closed' ? 'selected' : ''; ?>><?php echo __('Cerrados'); ?></option>
        </select>
      </label>
      <?php } ?>
      <label><?php echo __('Buscar en contenido'); ?>:
        <input type="text" class="input-medium search-query" name="text_search"
          placeholder="Asunto o contenido..."
          value="<?php echo Format::htmlchars($_REQUEST['text_search'] ?? '', true); ?>">
      </label>
      <button class="green button action-button muted" type="submit"><?php echo __('Buscar'); ?></button>
      <a class="button" href="<?php echo $refresh_url; ?>"><?php echo __('Limpiar'); ?></a>
    </div>
  </form>

  <form action="tickets.php" method="POST" name="tickets" id="tickets">
    <?php csrf_token(); ?>
    <input type="hidden" name="a" value="mass_process">
    <input type="hidden" name="do" id="action" value="">
    <input type="hidden" name="status" value="<?php echo Format::htmlchars($queue_name); ?>">
    <table class="list queue tickets" border="0" cellspacing="1" cellpadding="2" width="1120">
      <thead><tr>
        <?php $canManageTickets = $thisstaff->canManageTickets();
        if ($canManageTickets) { ?><th width="4%">&nbsp;</th><?php }
        unset($pageArgs['sort'], $pageArgs['dir']);
        $qstr = Http::build_query($pageArgs);
        foreach ($queue_columns as $key => $column) {
            $direction = !empty($column['sort_dir']) ? 0 : 1;
            $class = isset($column['sort_dir'])
                ? ($column['sort_dir'] ? 'asc' : 'desc') : '';
            echo sprintf(
                '<th width="%s"><a href="?sort=%s&amp;dir=%d&amp;%s" class="%s">%s</a>
                  <div class="attached input">
                    <input type="%s" class="column-search" name="%s" value="%s" form="query">
                    <button type="submit" class="attached button" form="query"><i class="icon-search"></i></button>
                  </div></th>',
                $column['width'], $key, $direction, $qstr, $class,
                $column['heading'], $column['filter_type'] ?? 'text', $key,
                Format::htmlchars($_REQUEST[$key] ?? '', true));
        } ?>
      </tr></thead>
      <tbody>
      <?php $total = 0;
      foreach ($tickets as $T) {
          $total++;
          $flag = null;
          if ($T['lock__lock_id'] && $T['lock__staff_id'] != $thisstaff->getId())
              $flag = 'locked';
          elseif ($T['isoverdue'])
              $flag = 'overdue';

          $assigned = '';
          if ($T['staff_id']) {
              $assigned = Format::truncate((string) new AgentsName(array(
                  'first' => $T['staff__firstname'],
                  'last' => $T['staff__lastname'])), 40);
          }
          if ($T['team_id']) {
              $team = Team::getLocalById($T['team_id'], 'name', $T['team__name']);
              $assigned .= ($assigned ? '<br>' : '').Format::truncate($team, 40);
          }
          $created = explode(' ', Format::datetime($T['created']), 2);
          $activity = explode(' ', Format::datetime($T['last_entry'] ?: $T['lastupdate']), 2);
          $statusLabel = TicketStatus::getLocalById(
              $T['status_id'], 'value', $T['status__name']);
          $subject = Format::truncate(
              $subject_field->display($subject_field->to_php($T['cdata__subject'])), 50);
      ?>
        <tr id="<?php echo $T['ticket_id']; ?>">
          <?php if ($canManageTickets) { ?>
          <td align="center" class="nohover"><input class="ckb" type="checkbox"
            name="tids[]" value="<?php echo $T['ticket_id']; ?>"></td>
          <?php } ?>
          <td nowrap>
            <div class="iris-task-number">
              <a class="preview" href="tickets.php?id=<?php echo $T['ticket_id']; ?>"
                data-preview="#tickets/<?php echo $T['ticket_id']; ?>/preview"><?php
                echo Format::htmlchars($T['number']); ?></a>
              <span class="iris-task-status <?php echo $T['status__state'] === 'open' ? 'is-open' : 'is-closed'; ?>"><?php
                echo Format::htmlchars($statusLabel); ?></span>
            </div>
          </td>
          <td nowrap><?php echo implode('<br>', $created); ?></td>
          <td nowrap><?php echo implode('<br>', $activity); ?></td>
          <td>
            <a class="preview<?php echo $flag ? ' Icon '.$flag.'Ticket' : ''; ?>"
              href="tickets.php?id=<?php echo $T['ticket_id']; ?>"
              data-preview="#tickets/<?php echo $T['ticket_id']; ?>/preview"><?php echo $subject; ?></a>
            <?php if ($T['thread_count'] > 1)
                echo '<small>('.(int) $T['thread_count'].')</small>&nbsp;<i class="icon-fixed-width icon-comments-alt"></i>&nbsp;';
            if ($T['collab_count'])
                echo '<i class="icon-fixed-width icon-group faded"></i>&nbsp;';
            if ($T['attachment_count'])
                echo '<i class="icon-fixed-width icon-paperclip"></i>&nbsp;'; ?>
          </td>
          <td><?php echo Format::htmlchars(Dept::getLocalById(
              $T['dept_id'], 'name', $T['dept__name'])); ?></td>
          <td><?php echo $assigned; ?></td>
          <td><?php echo Format::htmlchars($T['origin_dept_name'] ?: '—'); ?></td>
        </tr>
      <?php } ?>
      </tbody>
      <tfoot><tr><td colspan="<?php echo count($queue_columns) + ($canManageTickets ? 1 : 0); ?>">
        <?php if ($total && $canManageTickets) { ?>
          <?php echo __('Select'); ?>:&nbsp;
          <a id="selectAll" href="#ckb"><?php echo __('All'); ?></a>&nbsp;&nbsp;
          <a id="selectNone" href="#ckb"><?php echo __('None'); ?></a>&nbsp;&nbsp;
          <a id="selectToggle" href="#ckb"><?php echo __('Toggle'); ?></a>
        <?php } else {
          echo '<i>'.__('No hay expedientes que coincidan con los criterios.').'</i>';
        } ?>
      </td></tr></tfoot>
    </table>
    <?php if ($count > 0) { ?>
    <div>
      <span class="faded pull-right"><?php echo $pageNav->showing(); ?></span>
      <?php echo __('Page').':'.$pageNav->getPageLinks().'&nbsp;'; ?>
      <a class="export-csv no-pjax" href="tickets.php?a=export&amp;status=<?php
        echo urlencode($queue_name); ?>"><?php echo __('Export'); ?></a>
      <i class="help-tip icon-question-sign" href="#export"></i>
    </div>
    <?php } ?>
  </form>
</div>

<script type="text/javascript">
$(function() {
    $('[data-toggle=tooltip]').tooltip();
});
</script>

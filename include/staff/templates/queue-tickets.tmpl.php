<?php
/*************************************************************************
  queue-tickets.tmpl.php — versión limpia con filtros de estado y origen
*************************************************************************/

// ======================================================
// 1️⃣ VISIBILIDAD
// ======================================================
$ignoreVisibility = $queue->ignoreVisibilityConstraints($thisstaff);

if (!$ignoreVisibility ||
   ($ignoreVisibility && ($queue->isAQueue() || $queue->isASubQueue()))
) {
    $tickets->filter($thisstaff->getTicketsVisibility());
}

// ======================================================
// 2️⃣ FILTRO DE HIJOS
// ======================================================
if ($queue->isAQueue() || $queue->isASubQueue())
    $tickets->filter(Q::any(array('ticket_pid' => null, 'flags__hasbit' => TICKET::FLAG_LINKED)));

// ======================================================
// 3️⃣ VISTA CDATA
// ======================================================
TicketForm::ensureDynamicDataView();

// ======================================================
// 4️⃣ COLUMNAS
// ======================================================
$columns = $queue->getColumns();

// ======================================================
// 5️⃣ REFRESH URL
// ======================================================
$qsFilter = ['id'];
if (isset($_REQUEST['a']) && ($_REQUEST['a'] !== 'search'))
    $qsFilter[] = 'a';
$refresh_url = Http::refresh_url($qsFilter);

// ======================================================
// 6️⃣ SORT
// ======================================================
if (isset($_GET['sort']) && is_numeric($_GET['sort'])) {
    $sort = $_SESSION['sort'][$queue->getId()] = array(
        'col' => (int) $_GET['sort'],
        'dir' => (int) $_GET['dir'],
    );
} elseif (isset($_GET['sort'])
    && (strpos($_GET['sort'], 'qs-') === 0)
    && ($sort_id = substr($_GET['sort'], 3))
    && is_numeric($sort_id)
    && ($sort = QueueSort::lookup($sort_id))
) {
    $sort = $_SESSION['sort'][$queue->getId()] = array(
        'queuesort' => $sort,
        'dir' => (int) $_GET['dir'],
    );
} elseif (isset($_SESSION['sort'][$queue->getId()])) {
    $sort = $_SESSION['sort'][$queue->getId()];
} elseif ($queue_sort = $queue->getDefaultSort()) {
    $sort = $_SESSION['sort'][$queue->getId()] = array(
        'queuesort' => $queue_sort,
        'dir' => (int) $_GET['dir'] ?? 0,
    );
}

// ======================================================
// 7️⃣ APLICAR SORT
// ======================================================
$sorted = false;
foreach ($columns as $C) {
    if (isset($sort['col']) && $sort['col'] == $C->id) {
        $tickets = $C->applySort($tickets, $sort['dir']);
        $sorted = true;
    }
}

if (!$sorted) {
    if (isset($sort['queuesort'])) {
        $sort['queuesort']->applySort($tickets, $sort['dir']);
    } else {
        $tickets->order_by('-created');
    }
}

// ======================================================
// 8️⃣ APLICAR FILTROS MANUALES (status y source)
// ======================================================
if (!empty($_GET['status'])) {
    $status = strtolower($_GET['status']);
    switch ($status) {
        case 'open':
        case 'tramite':
            $tickets->filter(Q::any(['status__state' => 'open']));
            break;
        case 'resolved':
        case 'closed':
            $tickets->filter(Q::any(['status__state' => 'closed']));
            break;
        case 'archived':
            $tickets->filter(Q::any(['flags__hasbit' => Ticket::FLAG_ARCHIVED]));
            break;
        case 'deleted':
            $tickets->filter(Q::any(['flags__hasbit' => Ticket::FLAG_DELETED]));
            break;
    }
}

if (!empty($_GET['source'])) {
    $src = strtolower($_GET['source']);
    $negate = str_starts_with($src, '!');
    $srcValue = ltrim($src, '!');
    if ($negate) {
        $tickets->filter(Q::not(['source' => ucfirst($srcValue)]));
    } else {
        $tickets->filter(Q::any(['source' => ucfirst($srcValue)]));
    }
}

// ======================================================
// 9️⃣ PAGINACIÓN
// ======================================================
$page = (isset($_GET['p']) && is_numeric($_GET['p'])) ? $_GET['p'] : 1;
$pageNav = new Pagenate(PHP_INT_MAX, $page, PAGE_LIMIT);
$tickets = $pageNav->paginateSimple($tickets);

// ======================================================
// 🔟 QUERY PRINCIPAL
// ======================================================
$Q = $queue->getBasicQuery();

if ($Q->constraints) {
    if (count($Q->constraints) > 1) {
        foreach ($Q->constraints as $value) {
            if (!$value->constraints) $empty = true;
        }
    }
}

if (($Q->extra && isset($Q->extra['tables'])) || !$Q->constraints || $empty) {
    $skipCount = true;
    $count = '-';
}

$count = $count ?? $queue->getCount($thisstaff);
$pageNav->setTotal($count, true);
$pageNav->setURL('tickets.php', $_GET);
?>

<!-- SEARCH FORM START -->
<div id='basic_search'>
  <div class="pull-right" style="height:25px">
    <span class="valign-helper"></span>
    <?php
    require 'queue-quickfilter.tmpl.php';
    if ($queue->getSortOptions())
        require 'queue-sort.tmpl.php';
    ?>
  </div>
    <form action="tickets.php" method="get" onsubmit="javascript:
  $.pjax({
    url:$(this).attr('action') + '?' + $(this).serialize(),
    container:'#pjax-container',
    timeout: 2000
  });
return false;">
    <input type="hidden" name="a" value="search">
    <input type="hidden" name="search-type" value=""/>
    <div class="attached input">
      <input type="text" class="basic-search" data-url="ajax.php/tickets/lookup" name="query"
        autofocus size="30" value="<?php echo Format::htmlchars($_REQUEST['query'] ?? null, true); ?>"
        autocomplete="off" autocorrect="off" autocapitalize="off">
      <button type="submit" class="attached button"><i class="icon-search"></i>
      </button>
    </div>
    <a href="#" onclick="javascript:
        $.dialog('ajax.php/tickets/search', 201);"
        >[<?php echo __('advanced'); ?>]</a>
        <i class="help-tip icon-question-sign" href="#advanced"></i>
    </form>
</div>
<!-- SEARCH FORM END -->

<!-- FILTERS START -->
<div id="extra_filters" style="margin-top:10px; margin-bottom:15px;">
  <form action="tickets.php" method="get" class="inline" onsubmit="javascript:
    $.pjax({
      url:$(this).attr('action') + '?' + $(this).serialize(),
      container:'#pjax-container',
      timeout:2000
    });
    return false;">
    
    <input type="hidden" name="_pjax" value="#pjax-container" />
    <input type="hidden" name="queue" value="<?php echo $queue->getId(); ?>" />

    <label for="status_filter"><strong>Estado:</strong></label>
    <select name="status" id="status_filter" onchange="$(this).closest('form').submit();" style="margin-right:20px;">
      <option value="">Todos</option>
      <option value="open"      <?php echo ($_GET['status'] ?? '') == 'open' ? 'selected' : ''; ?>>Abierto</option>
      <option value="resolved"  <?php echo ($_GET['status'] ?? '') == 'resolved' ? 'selected' : ''; ?>>Resuelto</option>
      <option value="closed"    <?php echo ($_GET['status'] ?? '') == 'closed' ? 'selected' : ''; ?>>Cerrado</option>
      <option value="archived"  <?php echo ($_GET['status'] ?? '') == 'archived' ? 'selected' : ''; ?>>Archivado</option>
      <option value="deleted"   <?php echo ($_GET['status'] ?? '') == 'deleted' ? 'selected' : ''; ?>>Eliminado</option>
      <option value="tramite"   <?php echo ($_GET['status'] ?? '') == 'tramite' ? 'selected' : ''; ?>>En trámite</option>
    </select>

    <label for="source_filter"><strong>Origen:</strong></label>
    <select name="source" id="source_filter" onchange="$(this).closest('form').submit();">
      <option value="">Todos</option>
      <option value="web"  <?php echo ($_GET['source'] ?? '') == 'web' ? 'selected' : ''; ?>>Web</option>
      <option value="!web" <?php echo ($_GET['source'] ?? '') == '!web' ? 'selected' : ''; ?>>No Web</option>
    </select>
  </form>
</div>
<!-- FILTERS END -->

<div class="clear"></div>
<div style="margin-bottom:20px; padding-top:5px;">
    <div class="sticky bar opaque">
        <div class="content">
            <div class="pull-left flush-left">
                <h2><a href="<?php echo $refresh_url; ?>"
                    title="<?php echo __('Refresh'); ?>"><i class="icon-refresh"></i> <?php echo
                    $queue->getName(); ?></a>
                    <?php
                    if (($crit=$queue->getSupplementalCriteria()))
                        echo sprintf('<i class="icon-filter"
                                data-placement="bottom" data-toggle="tooltip"
                                title="%s"></i>&nbsp;',
                                Format::htmlchars($queue->describeCriteria($crit)));
                    ?>
                </h2>
            </div>
            <div class="configureQ">
                <i class="icon-cog"></i>
                <div class="noclick-dropdown anchor-left">
                    <ul>
                        <li>
                            <a class="no-pjax" href="#"
                              data-dialog="ajax.php/tickets/search/<?php echo
                              urlencode($queue->getId()); ?>"><i
                            class="icon-fixed-width icon-pencil"></i>
                            <?php echo __('Edit'); ?></a>
                        </li>
                        <li>
                            <a class="no-pjax" href="#"
                              data-dialog="ajax.php/tickets/search/create?pid=<?php
                              echo $queue->getId(); ?>"><i
                            class="icon-fixed-width icon-plus-sign"></i>
                            <?php echo __('Add Sub Queue'); ?></a>
                        </li>
<?php
if ($queue->id > 0 && $queue->isOwner($thisstaff)) { ?>
                        <li class="danger">
                            <a class="no-pjax confirm-action" href="#"
                                data-dialog="ajax.php/queue/<?php
                                echo $queue->id; ?>/delete"><i
                            class="icon-fixed-width icon-trash"></i>
                            <?php echo __('Delete'); ?></a>
                        </li>
<?php } ?>
                    </ul>
                </div>
            </div>

          <div class="pull-right flush-right">
            <?php
            if ($count) {
                Ticket::agentActions($thisstaff, array('status' => $status ?? null));
            }?>
            </div>
        </div>
    </div>
</div>
<div class="clear"></div>

<form action="?" method="POST" name='tickets' id="tickets">
<?php csrf_token(); ?>
 <input type="hidden" name="a" value="mass_process" >
 <input type="hidden" name="do" id="action" value="" >

<table class="list queue tickets" border="0" cellspacing="1" cellpadding="2" width="940">
  <thead>
    <tr>
<?php
$canManageTickets = $thisstaff->canManageTickets();
if ($canManageTickets) { ?>
        <th style="width:12px"></th>
<?php
}

foreach ($columns as $C) {
    $heading = Format::htmlchars($C->getLocalHeading());
    if ($C->isSortable()) {
        $args = $_GET;
        $dir = $sort['col'] != $C->id ?: ($sort['dir'] ? 'desc' : 'asc');
        $args['dir'] = $sort['col'] != $C->id ?: (int) !$sort['dir'];
        $args['sort'] = $C->id;
        $heading = sprintf('<a href="?%s" class="%s">%s</a>',
            Http::build_query($args), $dir, $heading);
    }
    echo sprintf('<th width="%s" data-id="%d">%s</th>',
        $C->getWidth(), $C->id, $heading);
}
?>
    </tr>
  </thead>
  <tbody>
<?php
foreach ($tickets as $T) {
    echo '<tr>';
    if ($canManageTickets) { ?>
        <td><input type="checkbox" class="ckb" name="tids[]"
            value="<?php echo $T['ticket_id']; ?>" /></td>
<?php
    }
    foreach ($columns as $C) {
        list($contents, $styles) = $C->render($T);
        if ($style = $styles ? 'style="'.$styles.'"' : '') {
            echo "<td $style><div $style>$contents</div></td>";
        } else {
            echo "<td>$contents</td>";
        }
    }
    echo '</tr>';
}
?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="<?php echo count($columns)+1; ?>">
        <?php if ($count && $canManageTickets) {
        echo __('Select');?>:&nbsp;
        <a id="selectAll" href="#ckb"><?php echo __('All');?></a>&nbsp;&nbsp;
        <a id="selectNone" href="#ckb"><?php echo __('None');?></a>&nbsp;&nbsp;
        <a id="selectToggle" href="#ckb"><?php echo __('Toggle');?></a>&nbsp;&nbsp;
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
if ($count > 0 || $skipCount) { ?>
  <div>
    <span class="faded pull-right"><?php echo $pageNav->showing(); ?></span>
    <?php echo __('Page').':'.$pageNav->getPageLinks().'&nbsp;'; ?>
    <a href="#tickets/export/<?php echo $queue->getId(); ?>"
       id="queue-export" class="no-pjax export">
       <?php echo __('Export'); ?></a>
    <i class="help-tip icon-question-sign" href="#export"></i>
  </div>
<?php } ?>
</form>

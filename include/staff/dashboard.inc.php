<?php
$report = new OverviewReport($_POST['start'], $_POST['period']);
?>
<script type="text/javascript" src="js/raphael-min.js"></script>
<script type="text/javascript" src="js/g.raphael.js"></script>
<script type="text/javascript" src="js/g.line-min.js"></script>
<script type="text/javascript" src="js/g.dot-min.js"></script>
<script type="text/javascript" src="js/dashboard.inc.js"></script>

<link rel="stylesheet" type="text/css" href="css/dashboard.css"/>

<form method="post" action="dashboard.php">
<div id="basic_search">
    <div style="min-height:25px;">
        <!--<p><?php //echo __('Select the starting time and period for the system activity graph');?></p>-->
            <?php echo csrf_token(); ?>
            <label>
                <?php echo __( 'Report timeframe'); ?>:
                <input type="text" class="dp input-medium search-query"
                    name="start" placeholder="<?php echo Format::htmlchars(__('Last month'));?>"
                    value="<?php
                        echo Format::htmlchars($report->getStartDate());
                    ?>" />
            </label>
            <label>
                <?php echo __('period');?>:
                <select name="period">
                    <?php foreach ($report::$end_choices as $val=>$desc) { ?>
                        <option value="<?php echo Format::htmlchars($val); ?>"<?php
                            if ($report->getPeriod() === $val)
                                echo ' selected="selected"';
                        ?>><?php echo Format::htmlchars(__($desc)); ?></option>
                    <?php } ?>
                </select>
            </label>
            <button class="green button action-button muted" type="submit">
                <?php echo __( 'Refresh');?>
            </button>
            <i class="help-tip icon-question-sign" href="#report_timeframe"></i>
    </div>
</div>
<div class="clear"></div>
<div style="margin-bottom:20px; padding-top:5px;">
    <div class="pull-left flush-left">
        <h2><?php echo __('Actividad de Tareas');
            ?>&nbsp;<i class="help-tip icon-question-sign" href="#ticket_activity"></i></h2>
    </div>
</div>
<div class="clear"></div>
<!-- Create a graph and fetch some data to create pretty dashboard -->
<div style="position:relative;">
    <div id="line-chart-here" style="height:300px"></div>
    <div style="position:absolute;right:0;top:0" id="line-chart-legend"></div>
</div>

<hr/>
<h2><?php echo __('Statistics'); ?>&nbsp;<i class="help-tip icon-question-sign" href="#statistics"></i></h2>
<p><?php echo __('Estadísticas de tareas organizadas por dependencias, equipos y agentes.');?></p>
<p><?php echo __('Creadas, Asignadas y Cerradas corresponden al periodo seleccionado. Abiertas es una fotografía del inventario actual de tareas abiertas.');?></p>
<p><?php echo __('Los agentes bloqueados no se muestran. Los equipos deshabilitados permanecen visibles y se identifican en su nombre para no ocultar su actividad o carga pendiente.');?></p>
<p><b><?php echo __('Range (inclusive):'); ?></b>
  <?php
  $range = OverviewReport::formatDateRangeForDisplay(
      $report->getDateRangeTimestamps(),
      $cfg->getTimezone(),
      'F j, Y',
      $cfg->getDbTimezone()
  );
  echo Format::htmlchars(implode(' - ', $range));
?>
<br><small><?php echo __('The range includes both boundaries. Up to today ends at the time the report is generated.'); ?></small>
</p>

<ul class="clean tabs">
<?php
$first = true;
$groups = $report->enumTabularGroups();
foreach ($groups as $g=>$desc) { ?>
    <li class="<?php echo $first ? 'active' : ''; ?>"><a href="#<?php echo Format::slugify($g); ?>"
        ><?php echo Format::htmlchars($desc); ?></a></li>
<?php
    $first = false;
} ?>
</ul>

<?php
$first = true;
$allPlotData = array();
foreach ($groups as $g=>$desc) {
    $data = $report->getTabularData($g);
    $tabId = Format::slugify($g);
    $allPlotData[$tabId] = $report->getPlotData($g, $data); ?>
    <div class="tab_content <?php echo (!$first) ? 'hidden' : ''; ?>" id="<?php echo $tabId; ?>">
    <table class="dashboard-stats table"><tbody><tr>
<?php
    foreach ($data['columns'] as $j=>$c) {
      ?>
        <th <?php if ($j === 0) echo 'width="30%" class="flush-left"'; ?>><?php echo Format::htmlchars($c);
        switch ($c) {
          case 'Created':
            ?>
              <i class="help-tip icon-question-sign" href="#opened"></i>
            <?php
            break;
          case 'Assigned':
            ?>
              <i class="help-tip icon-question-sign" href="#assigned"></i>
            <?php
            break;
            case 'Closed':
              ?>
                <i class="help-tip icon-question-sign" href="#closed"></i>
              <?php
              break;
            case 'Reopened':
              ?>
                <i class="help-tip icon-question-sign" href="#reopened"></i>
              <?php
              break;
            case 'Service Time':
              ?>
                <i class="help-tip icon-question-sign" href="#service_time"></i>
              <?php
              break;
        }
        ?></th>
<?php
    } ?>
    </tr></tbody>
    <tbody>
<?php
    foreach ($data['data'] as $i=>$row) {
        echo '<tr>';
        foreach ($row as $j=>$td) {
            if ($j === 0) { ?>
                <th class="flush-left"><?php echo Format::htmlchars($td); ?></th>
<?php       }
            else { ?>
                <td><?php echo Format::htmlchars($td);
                if ($td) { // TODO Add head map
                }
                echo '</td>';
            }
        }
        echo '</tr>';
    }
    $first = false; ?>
    </tbody></table>
    <div style="margin-top: 5px"><button type="submit" class="link button" name="export"
        value="<?php echo Format::htmlchars($g); ?>">
        <i class="icon-download"></i>
        <?php echo __('Export'); ?></a></div>
    </div>
<?php
}
?>
</form>
<script>
  window.irisDashboardPlotData = <?php echo JsonDataEncoder::encode($allPlotData); ?>;
</script>

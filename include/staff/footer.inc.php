</div>
</div>
<?php if (!isset($_SERVER['HTTP_X_PJAX'])) { ?>
    <div id="footer">
        <?php echo __('Copyright &copy;') ?> 2006-<?php echo date('Y'); ?>&nbsp;<?php
        echo Format::htmlchars((string) $ost->company ?: 'osTicket.com'); ?>&nbsp;<?php echo __('All Rights Reserved.'); ?>
    </div>
<?php
if(is_object($thisstaff) && $thisstaff->isStaff()) { ?>
    <div>
        <!-- Do not remove <img src="autocron.php" alt="" width="1" height="1" border="0" /> or your auto cron will cease to function -->
        <img src="<?php echo ROOT_PATH; ?>scp/autocron.php" alt="" width="1" height="1" border="0" />
        <!-- Do not remove <img src="autocron.php" alt="" width="1" height="1" border="0" /> or your auto cron will cease to function -->
    </div>
<?php
} ?>
</div>
<div id="overlay"></div>
<div id="loading">
    <i class="icon-spinner icon-spin icon-3x pull-left icon-light"></i>
    <h1><?php echo __('Loading ...');?></h1>
</div>
<div class="dialog draggable" style="display:none;" id="popup">
    <div id="popup-loading">
        <h1 style="margin-bottom: 20px;"><i class="icon-spinner icon-spin icon-large"></i>
        <?php echo __('Loading ...');?></h1>
    </div>
    <div class="body"></div>
</div>
<div style="display:none;" class="dialog" id="alert">
    <h3><i class="icon-warning-sign"></i> <span id="title"></span></h3>
    <a class="close" href=""><i class="icon-remove-circle"></i></a>
    <hr/>
    <div id="body" style="min-height: 20px;"></div>
    <hr style="margin-top:3em"/>
    <p class="full-width">
        <span class="buttons pull-right">
            <input type="button" value="<?php echo __('OK');?>" class="close ok">
        </span>
     </p>
    <div class="clear"></div>
</div>

<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery.pjax.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/bootstrap-typeahead.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-ui-1.13.2.custom.min.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-ui-timepicker-addon.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-ui-sliderAccess.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>scp/js/scp.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/filedrop.field.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/select2.min.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>scp/js/tips.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/redactor.min.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/redactor-osticket.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/redactor-plugins.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>scp/js/jquery.translatable.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>scp/js/jquery.dropdown.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>scp/js/bootstrap-tooltip.js"></script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>scp/js/jb.overflow.menu.js"></script>
<link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>scp/css/tooltip.css">
<script type="text/javascript">
    getConfig().resolve(<?php
        include INCLUDE_DIR . 'ajax.config.php';
        $api = new ConfigAjaxAPI();
        print $api->scp(false);
    ?>);
</script>
<?php
if ($thisstaff
        && ($lang = $thisstaff->getLanguage())
        && 0 !== strcasecmp($lang, 'en_US')) { ?>
    <script type="text/javascript" src="ajax.php/i18n/<?php
        echo $thisstaff->getLanguage(); ?>/js"></script>
<?php } ?>
<script>
  const sessionTimeout = 85 * 60 * 1000; 
  const warningTime = 80* 60 * 1000; 

  let warningTimer = setTimeout(() => {
    alert("⚠️ Tu sesión está por caducar. Haz clic en aceptar para continuar.");
  }, warningTime);

  let logoutTimer = setTimeout(() => {
    window.location.href = 'logout.php';
  }, sessionTimeout);

  const resetTimers = () => {
    clearTimeout(warningTimer);
    clearTimeout(logoutTimer);
    warningTimer = setTimeout(() => {
      alert("⚠️ Tu sesión está por caducar. Haz clic en aceptar para continuar.");
    }, warningTime);
    logoutTimer = setTimeout(() => {
      window.location.href = 'logout.php';
    }, sessionTimeout);
  };

  document.addEventListener("mousemove", resetTimers);
  document.addEventListener("keydown", resetTimers);
</script>
<?php
// Popup de resumen semanal: solo al iniciar sesión
if (is_object($thisstaff) && $thisstaff->isStaff()
        && !empty($_SESSION['_staff']['just_logged_in'])) {
    // Consumir el flag para que no se repita
    unset($_SESSION['_staff']['just_logged_in']);
?>
<script type="text/javascript">
$(function() {
    $.ajax({
        url: 'ajax.php/tasks/my-weekly-summary',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            if (!data || data.length === 0) return;

            var html = '<div style="max-height:400px;overflow-y:auto;">';
            html += '<p>Tareas creadas por ti en los últimos 7 días:</p>';
            html += '<table class="list" width="100%" border="0" cellspacing="1" cellpadding="2">';
            html += '<thead><tr>';
            html += '<th>Número</th>';
            html += '<th>Título</th>';
            html += '<th>Estado</th>';
            html += '<th>Última actualización</th>';
            html += '</tr></thead><tbody>';

            $.each(data, function(i, t) {
                html += '<tr>';
                html += '<td><a href="tasks.php?id=' + t.id + '">#' + t.number + '</a></td>';
                html += '<td>' + t.title + '</td>';
                html += '<td>' + t.status + '</td>';
                html += '<td>' + t.updated + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table></div>';

            var $dialog = $('.dialog#alert');
            if ($dialog.length) {
                $.toggleOverlay(true);
                $('#title', $dialog).html(
                    '<i class="icon-tasks"></i> Resumen semanal (' + data.length + ' tarea' + (data.length !== 1 ? 's' : '') + ')'
                );
                $('#body', $dialog).html(html);
                $dialog.css({'width': '650px', 'margin-left': '-325px'}).show();
                $dialog.find('input.ok.close').off('click.weekly').on('click.weekly', function() {
                    $dialog.hide().removeAttr('style');
                    $.toggleOverlay(false);
                });
            }
        },
        error: function() {
            // Fallo silencioso: no interrumpir la experiencia del agente
        }
    });
});
</script>
<?php } ?>
</body>
</html>
<?php } # endif X_PJAX ?>

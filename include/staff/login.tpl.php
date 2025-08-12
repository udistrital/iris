<?php
include_once(INCLUDE_DIR.'staff/login.header.php');
$info = ($_POST && $errors)?Format::htmlchars($_POST):array();


if ($thisstaff && $thisstaff->is2FAPending())
    $msg = "2FA Pending";

?>
<div id="brickwall"></div>
<div id="loginBox">
    <div id="blur">
        <div id="background"></div>
    </div>
    <h1 id="logo"><a href="index.php">
        <span class="valign-helper"></span>
        <img src="logo.php?login" alt="Iris :: <?php echo __('Sistema Integrado de Solicitudes y Trámites');?>" />
    </a></h1>
    <h3 id="login-message"><?php echo Format::htmlchars($msg); ?></h3>

<div>
    <!-- === MANUALES === -->
<p style="text-align:center; margin:8px 0 0;">
  <a href="#" id="open-manuals">Manuales</a>
</p>

<div id="manuals-overlay" style="display:none; position:fixed; inset:0; background: rgba(0,0,0,.6); z-index: 9999;">
  <div class="manuals-modal" style="position:relative; margin:8vh auto; background:#fff; width:90%; max-width:540px; border-radius:8px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,.3);">
    <button type="button" id="manuals-close" aria-label="Cerrar" style="position:absolute; top:8px; right:10px; border:0; background:transparent; font-size:24px; cursor:pointer; line-height:1;">×</button>
    <div style="padding:12px 16px; font-weight:bold; border-bottom:1px solid #e5e5e5;">Manuales disponibles</div>
    <ul id="manuals-list" style="list-style:none; margin:0; padding:0; max-height:60vh; overflow:auto;"></ul>
    <div style="padding:8px 12px; font-size:12px; border-top:1px solid #e5e5e5;">Los enlaces se abren en una pestaña nueva.</div>
  </div>
</div>

<script>
(function(){
  var manuals = [
    { name: 'Presentación del sistema', url: 'https://bit.ly/453Khom' },
    { name: 'Administrador de dependencia solicitante', url: 'https://bit.ly/3ZozxiX' },
    { name: 'Administrador dependencia destinataria', url: 'https://bit.ly/3ZiOClX' },
    { name: 'Agente dependencia destinataria', url: 'https://bit.ly/3ZiJoGZ' },
    { name: '1. Que es Iris y como funciona', url: 'https://youtu.be/gD435cI9w-s?si=crlFH9ViY55W29yW' },
    { name: '2. ¿Cómo crear tus tareas en IRIS?', url: 'https://www.youtube.com/watch?v=wVDBDdIeRR8' },
    { name: '3. Asignar tareas en IRIS desde el rol de Administrador', url: 'https://www.youtube.com/watch?v=TYaWn3V1gew&t=15s' },
    { name: '4. ¿Cómo consultar y dar respuesta a tus tareas en IRIS?', url: 'https://www.youtube.com/watch?v=H0WmBmFZwE8&t=1s' },
    { name: '5. ¿Cómo enviar y cerrar tus tareas en IRIS?', url: 'https://www.youtube.com/watch?v=DJKAGKjFoVk' },
    { name: '6. Exportar datos en IRIS.', url: 'https://youtu.be/-JcspMWd2h8' }
  ];

  var $ = function(id){ return document.getElementById(id); };
  var openBtn = $('open-manuals');
  var overlay = $('manuals-overlay');
  var listEl  = $('manuals-list');
  var closeBtn= $('manuals-close');

  function renderList() {
    listEl.innerHTML = '';
    manuals.forEach(function(m){
      var li = document.createElement('li');
      li.style.margin = '0';
      var a = document.createElement('a');
      a.href = m.url;
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
      a.textContent = m.name;
      a.style.display = 'block';
      a.style.padding = '10px 16px';
      a.style.textDecoration = 'none';
      a.style.borderBottom = '1px solid #f0f0f0';
      li.appendChild(a);
      listEl.appendChild(li);
    });
  }

  function open()  { overlay.style.display = 'block'; }
  function close() { overlay.style.display = 'none'; }

  if (openBtn) openBtn.addEventListener('click', function(e){ e.preventDefault(); open(); });
  if (closeBtn) closeBtn.addEventListener('click', close);
  overlay.addEventListener('click', function(e){ if (e.target === overlay) close(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') close(); });

  renderList();
})();
</script>
<!-- === /MANUALES === -->


</div>   

<div class="banner"><small><?php echo ($content) ? Format::display($content->getLocalBody()) : ''; ?></small></div>

    <div class="banner"><small><?php echo ($content) ? Format::display($content->getLocalBody()) : ''; ?></small></div>
    <div id="loading" style="display:none;" class="dialog">
        <h1><i class="icon-spinner icon-spin icon-large"></i>
        <?php echo __('Verifying');?></h1>
    </div>
    <form action="login.php" method="post" id="login" onsubmit="attemptLoginAjax(event)">
        <?php csrf_token();
        if ($thisstaff
                &&  $thisstaff->is2FAPending()
                && ($bk=$thisstaff->get2FABackend())
                && ($form=$bk->getInputForm($_POST))) {
            // Render 2FA input form
            include STAFFINC_DIR . 'templates/dynamic-form-simple.tmpl.php';
            ?>
            <fieldset style="padding-top:10px;">
            <input type="hidden" name="do" value="2fa">
            <button class="submit button pull-center" type="submit"
                name="submit"><i class="icon-signin"></i>
                <?php echo __('Verify'); ?>
            </button>
             </fieldset>
        <?php
        } else { ?>
            <!-- Botón para mostrar login tradicional -->
            <div style="margin-bottom: 1em; text-align: right;">
                <button type="button" class="button" onclick="document.getElementById('manual-login').style.display='block'" style="background: none; border: none; padding: 0;">
                    <img src="logo.php?login" alt="i" style="height: 32px; cursor: pointer;" />
                </button>
            </div>


            <!-- Login tradicional oculto por defecto -->
            <div id="manual-login" style="display: none;">
                <input type="hidden" name="do" value="scplogin">
                <fieldset>
                    <input type="text" name="userid" id="name" value="<?php
                        echo $info['userid'] ?? null; ?>" placeholder="<?php echo __('Email or Username'); ?>"
                        autofocus autocorrect="off" autocapitalize="off">
                    <input type="password" name="passwd" id="pass" maxlength="128" placeholder="<?php echo __('Password'); ?>" autocorrect="off" autocapitalize="off">
                    <h3 style="display:inline"><a id="reset-link" class="<?php
                        if (!$show_reset || !$cfg->allowPasswordReset()) echo 'hidden';
                        ?>" href="pwreset.php"><?php echo __('Forgot My Password'); ?></a></h3>
                    <button class="submit button pull-right" type="submit"
                        name="submit"><i class="icon-signin"></i>
                        <?php echo __('Log In'); ?>
                    </button>
                </fieldset>
            </div>

        <?php
        } ?>
    </form>
<?php
if (($bks=StaffAuthenticationBackend::getExternal())) { ?>
<div class="or">
    <hr/>
</div><?php
    foreach ($bks as $bk) { ?>
<div class="external-auth"><?php $bk->renderExternalLink(); ?></div><br/><?php
    }
} ?>

    <div id="company">
        <div class="content">
            <?php echo __('Copyright'); ?> &copy; <?php echo Format::htmlchars($ost->company) ?: date('Y'); ?>
        </div>
    </div>
</div>
<div id="poweredBy"><?php echo __('Powered by'); ?>
    <a href="http://www.osticket.com" target="_blank">
        <img alt="osTicket" src="images/osticket-grey.png" class="osticket-logo">
    </a>
</div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (undefined === window.getComputedStyle(document.documentElement).backgroundBlendMode) {
            document.getElementById('loginBox').style.backgroundColor = 'white';
        }
    });

    function attemptLoginAjax(e) {
        $('#loading').show();
        var objectifyForm = function(formArray) { //serialize data function
            var returnArray = {};
            for (var i = 0; i < formArray.length; i++) {
                returnArray[formArray[i]['name']] = formArray[i]['value'];
            }
            return returnArray;
        };
        if ($.fn.effect) {
            // For some reason, JQuery-UI shake does not considere an element's
            // padding when shaking. Looks like it might be fixed in 1.12.
            // Thanks, https://stackoverflow.com/a/22302374
            var oldEffect = $.fn.effect;
            $.fn.effect = function (effectName) {
                if (effectName === "shake") {
                    $('#loading').hide();
                    var old = $.effects.createWrapper;
                    $.effects.createWrapper = function (element) {
                        var result;
                        var oldCSS = $.fn.css;

                        $.fn.css = function (size) {
                            var _element = this;
                            var hasOwn = Object.prototype.hasOwnProperty;
                            return _element === element && hasOwn.call(size, "width") && hasOwn.call(size, "height") && _element || oldCSS.apply(this, arguments);
                        };

                        result = old.apply(this, arguments);

                        $.fn.css = oldCSS;
                        return result;
                    };
                }
                return oldEffect.apply(this, arguments);
            };
        }
        var form = $(e.target),
            data = objectifyForm(form.serializeArray())
        data.ajax = 1;
        $('button[type=submit]', form).attr('disabled', 'disabled');
        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: data,
            cache: false,
            success: function(json) {
                 $('button[type=submit]', form).removeAttr('disabled');
                if (!typeof(json) === 'object' || !json.status)
                    return;
                switch (json.status) {
                case 401:
                    if (json && json.redirect)
                        document.location.href = json.redirect;
                    if (json && json.message)
                        $('#login-message').text(json.message)
                    if (json && json.show_reset)
                        $('#reset-link').show()
                    if ($.fn.effect) {
                        $('#loginBox').effect('shake')
                    }
                    // Clear the password field
                    $('#pass').val('').focus();
                    break
                case 302:
                    if (json && json.redirect)
                        document.location.href = json.redirect;
                    break
                }
            },
        });
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        return false;
    }
    </script>
    <!--[if IE]>
    <style>
        #loginBox:after { background-color: white !important; }
    </style>
    <![endif]-->
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-ui-1.13.2.custom.min.js"></script>
</body>
</html>

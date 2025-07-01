<?php

if (!$info['title'])
    $info['title'] = __('New Task');

$namespace = 'task.add';
if ($ticket)
    $namespace = sprintf('ticket.%d.task', $ticket->getId());

?>
<div id="task-form">
<h3 class="drag-handle"><?php echo $info['title']; ?></h3>
<b><a class="close" href="#"><i class="icon-remove-circle"></i></a></b>
<hr/>
<?php

if ($info['error']) {
    echo sprintf('<p id="msg_error">%s</p>', $info['error']);
} elseif ($info['warning']) {
    echo sprintf('<p id="msg_warning">%s</p>', $info['warning']);
} elseif ($info['msg']) {
    echo sprintf('<p id="msg_notice">%s</p>', $info['msg']);
} ?>
<div id="new-task-form" style="display:block;">
<form method="post" class="org" action="<?php echo $info['action'] ?: '#tasks/add'; ?>">
    <?php
        $form = $form ?: TaskForm::getInstance();
        echo $form->getForm($vars)->asTable(' ', array('draft-namespace' => $namespace));

        $iform = $iform ?: TaskForm::getInternalForm();

        $deptField = $iform->getField('dept_id');
        $duedateField = $iform->getField('duedate');
    ?>

    <fieldset>
        <legend><?php echo __("Visibilidad y asignación de tarea"); ?></legend>

        <table class="form-table">
            <tr>
                <th><?php echo $deptField->getLabel(); ?> <span class="error">*</span></th>
                <td><?php echo $deptField->render(); ?></td>
            </tr>

            <!-- Alerta condicional para dept_id == RITA -->
            <tr id="dept-alert" style="display:none;">
                <td colspan="2">
                    <div class="alert" style="color: red; font-weight: bold;">
                        ⚠ Este departamento requiere autorización especial.
                    </div>
                </td>
            </tr>

            <tr>
                <th><?php echo $duedateField->getLabel(); ?> <span class="error">*</span></th>
                <td><?php echo $duedateField->render(); ?></td>
            </tr>
        </table>
    </fieldset>

    <div id="teamForm"></div>
    <script>
        var selectorDept = "<?php echo '#_' . $iform->getFieldNameByKey('dept_id'); ?>";
        var deptAlert = $('#dept-alert');

        $(selectorDept).on('change', function () {
            var val = $(this).val();
            deptAlert.toggle(val == 167); // muestra solo si es RITA

            $('#teamForm').empty();
            $.ajax(
                'ajax.php/tasks/dept_id/' + val,
                {
                    dataType: 'text',
                    success: function (response) {
                        $('#teamForm').html(response);
                    },
                });
        });

        // Mostrar si ya estaba seleccionado al cargar
        $(function () {
            if ($(selectorDept).val() == 122) {
                deptAlert.show();
            }
        });
    </script>

    <hr>
    <p class="full-width">
        <span class="buttons pull-left">
            <input type="reset" value="<?php echo __('Reset'); ?>">
            <input type="button" name="cancel" class="close" value="<?php echo __('Cancel'); ?>">
        </span>
        <span class="buttons pull-right">
            <input type="submit" value="<?php echo __('Create Task'); ?>">
        </span>
     </p>
</form>
</div>
<div class="clear"></div>
</div>

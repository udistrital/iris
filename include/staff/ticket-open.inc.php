<?php
if (!defined('OSTSCPINC') || !$thisstaff
        || !$thisstaff->hasPerm(Ticket::PERM_CREATE, false))
        die('Access Denied');

$info=array();
$info=Format::htmlchars(($errors && $_POST)?$_POST:$info, true);

if ($_SESSION[':form-data'] && !$_GET['tid'])
  unset($_SESSION[':form-data']);

//  Use thread entry to seed the ticket
if (!$user && $_GET['tid'] && ($entry = ThreadEntry::lookup($_GET['tid']))) {
    if ($entry->getThread()->getObjectType() == 'T')
      $oldTicketId = $entry->getThread()->getObjectId();
    if ($entry->getThread()->getObjectType() == 'A')
      $oldTaskId = $entry->getThread()->getObjectId();

    $_SESSION[':form-data']['message'] = Format::htmlchars($entry->getBody());
    $_SESSION[':form-data']['ticketId'] = $oldTicketId;
    $_SESSION[':form-data']['taskId'] = $oldTaskId;
    $_SESSION[':form-data']['eid'] = $entry->getId();
    $_SESSION[':form-data']['timestamp'] = $entry->getCreateDate();

    if ($entry->user_id)
       $user = User::lookup($entry->user_id);

     if (($m= TicketForm::getInstance()->getField('message'))) {
         $k = 'attach:'.$m->getId();
         unset($_SESSION[':form-data'][$k]);
        foreach ($entry->getAttachments() as $a) {
          if (!$a->inline && $a->file) {
            $_SESSION[':form-data'][$k][$a->file->getId()] = $a->getFilename();
            $_SESSION[':uploadedFiles'][$a->file->getId()] = $a->getFilename();
          }
        }
     }
}

if (!$info['topicId'])
    $info['topicId'] = $cfg->getDefaultTopicId();

$forms = array();
if ($info['topicId'] && ($topic=Topic::lookup($info['topicId']))) {
    foreach ($topic->getForms() as $F) {
        if (!$F->hasAnyVisibleFields())
            continue;
        if ($_POST) {
            $F = $F->instanciate();
            $F->isValidForClient();
        }
        $forms[] = $F;
    }
}

if ($_POST)
    $info['duedate'] = Format::date(strtotime($info['duedate']), false, false, 'UTC');
?>
<form action="tickets.php?a=open" method="post" class="save"  enctype="multipart/form-data">
 <?php csrf_token(); ?>
 <input type="hidden" name="do" value="create">
 <input type="hidden" name="a" value="open">
<div style="margin-bottom:20px; padding-top:5px;">
    <div class="pull-left flush-left">
        <h2><?php echo __('Open a New Ticket');?></h2>
    </div>
</div>
 <table class="form_table fixed" width="940" border="0" cellspacing="0" cellpadding="2">
    <thead>
    <!-- This looks empty - but beware, with fixed table layout, the user
         agent will usually only consult the cells in the first row to
         construct the column widths of the entire toable. Therefore, the
         first row needs to have two cells -->
        <tr><td style="padding:0;"></td><td style="padding:0;"></td></tr>
    </thead>
    <tbody>
        <tr>
            <th colspan="2">
                <em><strong><?php echo __('Con Copia'); ?></strong>: </em>
                <div class="error"><?php echo $errors['user']; ?></div>
            </th>
        </tr>
              <?php
              if ($user) { ?>
                      <input type="hidden" name="uid" id="uid" value="<?php echo $user->getId(); ?>" />
              <?php
            } else { //Fallback: Just ask for email and name
              ?>
              <tr id="userRow">
                <td width="120"><?php echo __('User'); ?>:</td>
                <td>
                  <span>
                    <select class="userSelection" name="name" id="user-name"
                    data-placeholder="<?php echo __('Select User'); ?>">
                  </select>
                </span>

                <a class="inline button" style="overflow:inherit" href="#"
                onclick="javascript:
                $.userLookup('ajax.php/users/lookup/form', function (user) {
                  var newUser = new Option(user.email + ' - ' + user.name, user.id, true, true);
                  return $(&quot;#user-name&quot;).append(newUser).trigger('change');
                });
                return false;
                "><i class="icon-plus"></i> <?php echo __('Add New'); ?></a>

                <span class="error">*</span>
                <br/><span class="error"><?php echo $errors['name']; ?></span>
              </td>
              <div>
                <input type="hidden" size=45 name="email" id="user-email" class="attached"
                placeholder="<?php echo __('User Email'); ?>"
                autocomplete="off" autocorrect="off" value="<?php echo $info['email']; ?>" />
              </div>
            </tr>
            <?php
          } ?>
          <tr id="ccRow">
            <td width="160"><?php echo __('CC'); ?>:</td>
            <td>
              <span>
                <select class="collabSelections" name="ccs[]" id="cc_users_open" multiple="multiple"
                ref="tags" data-placeholder="<?php echo __('CC'); ?>">
              </select>
            </span>

            <a class="inline button" style="overflow:inherit" href="#"
            onclick="javascript:
            $.userLookup('ajax.php/users/lookup/form', function (user) {
              var newUser = new Option(user.name, user.id, true, true);
              return $(&quot;#cc_users_open&quot;).append(newUser).trigger('change');
            });
            return false;
            "><i class="icon-plus"></i> <?php echo __('Add New'); ?></a>

            <br/><span class="error"><?php echo $errors['ccs']; ?></span>
          </td>
        </tr>
        <?php
        if ($cfg->notifyONNewStaffTicket()) {
         ?>
        <tr class="no_border">
          <input id="reply-to"  type="hidden" name="reply-to" value="none">
        </tr>
      <?php } ?>
    </tbody>
    <tbody>
        <tr>
            <th colspan="2">
                <em><strong><?php echo __('Ticket Information and Options');?></strong>:</em>
            </th>
        </tr>
        <tr>
          <input type="hidden" name="source" value="Email">
        </tr>
        <tr>
            <td width="160" class="required">
                <?php echo 'Dependencia'; ?>:
            </td>
            <td>
                <select name="topicId" onchange="javascript:
                        var data = $(':input[name]', '#dynamic-form').serialize();
                        $.ajax(
                          'ajax.php/form/help-topic/' + this.value,
                          {
                            data: data,
                            dataType: 'json',
                            success: function(json) {
                              $('#dynamic-form').empty().append(json.html);
                              $(document.head).append(json.media);
                            }
                          });">
                    <?php
                    if ($topics=$thisstaff->getTopicNames(false, false, Ticket::PERM_CREATE)) {
                        if (count($topics) == 1)
                            $selected = 'selected="selected"';
                        else { ?>
                        <option value="" selected >&mdash; <?php echo 'Seleccione la dependencia'; ?> &mdash;</option>
<?php                   }
                        foreach($topics as $id =>$name) {
                            echo sprintf('<option value="%d" %s %s>%s</option>',
                                $id, ($info['topicId']==$id)?'selected="selected"':'',
                                $selected, $name);
                        }
                        if (count($topics) == 1 && !$forms) {
                            if (($T = Topic::lookup($id)))
                                $forms =  $T->getForms();
                        }
                    }
                    ?>
                </select>
                &nbsp;<font class="error"><b>*</b>&nbsp;<?php echo $errors['topicId']; ?></font>
                <em><?php echo 'Recuerde poner su dependencia'; ?>&nbsp;(<?php echo $thisstaff->getDept(); ?>)</em>
            </td>
        </tr>
        <tr style="display:none;"> 
            <td width="160">
                <?php echo __('Department'); ?>:
            </td>
            <td>
                <select name="deptId">
                    <!-- <option value="" selected >&mdash; <?php #echo __('Select Department'); ?>&mdash;</option> -->
                    <?php
                      echo('<option value='.$thisstaff->getDept()->getId().' selected="selected">'.$thisstaff->getDept().'</option>');
                    ?>
                    <?php
                    if($depts=$thisstaff->getDepartmentNames(true)) {
                        foreach($depts as $id =>$name) {
                            if (!($role = $thisstaff->getRole($id))
                                || !$role->hasPerm(Ticket::PERM_CREATE)
                            ) {
                                // No access to create tickets in this dept
                                continue;
                            }
                            echo sprintf('<option value="%d" %s>%s</option>',
                                    $id, ($info['deptId']==$id)?'selected="selected"':'',$name);
                        }
                    }
                    ?>
                </select>
                &nbsp;<font class="error"><?php echo $errors['deptId']; ?></font>
            </td>
        </tr>

         <tr>
            <input type="hidden" name="slaId" value="-1">
         </tr>

         <tr>
          <input type="hidden" name="duedate" value="">
        </tr>

        <?php
        if($thisstaff->hasPerm(Ticket::PERM_ASSIGN, false)) { ?>
        <tr style="display:none;">
            <td width="160"><?php echo __('Assign To'); ?>:</td>
            <td>
                <select id="assignId" name="assignId">
                    <?php
                      echo '<option value="s'.$thisstaff->getId().'" selected="selected">'.$thisstaff.'</option>'; // asignación automatica de agente
                    ?>
                    <!-- <option value="0" selected="selected">&mdash; <?php #echo __('Select an Agent OR a Team');?> &mdash;</option> --><!-- ocultar el option para seleccion -->
                    <?php
                    $users = Staff::getStaffMembers(array(
                                'available' => true,
                                'staff' => $thisstaff,
                                ));
                    if ($users) {
                        echo '<OPTGROUP label="'.sprintf(__('Agents (%d)'), count($users)).'">';
                        foreach ($users as $id => $name) {
                            $k="s$id";
                            echo sprintf('<option value="%s" %s>%s</option>',
                                        $k, (($info['assignId']==$k) ? 'selected="selected"' : ''), $name);
                        }
                        echo '</OPTGROUP>';
                    }

                    if(($teams=Team::getActiveTeams())) {
                        echo '<OPTGROUP label="'.sprintf(__('Teams (%d)'), count($teams)).'">';
                        foreach($teams as $id => $name) {
                            $k="t$id";
                            echo sprintf('<option value="%s" %s>%s</option>',
                                        $k,(($info['assignId']==$k)?'selected="selected"':''),$name);
                        }
                        echo '</OPTGROUP>';
                    }
                    ?>
                </select>&nbsp;<span class='error'>&nbsp;<?php echo $errors['assignId']; ?></span>
            </td>
        </tr>
        <?php } ?>
        </tbody>
        <tbody id="dynamic-form">
        <?php
            $options = array('mode' => 'create');
            foreach ($forms as $form) {
                print $form->getForm($_SESSION[':form-data'])->getMedia();
                include(STAFFINC_DIR .  'templates/dynamic-form.tmpl.php');
            }
        ?>
        </tbody>
        <tbody>
          <input type="hidden" name="response" id="response" value="">
          <input type="hidden" name="statusId" id="statusId" value="1">
          <input type="hidden" name="signature" value="none">
          <input type="hidden" name="note" value="">
    </tbody>
</table>
<p style="text-align:center;">
    <input type="submit" name="submit" value="<?php echo _P('action-button', 'Open');?>">
    <input type="reset"  name="reset"  value="<?php echo __('Reset');?>">
    <input type="button" name="cancel" value="<?php echo __('Cancel');?>" onclick="javascript:
        $(this.form).find('textarea.richtext')
          .redactor('plugin.draft.deleteDraft');
        window.location.href='tickets.php'; " />
</p>
</form>
<script type="text/javascript">
$(function() {
    $('input#user-email').typeahead({
        source: function (typeahead, query) {
            $.ajax({
                url: "ajax.php/users?q="+query,
                dataType: 'json',
                success: function (data) {
                    typeahead.process(data);
                }
            });
        },
        onselect: function (obj) {
            $('#uid').val(obj.id);
            $('#user-name').val(obj.name);
            $('#user-email').val(obj.email);
        },
        property: "/bin/true"
    });

   <?php
    // Popup user lookup on the initial page load (not post) if we don't have a
    // user selected
    if (!$_POST && !$user) {?>
    setTimeout(function() {
      $.userLookup('ajax.php/users/lookup/form', function (user) {
        window.location.href = window.location.href+'&uid='+user.id;
      });
    }, 100);
    <?php
    } ?>
});

$(function() {
    $('a#editorg').click( function(e) {
        e.preventDefault();
        $('div#org-profile').hide();
        $('div#org-form').fadeIn();
        return false;
     });

    $(document).on('click', 'form.org input.cancel', function (e) {
        e.preventDefault();
        $('div#org-form').hide();
        $('div#org-profile').fadeIn();
        return false;
    });

    $('.userSelection').select2({
      width: '450px',
      minimumInputLength: 3,
      ajax: {
        url: "ajax.php/users/local",
        dataType: 'json',
        data: function (params) {
          return {
            q: params.term,
          };
        },
        processResults: function (data) {
          return {
            results: $.map(data, function (item) {
              return {
                text: item.email + ' - ' + item.name,
                slug: item.slug,
                email: item.email,
                id: item.id
              }
            })
          };
          $('#user-email').val(item.name);
        }
      }
    });

    $('.userSelection').on('select2:select', function (e) {
      var data = e.params.data;
      $('#user-email').val(data.email);
    });

    $('.userSelection').on("change", function (e) {
      var data = $('.userSelection').select2('data');
      var data = data[0].text;
      var email = data.substr(0,data.indexOf(' '));
      $('#user-email').val(data.substr(0,data.indexOf(' ')));
     });

    $('.collabSelections').select2({
      width: '450px',
      minimumInputLength: 3,
      ajax: {
        url: "ajax.php/users/local",
        dataType: 'json',
        data: function (params) {
          return {
            q: params.term,
          };
        },
        processResults: function (data) {
          return {
            results: $.map(data, function (item) {
              return {
                text: item.name,
                slug: item.slug,
                id: item.id
              }
            })
          };
        }
      }
    });

  });
</script>

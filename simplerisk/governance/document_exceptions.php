<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this
* file, You can obtain one at http://mozilla.org/MPL/2.0/. */

// Include required functions file
require_once(realpath(__DIR__ . '/../includes/functions.php'));
require_once(realpath(__DIR__ . '/../includes/authenticate.php'));
require_once(realpath(__DIR__ . '/../includes/display.php'));
require_once(realpath(__DIR__ . '/../includes/alerts.php'));
require_once(realpath(__DIR__ . '/../includes/permissions.php'));
require_once(realpath(__DIR__ . '/../includes/governance.php'));

// Include Zend Escaper for HTML Output Encoding
require_once(realpath(__DIR__ . '/../includes/Component_ZendEscaper/Escaper.php'));
$escaper = new Zend\Escaper\Escaper('utf-8');

// Add various security headers
add_security_headers();

if (!isset($_SESSION))
{
    // Session handler is database
    if (USE_DATABASE_FOR_SESSIONS == "true")
    {
        session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc');
    }

    // Start the session
    session_set_cookie_params(0, '/', '', isset($_SERVER["HTTPS"]), true);

    session_name('SimpleRisk');
    session_start();
}

// Include the language file
require_once(language_file());

// Check for session timeout or renegotiation
session_check();

// Check if access is authorized
if (!isset($_SESSION["access"]) || $_SESSION["access"] != "granted")
{
    set_unauthenticated_redirect();
    header("Location: ../index.php");
    exit(0);
}

// Include the CSRF-magic library
// Make sure it's called after the session is properly setup
include_csrf_magic();

// Enforce that the user has access to governance
enforce_permission_governance();

enforce_permission_exception('view');

if(isset($_POST['download_audit_log']))
{
    if(is_admin())
    {
        // If extra is activated, download audit logs
        if (import_export_extra())
        {
            require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));
            download_audit_logs(get_param('post', 'days', 7), 'exception', $escaper->escapeHtml($lang['ExeptionAuditTrailReport']));
        }else{
            set_alert(true, "bad", $escaper->escapeHtml($lang['YouCantDownloadBecauseImportExportExtraDisabled']));
            refresh();
        }
    }
    // If this is not admin user, disable download
    else
    {
        set_alert(true, "bad", $escaper->escapeHtml($lang['AdminPermissionRequired']));
        refresh();
    }
}

/*********************
 * FUNCTION: DISPLAY *
 *********************/
function display($display = "")
{
    global $lang;
    global $escaper;

    // If import/export extra is enabled and admin user, shows export audit log button
    if (import_export_extra() && is_admin())
    {
        // Include the Import-Export Extra
        require_once(realpath(__DIR__ . '/../extras/import-export/index.php'));

        display_audit_download_btn();
    }
}

?>

<!doctype html>
<html lang="<?php echo $escaper->escapehtml($_SESSION['lang']); ?>" xml:lang="<?php echo $escaper->escapeHtml($_SESSION['lang']); ?>">
    <head>
        <script src="../js/jquery.min.js"></script>
        <script src="../js/popper.min.js"></script>
        <script src="../js/jquery.easyui.min.js"></script>
        <script src="../js/jquery-ui.min.js"></script>
        <script src="../js/jquery.draggable.js"></script>
        <script src="../js/jquery.droppable.js"></script>
        <script src="../js/treegrid-dnd.js"></script>
        <script src="../js/bootstrap.min.js"></script>
        <script src="../js/bootstrap-multiselect.js"></script>
        <script src="../js/jquery.dataTables.js"></script>
        <script src="../js/pages/governance.js"></script>

        <title>SimpleRisk: Enterprise Risk Management Simplified</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
        <link rel="stylesheet" href="../css/easyui.css">
        <link rel="stylesheet" href="../css/bootstrap.css">
        <link rel="stylesheet" href="../css/bootstrap-responsive.css">
        <link rel="stylesheet" href="../css/jquery.dataTables.css">
        <link rel="stylesheet" href="../css/bootstrap-multiselect.css">
        <link rel="stylesheet" href="../css/prioritize.css">
        <link rel="stylesheet" href="../css/divshot-util.css">
        <link rel="stylesheet" href="../css/divshot-canvas.css">
        <link rel="stylesheet" href="../css/display.css">
        <link rel="stylesheet" href="../css/style.css">

        <link rel="stylesheet" href="../bower_components/font-awesome/css/font-awesome.min.css">
        <link rel="stylesheet" href="../css/theme.css">

        <?php
            setup_alert_requirements("..");
        ?>

        <style>
            .exception--edit, .exception--delete, .exception-batch--delete, .exception--approve {
                cursor: pointer;
            }

            .exception-name:before {
                margin-right: 5px;
                font: normal normal normal 14px/1 FontAwesome !important;
                content: "\f05a";
            }

            #exception--view {
                color: #ffffff;
            }

            #exception--view .modal-body h4 {
                text-decoration: underline;
            }

            .exception-data {
                padding-left: 10px;
            }
        </style>
        <script>
        
            function wireActionButtons(tab) {

                //Edit
                $("#"+ tab + "-exceptions .exception--edit").click(function(){
                    var exception_id = $(this).data("id");
                    var type = $(this).data("type");

                    $.ajax({
                        url: BASE_URL + '/api/exceptions/exception?id=' + exception_id,
                        type: 'GET',
                        success : function (res){
                            var data = res.data;

                            $("#exception-update-form [name=type]").val(type);

                            $("#exception-update-form [name=exception_id]").val(exception_id);
                            $("#exception-update-form [name=name]").val(data.name);
                            $("#exception-update-form [name=policy]").val(data.policy_document_id);
                            $("#exception-update-form [name=control]").val(data.control_framework_id);
                            $("#exception-update-form [name=owner]").val(data.owner);
                            if (Array.isArray(data.additional_stakeholders) && data.additional_stakeholders.length) {
                                $("#exception-update-form [name='additional_stakeholders[]']").multiselect('select', data.additional_stakeholders);
                            } else {
                                $("#exception-update-form [name='additional_stakeholders[]']").multiselect('deselectAll', false);
                            }

                            $("#exception-update-form [name='additional_stakeholders[]']").multiselect('updateButtonText');

                            $("#exception-update-form [name=creation_date]").val(data.creation_date);
                            $("#exception-update-form [name=review_frequency]").val(data.review_frequency);
                            $("#exception-update-form [name=next_review_date]").val(data.next_review_date);
                            $("#exception-update-form [name=approval_date]").val(data.approval_date);
                            $("#exception-update-form [name=approver]").val(data.approver);
                            $("#exception-update-form [name=approved_original]").prop('checked', data.approved);
                            $("#exception-update-form [name=description]").val(data.description);
                            $("#exception-update-form [name=justification]").val(data.justification);

                            refresh_type_selects_display($('#exception--update'));

                            $("#exception--update").modal();
                        }
                    });
                });

                //Info + Approve
                $("#"+ tab + "-exceptions span.exception-name > a, #"+ tab + "-exceptions a.exception--approve").click(function(){
                    event.preventDefault();
                    var exception_id = $(this).data("id");
                    var type = $(this).data("type");
                    var approval = $(this).hasClass("exception--approve");

                    $.ajax({
                        url: BASE_URL + '/api/exceptions/info',
                        data: {
                            id: exception_id,
                            type: type,
                            approval: approval
                        },
                        type: 'GET',
                        success : function (res){
                            var data = res.data;

                            $("#exception--view #name").text(data.name);
                            $("#exception--view #type").text(data.type_text);
                            if (data.type == 'policy') {
                                $("#exception--view #policy").text(data.policy_name);
                                $("#exception--view #policy").parent().show();
                                $("#exception--view #control").parent().hide();
                            } else {
                                $("#exception--view #control").text(data.control_name);
                                $("#exception--view #control").parent().show();
                                $("#exception--view #policy").parent().hide();
                            }

                            $("#exception--view #owner").text(data.owner);
                            $("#exception--view #additional_stakeholders").text(data.additional_stakeholders);
                            $("#exception--view #creation_date").text(data.creation_date);
                            $("#exception--view #review_frequency").text(data.review_frequency);
                            $("#exception--view #next_review_date").text(data.next_review_date);
                            $("#exception--view #approval_date").text(data.approval_date);
                            $("#exception--view #approver").text(data.approver);
                            $("#exception--view #description").html(data.description);
                            $("#exception--view #justification").html(data.justification);

                            if (approval) {
                                $(".approve-footer").show();
                                $(".info-footer").hide();
                                $("#exception-approve-form [name='exception_id']").val(exception_id);
                                $("#exception-approve-form [name='type']").val(type);
                            } else {
                                $(".approve-footer").hide();
                                $(".info-footer").show();
                                $("#exception-approve-form [name='type']").val("");
                            }

                            $("#exception--view").modal();
                        }
                    });
                });

                //Delete
                $("#"+ tab + "-exceptions a.exception--delete").click(function(){
                    $("#exception-delete-form [name='exception_id']").val($(this).data("id"));
                    $("#exception-delete-form [name='type']").val($(this).data("type"));
                    $("#exception-delete-form #approved").prop('checked', $(this).data("approved"));
                    $("#exception--delete").modal('show');
                });

                //Batch-delete
                $("#"+ tab + "-exceptions a.exception-batch--delete").click(function(){
                    $("#exception-batch-delete-form [name='parent_id']").val($(this).data("id"));
                    $("#exception-batch-delete-form [name='type']").val($(this).data("type"));
                    $("#exception-batch-delete-form [name='approved']").prop('checked', $(this).data("approved"));
                    $("#exception-batch-delete-form #all-approved").prop('checked', $(this).data("all-approved"));
                    $("#exception-batch--delete").modal('show');
                });
            }

            //Refresh audit logs if the log section is not collapsed
            // if it is, mark it for refresh on the next time it's opened
            function refreshAuditLogsIfOpen() {
                if ($(".collapsible--toggle > span > i.fa-caret-down").length)
                    refreshAuditLogs();
                else $(".collapsible--toggle > span > i").data('need-refresh', true);
            }

            function refreshAuditLogs() {
                $.ajax({
                    type: "GET",
                    url: BASE_URL + "/api/exceptions/audit_log",
                    data: {
                        days: $('.audit-trail select.audit-select-days').val()
                    },
                    async: true,
                    cache: false,
                    success: function(data){
                        var div = $("<div>");
                        $.each( data.data, function( key, value ) {
                            div.append($("<p>" + value.timestamp + " > " + value.message + "</p>" ));
                        });
                        $('.audit-trail>div.audit-contents').html(div.html());
                        $(".collapsible--toggle > span > i").data('need-refresh', false);
                    },
                    error: function(xhr,status,error){
                        if(!retryCSRF(xhr, this))
                        {
                            if(xhr.responseJSON && xhr.responseJSON.status_message){
                                showAlertsFromArray(xhr.responseJSON.status_message);
                            }
                        }
                    }
                });
            }

            function refresh_type_selects_display(root) {

                var policy = root.find('#policy');
                var control = root.find('#control');

                if ((policy.val() && policy.val() > 0) || (control.val() && control.val() > 0)) {
                    if ((policy.val() && policy.val() > 0)) {
                        policy.prop("disabled", false);
                        control.prop("disabled", true);
                    } else {
                        control.prop("disabled", false);
                        policy.prop("disabled", true);
                    }
                } else {
                    policy.prop("disabled", false);
                    control.prop("disabled", false);
                }
            }

            $(document).ready(function(){
                var $tabs = $( "#exceptions-tab-content" ).tabs({
                    activate: function(event, ui){
                        fixTreeGridCollapsableColumn();
                        $(".exception-table").treegrid('resize');
                    }
                });

                $("#exception-new-form").submit(function(event) {
                    event.preventDefault();

                    $.ajax({
                        type: "POST",
                        url: BASE_URL + "/api/exceptions/create",
                        data: new FormData($('#exception-new-form')[0]),
                        async: true,
                        cache: false,
                        contentType: false,
                        processData: false,
                        success: function(data){
                            if(data.status_message){
                                showAlertsFromArray(data.status_message);
                            }

                            $('#exception--add').modal('hide');
                            $('#exception-new-form')[0].reset();
                            $("#exception-new-form [name='additional_stakeholders[]']").multiselect('select', []);

                            if (!data.data.approved) {
                                var tree = $('#exception-table-unapproved');
                                tree.treegrid('options').animate = false;
                                tree.treegrid('reload');
                            } else {
                                var tree = $('#exception-table-' + data.data.type);
                                tree.treegrid('options').animate = false;
                                tree.treegrid('reload');
                            }

                            refreshAuditLogsIfOpen();
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this))
                            {
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        }
                    });
                    return false;
                });

                $("#exception-update-form").submit(function(event) {
                    event.preventDefault();

                    var old_type = $("#exception-update-form [name=type]").val();

                    $.ajax({
                        type: "POST",
                        url: BASE_URL + "/api/exceptions/update",
                        data: new FormData($('#exception-update-form')[0]),
                        async: true,
                        cache: false,
                        contentType: false,
                        processData: false,
                        success: function(data){
                            if(data.status_message){
                                showAlertsFromArray(data.status_message);
                            }

                            $('#exception--update').modal('hide');
                            $('#exception-update-form')[0].reset();
                            $("#exception-update-form [name='additional_stakeholders[]']").multiselect('select', []);
                            var tree = $('#exception-table-' + data.data.type);
                            tree.treegrid('options').animate = false;
                            tree.treegrid('reload');

                            // If exception_update_resets_approval we have to refresh after an update
                            if (<?php if (get_setting('exception_update_resets_approval')) echo "true || ";  ?>!data.data.approved) {
                                var tree = $('#exception-table-unapproved');
                                tree.treegrid('options').animate = false;
                                tree.treegrid('reload');
                            }

                            // If type is changed we have to refresh the tab of the old type as well
                            if (data.data.type !== old_type) {
                                var tree = $('#exception-table-' + old_type);
                                tree.treegrid('options').animate = false;
                                tree.treegrid('reload');
                            }

                            refreshAuditLogsIfOpen();
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this))
                            {
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        }
                    });
                    return false;
                });

                $("#exception-approve-form").submit(function(event) {
                    event.preventDefault();

                    $.ajax({
                        type: "POST",
                        url: BASE_URL + "/api/exceptions/approve",
                        data: new FormData($('#exception-approve-form')[0]),
                        async: true,
                        cache: false,
                        contentType: false,
                        processData: false,
                        success: function(data){
                            if(data.status_message){
                                showAlertsFromArray(data.status_message);
                            }

                            $('#exception--view').modal('hide');

                            var tree = $('#exception-table-' + $("#exception-approve-form [name='type']").val());
                            tree.treegrid('options').animate = false;
                            tree.treegrid('reload');

                            tree = $('#exception-table-unapproved');
                            tree.treegrid('options').animate = false;
                            tree.treegrid('reload');

                            refreshAuditLogsIfOpen();
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this))
                            {
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        }
                    });
                    return false;
                });

                $("#exception-delete-form").submit(function(event) {
                    event.preventDefault();

                    $.ajax({
                        type: "POST",
                        url: BASE_URL + "/api/exceptions/delete",
                        data: new FormData($('#exception-delete-form')[0]),
                        async: true,
                        cache: false,
                        contentType: false,
                        processData: false,
                        success: function(data){
                            if(data.status_message){
                                showAlertsFromArray(data.status_message);
                            }

                            $('#exception--delete').modal('hide');

                            var tree = $('#exception-table-' + $("#exception-delete-form [name='type']").val());
                            tree.treegrid('options').animate = false;
                            tree.treegrid('reload');

                            if (!$("#exception-delete-form #approved").prop('checked')) {
                                tree = $('#exception-table-unapproved');
                                tree.treegrid('options').animate = false;
                                tree.treegrid('reload');
                            }

                            refreshAuditLogsIfOpen();
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this))
                            {
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        }
                    });
                    return false;
                });
                
                $("#exception-batch-delete-form").submit(function(event) {
                    event.preventDefault();
                                       
                    $.ajax({
                        type: "POST",
                        url: BASE_URL + "/api/exceptions/batch-delete",
                        data: new FormData($('#exception-batch-delete-form')[0]),
                        async: true,
                        cache: false,
                        contentType: false,
                        processData: false,
                        success: function(data){
                            if(data.status_message){                   
                                showAlertsFromArray(data.status_message);
                            }

                            $('#exception-batch--delete').modal('hide');

                            var tree = $('#exception-table-' + $("#exception-batch-delete-form [name='type']").val());
                            tree.treegrid('options').animate = false;
                            tree.treegrid('reload');
                           
                            if (!$("#exception-batch-delete-form #all-approved").prop('checked')) {
                                tree = $('#exception-table-unapproved');
                                tree.treegrid('options').animate = false;
                                tree.treegrid('reload');
                            }

                            refreshAuditLogsIfOpen();
                        },
                        error: function(xhr,status,error){
                            if(!retryCSRF(xhr, this))
                            {
                                if(xhr.responseJSON && xhr.responseJSON.status_message){
                                    showAlertsFromArray(xhr.responseJSON.status_message);
                                }
                            }
                        }
                    });
                    return false;
                });

                $('#exception--add').find('#policy, #control').change(function() {refresh_type_selects_display($('#exception--add'));});

                $('#exception--update').find('#policy, #control').change(function() {refresh_type_selects_display($('#exception--update'));});

                $(".exception-table").treegrid('resize');

                $("[name='additional_stakeholders[]']").multiselect();

                $("[name='approval_date']").datepicker({maxDate: new Date});
                $("[name='creation_date']").datepicker({maxDate: new Date});
                $("[name='next_review_date']").datepicker({minDate: new Date});

                //Have to remove the 'fade' class for the shown event to work for modals
                $('#exception--add, #exception--update, #exception--view').on('shown.bs.modal', function() {
                    $(this).find('.modal-body').scrollTop(0);
                    refresh_type_selects_display($(this));
                });

                $('.collapsible--toggle span').click(function(event) {
                    event.preventDefault();

                    if ($(".collapsible--toggle > span > i.fa-caret-right").length && $(".collapsible--toggle > span > i").data('need-refresh'))
                        refreshAuditLogs();

                    $(this).parents('.collapsible--toggle').next('.collapsible').slideToggle('400');
                    $(this).find('i').toggleClass('fa-caret-right fa-caret-down');
                });

                $('.refresh-audit-trail').click(function(event) {
                    event.preventDefault();
                    refreshAuditLogs();
                });

                $('.audit-trail select.audit-select-days').change(refreshAuditLogs);

                refreshAuditLogs();
            });
        </script>
    </head>

    <body>

              
          <?php
              view_top_menu("Governance");

              // Get any alert messages
              get_alert();
          ?>


        <div class="container-fluid">
            <div class="row-fluid">
                <div class="span3">
                    <?php view_governance_menu("DocumentExceptions"); ?>
                </div>
                <div class="span9">
                    <div class="row-fluid">
                        <div class="span12">
                            <div id="exceptions-tab-content">
                                <div class="status-tabs" >
                                    <?php if (check_permission_exception('create')) { ?>
                                        <a href="#exception--add" id="exception-add-btn" role="button" data-toggle="modal" class="project--add"><i class="fa fa-plus"></i></a>
                                    <?php } ?>
                                    <ul class="clearfix tabs-nav">
                                        <li><a href="#policy-exceptions" class="status" data-status="policy"><?php echo $escaper->escapeHtml($lang['PolicyExceptions']); ?> (<span id="policy-exceptions-count">0</span>)</a></li>
                                        <li><a href="#control-exceptions" class="status" data-status="control"><?php echo $escaper->escapeHtml($lang['ControlExceptions']); ?> (<span id="control-exceptions-count">0</span>)</a></li>
                                        <?php if (check_permission_exception('approve')) { ?>
                                            <li><a href="#unapproved-exceptions" class="status" data-status="unapproved"><?php echo $escaper->escapeHtml($lang['UnapprovedExceptions']); ?> (<span id="unapproved-exceptions-count">0</span>)</a></li>
                                        <?php } ?>
                                    </ul>

                                    <div id="policy-exceptions" class="custom-treegrid-container">
                                        <?php get_exception_tabs('policy') ?>
                                    </div>
                                    <div id="control-exceptions" class="custom-treegrid-container">
                                        <?php get_exception_tabs('control') ?>
                                    </div>
                                    <?php if (check_permission_exception('approve')) { ?>
                                        <div id="unapproved-exceptions" class="custom-treegrid-container">
                                            <?php get_exception_tabs('unapproved') ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row-fluid" style="padding-top: 25px;">
                        <div class="well">
                            <h4 class="collapsible--toggle">
                                <span><i class="fa fa-caret-right"></i><?php echo $escaper->escapeHtml($lang['AuditTrail']); ?></span>
                                <a href="#" class="refresh-audit-trail pull-right"><i class="fa fa-refresh"></i></a>
                            </h4>
                            <div class="collapsible" style="display: none;">
                                <div class="row-fluid">
                                    <div class="span12 audit-trail">
                                        <div class="audit-option-container">
                                            <div class="audit-select-folder">
                                                <select name="days" class="audit-select-days">
                                                    <option value="7" selected >Past Week</option>
                                                    <option value="30">Past Month</option>
                                                    <option value="90">Past Quarter</option>
                                                    <option value="180">Past 6 Months</option>
                                                    <option value="365">Past Year</option>
                                                    <option value="36500">All Time</option>
                                                </select>
                                            </div>
                                            <?php
                                                display();
                                            ?>
                                            <div class="clearfix"></div>
                                        </div>
                                        <div class="audit-contents"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL WINDOW FOR ADDING EXCEPTION -->
        <?php if (check_permission_exception('create')) { ?>
            <div id="exception--add" class="modal hide no-padding" tabindex="-1" role="dialog" aria-labelledby="exception--add" aria-hidden="true">
                <form id="exception-new-form" action="#" method="POST" autocomplete="off">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['ExceptionAdd']); ?></h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for=""><?php echo $escaper->escapeHtml($lang['ExceptionName']); ?></label>
                            <input type="text" required name="name" value="" class="form-control" autocomplete="off">

                            <label id="label_for_policy" for=""><?php echo $escaper->escapeHtml($lang['Policy']); ?></label>
                            <?php create_dropdown("policies", NULL, "policy", true); ?>

                            <label id="label_for_control" for=""><?php echo $escaper->escapeHtml($lang['Control']); ?></label>
                            <?php create_dropdown("framework_controls", NULL, "control", true); ?>

                            <label for=""><?php echo $escaper->escapeHtml($lang['ExceptionOwner']); ?></label>
                            <?php create_dropdown("enabled_users", NULL, "owner", false, false, false); ?>

                            <label for=""><?php echo $escaper->escapeHtml($lang['AdditionalStakeholders']); ?></label>
                            <?php create_multiple_dropdown("enabled_users", NULL, "additional_stakeholders"); ?>

                            <label for=""><?php echo $escaper->escapeHtml($lang['CreationDate']); ?></label>
                            <input type="text" name="creation_date" value="<?php echo $escaper->escapeHtml(date(get_default_date_format())); ?>" class="form-control datepicker">

                            <label for=""><?php echo $escaper->escapeHtml($lang['ReviewFrequency']); ?></label>
                            <input type="number" min="0" name="review_frequency" value="0" class="form-control"> <span class="white-labels">(<?php echo $escaper->escapeHtml($lang['days']); ?>)</span>

                            <label for=""><?php echo $escaper->escapeHtml($lang['NextReviewDate']); ?></label>
                            <input type="text" name="next_review_date" value="" class="form-control datepicker">

                            <label for=""><?php echo $escaper->escapeHtml($lang['ApprovalDate']); ?></label>
                            <input type="text" name="approval_date" value="" class="form-control datepicker">

                            <label for=""><?php echo $escaper->escapeHtml($lang['Approver']); ?></label>
                            <?php create_dropdown("enabled_users", NULL, "approver", true); ?>

                            <label for=""><?php echo $escaper->escapeHtml($lang['Description']); ?></label>
                            <textarea name="description" value="" class="form-control" rows="6" style="width:100%;"></textarea>

                            <label for=""><?php echo $escaper->escapeHtml($lang['Justification']); ?></label>
                            <textarea name="justification" value="" class="form-control" rows="6" style="width:100%;"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                        <button type="submit" name="add_exception" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Add']); ?></button>
                    </div>
                </form>
            </div>
        <?php } ?>

        <?php if (check_permission_exception('update')) { ?>
            <!-- MODAL WINDOW FOR EDITING EXCEPTION -->
            <div id="exception--update" class="modal hide no-padding" tabindex="-1" role="dialog" aria-hidden="true">
                <form id="exception-update-form" class="" action="#" method="post" autocomplete="off">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><?php echo $escaper->escapeHtml($lang['ExceptionUpdate']); ?></h4>
                    </div>
                    <input type="hidden" class="exception_id" name="exception_id" value="">
                    <input type="hidden" name="type" value="">
                    <input type="checkbox" name="approved_original" style="display:none;" />

                    <div class="modal-body">
                        <div class="form-group">
                            <label for=""><?php echo $escaper->escapeHtml($lang['ExceptionName']); ?></label>
                            <input type="text" required name="name" value="" class="form-control" autocomplete="off">

                            <label id="label_for_policy" for=""><?php echo $escaper->escapeHtml($lang['Policy']); ?></label>
                            <?php create_dropdown("policies", NULL, "policy", true, false, false, "", "--", "0"); ?>

                            <label id="label_for_control" for=""><?php echo $escaper->escapeHtml($lang['Control']); ?></label>
                            <?php create_dropdown("framework_controls", NULL, "control", true, false, false, "", "--", "0"); ?>

                            <label for=""><?php echo $escaper->escapeHtml($lang['ExceptionOwner']); ?></label>
                            <?php create_dropdown("enabled_users", NULL, "owner", false, false, false); ?>

                            <label for=""><?php echo $escaper->escapeHtml($lang['AdditionalStakeholders']); ?></label>
                            <?php create_multiple_dropdown("enabled_users", NULL, "additional_stakeholders"); ?>

                            <label for=""><?php echo $escaper->escapeHtml($lang['CreationDate']); ?></label>
                            <input type="text" name="creation_date" value="" class="form-control datepicker">

                            <label for=""><?php echo $escaper->escapeHtml($lang['ReviewFrequency']); ?></label>
                            <input type="number" min="0" name="review_frequency" value="" class="form-control"> <span class="white-labels">(<?php echo $escaper->escapeHtml($lang['days']); ?>)</span>

                            <label for=""><?php echo $escaper->escapeHtml($lang['NextReviewDate']); ?></label>
                            <input type="text" name="next_review_date" value="" class="form-control datepicker">

                            <label for=""><?php echo $escaper->escapeHtml($lang['ApprovalDate']); ?></label>
                            <input type="text" name="approval_date" value="" class="form-control datepicker">

                            <label for=""><?php echo $escaper->escapeHtml($lang['Approver']); ?></label>
                            <?php create_dropdown("enabled_users", NULL, "approver", true, false, false, "", "--", "0"); ?>

                            <label for=""><?php echo $escaper->escapeHtml($lang['Description']); ?></label>
                            <textarea name="description" value="" class="form-control" rows="6" style="width:100%;"></textarea>

                            <label for=""><?php echo $escaper->escapeHtml($lang['Justification']); ?></label>
                            <textarea name="justification" value="" class="form-control" rows="6" style="width:100%;"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                        <button type="submit" name="update_exception" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Update']); ?></button>
                    </div>
                </form>
            </div>
        <?php } ?>


        <!-- MODAL WINDOW FOR DISPLAYING AN EXCEPTION -->
        <div id="exception--view" class="modal hide no-padding" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 id="name" class="modal-title"></h4>
                </div>
                <div class="modal-body">

                    <h4><?php echo $escaper->escapeHtml($lang['ExceptionType']); ?></h4>
                    <span id="type" class="exception-data"></span>

                    <span>
                        <h4><?php echo $escaper->escapeHtml($lang['PolicyName']); ?></h4>
                        <span id="policy" class="exception-data"></span>
                    </span>

                    <span>
                        <h4><?php echo $escaper->escapeHtml($lang['ControlName']); ?></h4>
                        <span id="control" class="exception-data"></span>
                    </span>

                    <h4><?php echo $escaper->escapeHtml($lang['ExceptionOwner']); ?></h4>
                    <span id="owner" class="exception-data"></span>

                    <h4><?php echo $escaper->escapeHtml($lang['AdditionalStakeholders']); ?></h4>
                    <span id="additional_stakeholders" class="exception-data"></span>

                    <h4><?php echo $escaper->escapeHtml($lang['CreationDate']); ?></h4>
                    <span id="creation_date" class="exception-data"></span>

                    <h4><?php echo $escaper->escapeHtml($lang['ReviewFrequency']); ?></h4>
                    <span id="review_frequency" class="exception-data"></span><span style="margin-left: 5px;" class="white-labels"><?php echo $escaper->escapeHtml($lang['days']); ?></span>

                    <h4><?php echo $escaper->escapeHtml($lang['NextReviewDate']); ?></h4>
                    <span id="next_review_date" class="exception-data"></span>

                    <h4><?php echo $escaper->escapeHtml($lang['ApprovalDate']); ?></h4>
                    <span id="approval_date" class="exception-data"></span>

                    <h4><?php echo $escaper->escapeHtml($lang['Approver']); ?></h4>
                    <span id="approver" class="exception-data"></span>

                    <h4><?php echo $escaper->escapeHtml($lang['Description']); ?></h4>
                    <div id="description" class="exception-data"></div>

                    <h4><?php echo $escaper->escapeHtml($lang['Justification']); ?></h4>
                    <div id="justification" class="exception-data"></div>

                </div>
                <div class="modal-footer info-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Close']); ?></button>
                </div>
                <?php if (check_permission_exception('approve')) { ?>
                    <div class="modal-footer approve-footer">
                        <form class="" id="exception-approve-form" action="" method="post">
                            <input type="hidden" name="exception_id" value="" />
                            <input type="hidden" name="type" value="" />

                            <button type="button" class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                            <button type="submit" name="approve_exception" class="btn btn-danger"><?php echo $escaper->escapeHtml($lang['Approve']); ?></button>
                        </form>
                    </div>
                <?php } ?>

            </div>
        </div>

        <?php if (check_permission_exception('delete')) { ?>
            <!-- MODAL WINDOW FOR EXCEPTION DELETE CONFIRM -->
            <div id="exception--delete" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="exception-delete-form" aria-hidden="true">
                <div class="modal-body">

                    <form class="" id="exception-delete-form" action="" method="post">
                        <div class="form-group text-center">
                            <label for=""><?php echo $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteThisException']); ?></label>
                            <input type="hidden" name="exception_id" value="" />
                            <input type="hidden" name="type" value="" />
                            <input type="checkbox" id="approved" style="display:none;" />
                        </div>

                        <div class="form-group text-center project-delete-actions">
                            <button type="button" class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                            <button type="submit" name="delete_exception" class="delete_project btn btn-danger"><?php echo $escaper->escapeHtml($lang['Yes']); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL WINDOW FOR EXCEPTION BATCH DELETE CONFIRM -->
            <div id="exception-batch--delete" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="exception-batch-delete-form" aria-hidden="true">
                <div class="modal-body">

                    <form class="" id="exception-batch-delete-form" action="" method="post">
                        <div class="form-group text-center">
                            <label for=""><?php echo $escaper->escapeHtml($lang['AreYouSureYouWantToDeleteTheseExceptions']); ?></label>
                            <input type="hidden" name="parent_id" value="" />
                            <input type="hidden" name="type" value="" />
                            <input type="checkbox" name="approved" style="display:none;" />
                            <input type="checkbox" id="all-approved" style="display:none;" />
                        </div>

                        <div class="form-group text-center project-delete-actions">
                            <button type="button" class="btn btn-default" data-dismiss="modal" aria-hidden="true"><?php echo $escaper->escapeHtml($lang['Cancel']); ?></button>
                            <button type="submit" name="delete_exception" class="delete_project btn btn-danger"><?php echo $escaper->escapeHtml($lang['Yes']); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        <?php } ?>
        <?php display_set_default_date_format_script(); ?>
    </body>

</html>

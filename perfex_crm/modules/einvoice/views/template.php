<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * @var object{id: $template int, name: string, content: string, type: string}|null
 */
init_head();

$contentTypes = [
    [
        'id'   => 'XML',
        'name' => 'XML',
    ],
    [
        'id'   => 'JSON',
        'name' => 'JSON',
    ],
];
?>
<div id="wrapper">
    <div class="content einvoice-template">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700">
                    <?= _l('einvoice_new_template'); ?>
                </h4>
            </div>
            <div class="clearfix"></div>
            <?= form_open('einvoice/validate_and_save' . ($template ? "/{$template->id}" : ''), ['id' => 'template-form']) ?>
            <div class="panel_s">
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-8">
                            <?= render_input('name', 'template_name', $template?->name ?? '') ?>
                            <?= render_select('content_type', $contentTypes, ['id', 'name'], 'template_type', selected: $template?->content_type ?? '', include_blank: false) ?>

                            <div id="content-error" class="alert alert-danger hide" style="margin-top: 10px;">
                                <i class="fa fa-exclamation-triangle"></i>
                                <span
                                    id="content-error-message"><?= _l('einvoice_template_invalid_xml_on_submit') ?></span>
                            </div>
                            <div id="content-success" class="alert alert-success hide" style="margin-top: 10px;">
                                <i class="fa fa-check"></i>
                                <span
                                    id="content-success-message"><?= _l('template_validation_success') ?></span>
                            </div>

                            <div class="form-group">
                                <div class="clearfix">
                                    <label class="control-label pull-left" for="content">
                                        <?= _l('template_content') ?>
                                    </label>
                                    <button type="button" id="validate-btn" class="btn btn-secondary btn-xs pull-right">
                                        <i class="fa fa-check"></i>
                                        <?= _l('validate_template') ?>
                                    </button>
                                </div>

                                <textarea name="content" id="content"
                                    class="form-control ace-editor"><?= e($template?->content ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <?php $this->load->view('einvoice/template_variables'); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="panel-footer">
                    <div class="tw-flex tw-justify-between">
                        <a href="<?= admin_url('settings?group=einvoice') ?>"
                            class="btn btn-default close_btn">
                            <i class="fa fa-arrow-left"></i>
                            <?= _l('go_back'); ?>
                        </a>
                        <button type="submit"
                            class="btn btn-primary"><?= _l('submit'); ?></button>
                    </div>
                </div>
            </div>
            <?= form_close(); ?>
        </div>
    </div>
</div>
<?php init_tail(); ?>

<script>
    $(document).ready(function() {
        var
            templateId = <?= $template ? $template->id : 'null' ?> ;
        var isValidating = false;
        var lastValidationResult = null;

        // Initialize form validation
        appValidateForm($("#template-form"), {
            name: "required",
            content_type: "required",
        });

        // Ensure our error/success elements are properly positioned and visible
        function ensureErrorElementsExist() {
            // Make sure our custom error/success divs are the only ones with these IDs
            $('div[id="content-error"]:not(:first)').remove();
            $('div[id="content-success"]:not(:first)').remove();
        }

        // Call on page load
        ensureErrorElementsExist();

        // Hide alerts function
        function hideAlerts() {
            ensureErrorElementsExist();
            $('#content-error').addClass('hide');
            $('#content-success').addClass('hide');
        }

        // Show error alert
        function showError(message) {
            ensureErrorElementsExist();
            hideAlerts();
            $('#content-error-message').text(message);
            $('#content-error').removeClass('hide');
        }

        // Show success alert
        function showSuccess(message) {
            ensureErrorElementsExist();
            hideAlerts();
            $('#content-success-message').text(message);
            $('#content-success').removeClass('hide');
        }

        // Validate template content via AJAX
        function validateTemplate() {
            if (isValidating) return;

            var content = $('#content').val();
            var contentType = $('#content_type').val();

            if (!content.trim()) {
                showError(
                    '<?= _l('template_content_required') ?>'
                );
                return;
            }

            isValidating = true;
            $('#validate-btn').prop('disabled', true).html(
                '<i class="fa fa-spinner fa-spin"></i> <?= _l('validating') ?>'
            );
            hideAlerts();

            $.ajax({
                url: '<?= admin_url('einvoice/validate_template_ajax') ?>',
                type: 'POST',
                data: {
                    content: content,
                    content_type: contentType
                },
                dataType: 'json',
                success: function(response) {
                    lastValidationResult = response;
                    if (response.success) {
                        showSuccess(response.message);
                    } else {
                        showError(response.message);
                    }
                },
                error: function(xhr, status, error) {
                    lastValidationResult = {
                        success: false
                    };
                    showError(
                        '<?= _l('something_went_wrong') ?>'
                    );
                },
                complete: function() {
                    isValidating = false;
                    $('#validate-btn').prop('disabled', false).html(
                        '<i class="fa fa-check"></i> <?= _l('validate_template') ?>'
                    );
                }
            });
        }

        // Validate button click
        $('#validate-btn').on('click', function(e) {
            e.preventDefault();
            validateTemplate();
        });

        // Content change handler - reset validation state
        $('#content, #content_type').on('change input', function() {
            lastValidationResult = null;
            hideAlerts();
        });

        // Enhanced form submission with AJAX
        $('#template-form').on('submit', function(e) {
            e.preventDefault();

            // Basic form validation
            var name = $('#name').val().trim();
            var contentType = $('#content_type').val();
            var content = $('#content').val().trim();

            if (!name) {
                $('#name').focus();
                return false;
            }

            if (!contentType) {
                $('#content_type').focus();
                return false;
            }

            if (!content) {
                return false;
            }

            var $submitBtn = $(this).find('button[type="submit"]');
            var originalBtnText = $submitBtn.html();

            // Show loading state
            $submitBtn.prop('disabled', true).html(
                '<i class="fa fa-spinner fa-spin"></i> <?= _l('saving') ?>'
            );
            hideAlerts();

            var formData = {
                name: name,
                content_type: contentType,
                content: content
            };

            var url = templateId ?
                '<?= admin_url('einvoice/validate_and_save/') ?>' +
                templateId :
                '<?= admin_url('einvoice/validate_and_save') ?>';

            $.ajax({
                url: url,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Show success message briefly then redirect
                        showSuccess(response.message);
                        setTimeout(function() {
                            window.location.href = response.redirect;
                        }, 1500);
                    } else {
                        showError(response.message);
                        $submitBtn.prop('disabled', false).html(originalBtnText);
                    }
                },
                error: function(xhr) {
                    var errorMessage =
                        '<?= _l('something_went_wrong') ?>';
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMessage = response.message;
                        }
                    } catch (e) {
                        // Use default error message
                    }

                    showError(errorMessage);
                    $submitBtn.prop('disabled', false).html(originalBtnText);
                }
            });
        });

        // Auto-validate on content type change
        $('#content_type').on('change', function() {
            // Always clear alerts when content type changes
            ensureErrorElementsExist();
            hideAlerts();
            lastValidationResult = null;

            // Only auto-validate if there's content
            if ($('#content').val().trim()) {
                setTimeout(validateTemplate, 300); // Small delay to allow UI update
            }
        });
    });
</script>
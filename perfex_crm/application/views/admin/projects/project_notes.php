<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<h4 class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700">
    <?= _l('project_note_private'); ?>
</h4>

<div class="row">
    <div class="col-md-12">
        <button type="button" class="btn btn-primary mbot15 create-note-btn" data-toggle="modal"
            data-target="#project-note-modal">
            <i class="fa-regular fa-plus tw-mr-1"></i>
            <?= _l('new_note'); ?>
        </button>

        <div class="panel_s">
            <div class="panel-body">
                <table class="table dt-table" data-order-col="2" data-order-type="desc">
                    <thead>
                        <tr>
                            <th><?= _l('note_title'); ?>
                            </th>
                            <th width="40%">
                                <?= _l('note_content'); ?>
                            </th>
                            </th>
                            <th><?= _l('staff_notes_table_dateadded_heading'); ?>
                            </th>
                            <th class="options"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_notes as $note) { ?>
                        <tr>
                            <td>
                                <strong><?= e($note['title']); ?></strong>
                            </td>
                            <td width="40%">
                                <?= html_purify($note['content']); ?>
                            </td>
                            <td
                                data-order="<?= e($note['dateadded']); ?>">
                                <?= e(_dt($note['dateadded'])); ?>
                            </td>
                            <td>
                                <div class="tw-flex tw-items-center tw-space-x-2">
                                    <?php if ($note['staff_id'] == get_staff_user_id() || is_admin()) { ?>
                                    <a href="#"
                                        onclick="editProjectNote(<?= e($note['id']); ?>); return false;"
                                        class="text-muted edit-note-btn"
                                        data-note-id="<?= e($note['id']); ?>"
                                        data-note-title="<?= e($note['title']); ?>"
                                        data-note-content="<?= e($note['content']); ?>">
                                        <i class="fa-regular fa-pen-to-square fa-lg"></i>
                                    </a>
                                    <a href="<?= admin_url('projects/delete_note/' . $note['id']); ?>"
                                        class="text-muted _delete">
                                        <i class="fa-regular fa-trash-can fa-lg"></i>
                                    </a>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Project Note Modal -->
<div class="modal fade" id="project-note-modal" tabindex="-1" role="dialog" aria-labelledby="project-note-modal-label">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="project-note-modal-label">
                    <?= _l('new_note'); ?>
                </h4>
            </div>
            <?= form_open('', ['id' => 'project-note-form']); ?>
            <div class="modal-body">
                <input type="hidden" name="note_id" id="note_id" value="">
                <div class="row">
                    <div class="col-md-12">
                        <?= render_input('title', 'note_title', '', 'text', ['required' => true, 'id' => 'note_title']); ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <?= render_textarea('content', 'note_content', '', ['rows' => 5, 'id' => 'note_content', 'class' => 'tinymce tinymce-simple']); ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default"
                    data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-primary"
                    id="note-submit-btn"><?= _l('project_save_note'); ?></button>
            </div>
            <?= form_close(); ?>
        </div>
    </div>
</div>

<?php
hooks()->add_action('app_admin_footer', function () use ($project) {
    ?>
<script>
    $(document).ready(function() {
        // Reset modal when opening for new note
        $('#project-note-modal').on('show.bs.modal', function(e) {
            var relatedTarget = $(e.relatedTarget);
            if (relatedTarget.hasClass('create-note-btn')) {
                resetModal();
            }
        });

        // Handle form submission
        $('#project-note-form').on('submit', function(e) {
            e.preventDefault();

            var noteId = $('#note_id').val();
            var title = $('#note_title').val();
            var content = $('#note_content').val();
            var isEdit = noteId !== '';

            if (title === '' || content === '') {
                return false;
            }

            var url = isEdit ? admin_url + 'projects/edit_note/' + noteId : admin_url +
                'projects/save_note/<?= $project->id; ?>';

            $.post(url, {
                title: title,
                content: content
            }).done(function(response) {
                try {
                    response = JSON.parse(response);
                    if (response.success === true || response.success == "true") {
                        alert_float("success", response.message);
                        $('#project-note-modal').modal('hide');
                        location.reload();
                    } else {
                        alert_float("danger", response.message);
                    }
                } catch (e) {
                    // If response is not JSON, assume success and reload
                    alert_float("success",
                        "<?= _l('added_successfully', _l('project_note')); ?>"
                    );
                    $('#project-note-modal').modal('hide');
                    location.reload();
                }
            }).fail(function() {
                alert_float("danger",
                    "<?= _l('something_went_wrong'); ?>"
                );
            });
        });
    });

    function editProjectNote(noteId) {
        var noteBtn = $('[data-note-id="' + noteId + '"]');
        var title = noteBtn.data('note-title');
        var content = noteBtn.data('note-content');
        $('#project-note-modal-label').text(
            '<?= _l('note'); ?>');
        $('#note-submit-btn').text(
            '<?= _l('update_note'); ?>');
        $('#note_id').val(noteId);
        $('#note_title').val(title);
        tinymce.get('note_content').execCommand('mceSetContent', false, content);
        $('#project-note-modal').modal('show');
    }

    function resetModal() {
        $('#project-note-modal-label').text(
            '<?= _l('new_note'); ?>');
        $('#note-submit-btn').text(
            '<?= _l('project_save_note'); ?>');
        $('#note_id').val('');
        tinymce.get('note_content').execCommand('mceSetContent', false, '');
        $('#project-note-form')[0].reset();
    }
</script>
<?php
});
?>
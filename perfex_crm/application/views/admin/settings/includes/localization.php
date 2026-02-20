<?php defined('BASEPATH') or exit('No direct script access allowed');
$date_formats      = get_available_date_formats();
$enabled_languages = json_decode(get_option('enabled_languages') ?: '[]');
echo form_hidden('settings[localization_settings]', 'true');
?>
<div class="form-group">
    <label for="dateformat"
        class="control-label"><?= _l('settings_localization_date_format'); ?></label>
    <select name="settings[dateformat]" id="dateformat" class="form-control selectpicker"
        data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
        <?php foreach ($date_formats as $key => $val) { ?>
        <option value="<?= e($key); ?>" <?php if ($key == get_option('dateformat')) {
            echo 'selected';
        } ?>><?= e($val); ?>
        </option>
        <?php } ?>
    </select>
</div>
<hr />
<div class="form-group">
    <label for="time_format"
        class="control-label"><?= _l('time_format'); ?></label>
    <select name="settings[time_format]" id="time_format" class="form-control selectpicker"
        data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
        <option value="24" <?php if (get_option('time_format') == '24') {
            echo 'selected';
        } ?>><?= _l('time_format_24'); ?>
        </option>
        <option value="12" <?php if (get_option('time_format') == '12') {
            echo 'selected';
        } ?>><?= _l('time_format_12'); ?>
        </option>
    </select>
</div>
<hr />
<div class="form-group">
    <label for="timezones"
        class="control-label"><?= _l('settings_localization_default_timezone'); ?></label>
    <select name="settings[default_timezone]" id="timezones" class="form-control selectpicker"
        data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>"
        data-live-search="true">
        <?php foreach (get_timezones_list() as $key => $timezones) { ?>
        <optgroup label="<?= e($key); ?>">
            <?php foreach ($timezones as $timezone) { ?>
            <option value="<?= e($timezone); ?>" <?php if (get_option('default_timezone') == $timezone) {
                echo 'selected';
            } ?>><?= e($timezone); ?>
            </option>
            <?php } ?>
        </optgroup>
        <?php } ?>
    </select>
</div>
<hr />
<div class="form-group">
    <label for="active_language"
        class="control-label"><?= _l('settings_localization_default_language'); ?></label>
    <select name="settings[active_language]" data-live-search="true" id="active_language"
        class="form-control selectpicker"
        data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
        <?php foreach ($this->app->get_all_languages() as $availableLanguage) { ?>
        <?php $subtext = hooks()->apply_filters('settings_language_subtext', '', $availableLanguage); ?>
        <option value="<?= e($availableLanguage); ?>"
            data-subtext="<?= e($subtext); ?>" <?= $availableLanguage == get_option('active_language') ? 'selected' : ''; ?>>
            <?= e(ucfirst($availableLanguage)); ?>
        </option>
        <?php } ?>
    </select>
</div>
<hr />
<div class="form-group">
    <label for="enabled_languages"
        class="control-label"><?= _l('enabled_languages'); ?></label>
    <select name="settings[enabled_languages][]" multiple data-live-search="true" id="enabled_languages"
        class="form-control selectpicker"
        data-none-selected-text="<?= _l('all'); ?>">
        <?php foreach ($this->app->get_all_languages() as $availableLanguage) { ?>
        <?php $subtext = hooks()->apply_filters('settings_language_subtext', '', $availableLanguage); ?>
        <option value="<?= e($availableLanguage); ?>"
            data-subtext="<?= e($subtext); ?>" <?= in_array($availableLanguage, $enabled_languages) ? 'selected' : ''; ?>>
            <?= e(ucfirst($availableLanguage)); ?>
        </option>
        <?php } ?>
    </select>
</div>
<hr />
<?php render_yes_no_option('disable_language', 'disable_languages'); ?>
<hr />
<?php render_yes_no_option('output_client_pdfs_from_admin_area_in_client_language', 'settings_output_client_pdfs_from_admin_area_in_client_language', 'settings_output_client_pdfs_from_admin_area_in_client_language_help'); ?>
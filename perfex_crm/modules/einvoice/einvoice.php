<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: e-Invoice
Description: Default module for e-Invoice
Version: 1.0.0
Requires at least: 3.3.*
*/

require __DIR__ . '/vendor/autoload.php';

hooks()->add_filter('module_einvoice_action_links', 'module_einvoice_action_links');
function module_einvoice_action_links(array $actions): array
{
    $actions[] = '<a href="' . admin_url('settings?group=einvoice') . '">' . _l('settings') . '</a>';
    return $actions;
}

hooks()->add_action('admin_init', 'einvoice_module_init');
function einvoice_module_init(): void
{
    $CI = &get_instance();
    $CI->load->helper('einvoice/einvoice');
    $CI->app->add_settings_section_child(
        'finance',
        'einvoice',
        [
            'name'     => _l('settings_group_einvoice'),
            'view'     => 'einvoice/settings',
            'position' => 35,
            'icon'     => 'fa-regular fa-file-text',
        ],
    );

    if (staff_can('bulk_export',  'einvoice_module')) {
        $CI->app_menu->add_sidebar_children_item('utilities', [
            'slug'     => 'einvoice_module_bulk_export',
            'name'     => _l('einvoice_module_bulk_export'),
            'href'     => admin_url('einvoice/export'),
            'position' => 11,
        ]);
    }

    register_staff_capabilities(
        'einvoice_module',
        [
            'capabilities' => [
                'bulk_export' => _l('einvoice_module_permission_bulk'),
            ]
        ],
        _l('einvoice')
    );
}

hooks()->add_action('activate_einvoice_module', 'einvoice_module_activation_hook');
function einvoice_module_activation_hook(): void
{
    add_option('einvoice_send_as_invoice_email_attachment', '0');
    add_option('einvoice_default_credit_note_email_template', '0');
    add_option('einvoice_default_invoice_template');
    add_option('einvoice_default_credit_note_template');
}

hooks()->add_action('before_invoice_preview_more_menu_button', 'einvoice_module_invoice_button');
function einvoice_module_invoice_button($invoice): void
{
    $ci = &get_instance();
    $ci->load->view('einvoice/buttons/invoice', ['invoice' => $invoice]);
}

hooks()->add_action('before_credit_note_preview_more_menu_button', 'einvoice_module_credit_note_button');
function einvoice_module_credit_note_button($creditNote): void
{
    $ci = &get_instance();
    $ci->load->view('einvoice/buttons/credit_note', ['creditNote' => $creditNote]);
}

<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Check if company using invoice with different currencies
 *
 * @param string $table table to check
 *
 * @return bool
 */
function is_using_multiple_currencies($table = null)
{
    if (! $table) {
        $table = db_prefix() . 'invoices';
    }

    $CI = &get_instance();
    $CI->load->model('currencies_model');
    $currencies            = $CI->currencies_model->get();
    $total_currencies_used = 0;
    $other_then_base       = false;
    $base_found            = false;

    foreach ($currencies as $currency) {
        $CI->db->where('currency', $currency['id']);
        $total = $CI->db->count_all_results($table);
        if ($total > 0) {
            $total_currencies_used++;
            if ($currency['isdefault'] == 0) {
                $other_then_base = true;
            } else {
                $base_found = true;
            }
        }
    }

    if ($total_currencies_used > 1 && $base_found == true && $other_then_base == true) {
        return true;
    }
    if ($total_currencies_used == 1 && $base_found == false && $other_then_base == true) {
        return true;
    }

    return ! ($total_currencies_used == 0 || $total_currencies_used == 1);
}
/**
 * Custom format number function for the app
 *
 * @param mixed $total
 * @param bool  $foce_check_zero_decimals whether to force check
 *
 * @return mixed
 */
function app_format_number($total, $foce_check_zero_decimals = false)
{
    if (! is_numeric($total)) {
        return $total;
    }

    $decimal_separator  = get_option('decimal_separator');
    $thousand_separator = get_option('thousand_separator');

    $d = get_decimal_places();
    if (get_option('remove_decimals_on_zero') == 1 || $foce_check_zero_decimals == true) {
        if (! is_decimal($total)) {
            $d = 0;
        }
    }

    $formatted = number_format($total, $d, $decimal_separator, $thousand_separator);

    return hooks()->apply_filters('number_after_format', $formatted, [
        'total'              => $total,
        'decimal_separator'  => $decimal_separator,
        'thousand_separator' => $thousand_separator,
        'decimal_places'     => $d,
    ]);
}

/**
 * Format money/amount based on currency settings
 *
 * @since  2.3.2
 *
 * @param mixed $amount        amount to format
 * @param mixed $currency      currency db object or currency name (ISO code)
 * @param bool  $excludeSymbol whether to exclude to symbol from the format
 *
 * @return string
 */
function app_format_money($amount, $currency, $excludeSymbol = false)
{
    /**
     *  Check ewhether the amount is numeric and valid
     */
    if (! is_numeric($amount) && $amount != 0) {
        return $amount;
    }

    if (is_null($amount)) {
        $amount = 0;
    }

    /**
     * Check if currency is passed as Object from database or just currency name e.q. USD
     */
    if (is_string($currency)) {
        $dbCurrency = get_currency($currency);

        // Check of currency found in case does not exists in database
        if ($dbCurrency) {
            $currency = $dbCurrency;
        } else {
            $currency = [
                'symbol'             => $currency,
                'name'               => $currency,
                'placement'          => 'before',
                'decimal_separator'  => get_option('decimal_separator'),
                'thousand_separator' => get_option('thousand_separator'),
            ];
            $currency = (object) $currency;
        }
    }

    /**
     * Determine the symbol
     *
     * @var string
     */
    $symbol = ! $excludeSymbol ? $currency->symbol : '';

    /**
     * Check decimal places
     *
     * @var mixed
     */
    $d = get_option('remove_decimals_on_zero') == 1 && ! is_decimal($amount) ? 0 : get_decimal_places();

    /**
     * Format the amount
     *
     * @var string
     */
    $amountFormatted = number_format($amount, $d, $currency->decimal_separator, $currency->thousand_separator);

    /**
     * Maybe add the currency symbol
     *
     * @var string
     */
    $formattedWithCurrency = $currency->placement === 'after' ? $amountFormatted . '' . $symbol : $symbol . '' . $amountFormatted;

    return hooks()->apply_filters('app_format_money', $formattedWithCurrency, [
        'amount'         => $amount,
        'currency'       => $currency,
        'exclude_symbol' => $excludeSymbol,
        'decimal_places' => $d,
    ]);
}

/**
 * Check if passed number is decimal
 *
 * @param mixed $val
 *
 * @return bool
 */
function is_decimal($val)
{
    return is_numeric($val) && floor($val) != $val;
}
/**
 * Function that will loop through taxes and will check if there is 1 tax or multiple
 *
 * @param array $taxes
 *
 * @return bool
 */
function multiple_taxes_found_for_item($taxes)
{
    $names = [];

    foreach ($taxes as $t) {
        array_push($names, $t['taxname']);
    }
    $names = array_map('unserialize', array_unique(array_map('serialize', $names)));

    return ! (count($names) == 1);
}

/**
 * If there is more then 200 items in the script the search when creating eq invoice, estimate, proposal
 * will be ajax based
 *
 * @return int
 */
function ajax_on_total_items()
{
    return hooks()->apply_filters('ajax_on_total_items', 200);
}

/**
 * Helper function to get tax by passedid
 *
 * @param int $id taxid
 *
 * @return object
 */
function get_tax_by_id($id)
{
    $CI = &get_instance();
    $CI->db->where('id', $id);

    return $CI->db->get(db_prefix() . 'taxes')->row();
}
/**
 * Helper function to get tax by passed name
 *
 * @param string $name tax name
 *
 * @return object
 */
function get_tax_by_name($name)
{
    $CI = &get_instance();
    $CI->db->where('name', $name);

    return $CI->db->get(db_prefix() . 'taxes')->row();
}
/**
 * This function replace <br /> only nothing exists in the line and first line other then <br />
 *  Replace first <br /> lines to prevent new spaces
 *
 * @param string $text The text to perform the action
 *
 * @return string
 */
function _maybe_remove_first_and_last_br_tag($text)
{
    $text = preg_replace('/^<br ?\/?>/is', '', $text);

    // Replace last <br /> lines to prevent new spaces while there is new line
    while (preg_match('/<br ?\/?>$/', $text)) {
        $text = preg_replace('/<br ?\/?>$/is', '', $text);
    }

    return $text;
}

/**
 * Helper function to replace info format merge fields
 * Info format = Address formats for customers, proposals, company information
 *
 * @param string $mergeCode merge field to check
 * @param mixed  $val       value to replace
 * @param string $txt       from format
 *
 * @return string
 */
function _info_format_replace($mergeCode, $val, $txt)
{
    $tmpVal = strip_tags($val ?: '');

    if ($tmpVal != '') {
        $result = preg_replace('/({' . $mergeCode . '})/i', $val, $txt);
    } else {
        $re     = '/\s{0,}{' . $mergeCode . '}(<br ?\/?>(\n))?/i';
        $result = preg_replace($re, '', $txt);
    }

    return $result;
}

/**
 * Helper function to replace info format custom field merge fields
 * Info format = Address formats for customers, proposals, company information
 *
 * @param mixed  $id    custom field id
 * @param string $label custom field label
 * @param mixed  $value custom field value
 * @param string $txt   from format
 *
 * @return string
 */
function _info_format_custom_field($id, $label, $value, $txt)
{
    if ($value != '') {
        $result = preg_replace('/({cf_' . $id . '})/i', e($label) . ': ' . e($value), $txt);
    } else {
        $re     = '/\s{0,}{cf_' . $id . '}(<br ?\/?>(\n))?/i';
        $result = preg_replace($re, '', $txt);
    }

    return hooks()->apply_filters('info_format_custom_field', $result, [
        'id'    => $id,
        'label' => $label,
        'txt'   => $txt,
    ]);
}

/**
 * Perform necessary checking for custom fields info format
 *
 * @param array  $custom_fields custom fields
 * @param string $txt           info format text
 *
 * @return string
 */
function _info_format_custom_fields_check($custom_fields, $txt)
{
    if (count($custom_fields) == 0 || preg_match_all('/({cf_[0-9]{1,}})/i', $txt, $matches, PREG_SET_ORDER, 0) > 0) {
        $txt = preg_replace('/\s{0,}{cf_[0-9]{1,}}(<br ?\/?>(\n))?/i', '', $txt);
    }

    return $txt;
}

if (! function_exists('format_customer_info')) {
    /**
     * Format customer address info
     *
     * @param object $data        customer object from database
     * @param string $for         where this format will be used? Eq statement invoice etc
     * @param string $type        billing/shipping
     * @param bool   $companyLink company link to be added on customer company/name, this is used in admin area only
     *
     * @return string
     */
    function format_customer_info($data, $for, $type, $companyLink = false)
    {
        $format   = get_option('customer_info_format');
        $clientId = '';

        if ($for == 'statement') {
            $clientId = $data->userid;
        } elseif ($type == 'billing') {
            $clientId = $data->clientid;
        }

        $filterData = [
            'data'         => $data,
            'for'          => $for,
            'type'         => $type,
            'client_id'    => $clientId,
            'company_link' => $companyLink,
        ];

        $companyName = '';
        if ($for == 'statement') {
            $companyName = e(get_company_name($clientId));
        } elseif ($type == 'billing') {
            $companyName = e($data->client->company);
        }

        $acceptsPrimaryContactDisplay = ['invoice', 'estimate', 'payment', 'credit_note'];

        if (in_array($for, $acceptsPrimaryContactDisplay)
            && isset($data->client->show_primary_contact)
            && $data->client->show_primary_contact == 1
            && $primaryContactId = get_primary_contact_user_id($clientId)) {
            $companyName = e(get_contact_full_name($primaryContactId)) . '<br />' . $companyName;
        }

        $companyName = hooks()->apply_filters('customer_info_format_company_name', $companyName, $filterData);

        $street  = in_array($type, ['billing', 'shipping']) ? $data->{$type . '_street'} : '';
        $city    = in_array($type, ['billing', 'shipping']) ? $data->{$type . '_city'} : '';
        $state   = in_array($type, ['billing', 'shipping']) ? $data->{$type . '_state'} : '';
        $zipCode = in_array($type, ['billing', 'shipping']) ? $data->{$type . '_zip'} : '';

        $countryCode = '';
        $countryName = '';

        if ($country = in_array($type, ['billing', 'shipping']) ? get_country($data->{$type . '_country'}) : '') {
            $countryCode = $country->iso2;
            $countryName = $country->short_name;
        }

        $phone = '';
        if ($for == 'statement' && isset($data->phonenumber)) {
            $phone = $data->phonenumber;
        } elseif (in_array($type, ['billing', 'shipping']) && isset($data->client->phonenumber)) {
            $phone = $data->client->phonenumber;
        }

        $vat = '';
        if ($for == 'statement' && isset($data->vat)) {
            $vat = $data->vat;
        } elseif (in_array($type, ['billing', 'shipping']) && isset($data->client->vat)) {
            $vat = $data->client->vat;
        }

        if ($companyLink && (! isset($data->deleted_customer_name)
            || (isset($data->deleted_customer_name)
                && empty($data->deleted_customer_name)))) {
            $companyName = '<a href="' . e(admin_url('clients/client/' . $clientId)) . '" target="_blank"><b>' . $companyName . '</b></a>';
        } elseif ($companyName != '') {
            $companyName = '<b>' . $companyName . '</b>';
        }

        $format = _info_format_replace('company_name', $companyName, $format);
        $format = _info_format_replace('customer_id', $clientId, $format);
        $format = _info_format_replace('street', process_text_content_for_display($street), $format);
        $format = _info_format_replace('city', e($city), $format);
        $format = _info_format_replace('state', e($state), $format);
        $format = _info_format_replace('zip_code', e($zipCode), $format);
        $format = _info_format_replace('country_code', e($countryCode), $format);
        $format = _info_format_replace('country_name', e($countryName), $format);
        $format = _info_format_replace('phone', e($phone), $format);
        $format = _info_format_replace('vat_number', e($vat), $format);
        $format = _info_format_replace('vat_number_with_label', $vat == '' ? '' : _l('client_vat_number') . ': ' . e($vat), $format);

        $customFieldsCustomer = [];

        // On shipping address no custom fields are shown
        if ($type != 'shipping') {
            $whereCF = [];

            if (is_custom_fields_for_customers_portal()) {
                $whereCF['show_on_client_portal'] = 1;
            }

            $customFieldsCustomer = get_custom_fields('customers', $whereCF);
        }

        foreach ($customFieldsCustomer as $field) {
            $value  = get_custom_field_value($clientId, $field['id'], 'customers');
            $format = _info_format_custom_field($field['id'], $field['name'], $value, $format);
        }

        // If no custom fields found replace all custom fields merge fields to empty
        $format = _info_format_custom_fields_check($customFieldsCustomer, $format);
        $format = _maybe_remove_first_and_last_br_tag($format);

        // Remove multiple white spaces
        $format = preg_replace('/\s+/', ' ', $format);
        // Remove multiple coma
        $format = preg_replace('/,{2,}/m', '', $format);

        $format = trim($format);

        return hooks()->apply_filters('customer_info_text', $format, $filterData);
    }
}

if (! function_exists('format_proposal_info')) {
    /**
     * Format proposal info format
     *
     * @param object $proposal proposal from database
     * @param string $for      where this info will be used? Admin area, HTML preview?
     *
     * @return string
     */
    function format_proposal_info($proposal, $for = '')
    {
        $format = get_option('proposal_info_format');

        $countryCode = '';
        $countryName = '';

        if ($country = get_country($proposal->country)) {
            $countryCode = $country->iso2;
            $countryName = $country->short_name;
        }

        $proposalTo = '<b>' . e($proposal->proposal_to) . '</b>';
        $phone      = $proposal->phone;
        $email      = $proposal->email;

        if ($for == 'admin') {
            $hrefAttrs = '';
            if ($proposal->rel_type == 'lead') {
                $hrefAttrs = ' href="#" onclick="init_lead(' . e($proposal->rel_id) . '); return false;" data-toggle="tooltip" data-title="' . e(_l('lead')) . '"';
            } else {
                $hrefAttrs = ' href="' . e(admin_url('clients/client/' . $proposal->rel_id)) . '" data-toggle="tooltip" data-title="' . e(_l('client')) . '"';
            }
            $proposalTo = '<a' . $hrefAttrs . '>' . $proposalTo . '</a>';
        }

        if ($for == 'html' || $for == 'admin') {
            $phone = '<a href="tel:' . e($proposal->phone) . '">' . e($proposal->phone) . '</a>';
            $email = '<a href="mailto:' . e($proposal->email) . '">' . e($proposal->email) . '</a>';
        }

        $format = _info_format_replace('proposal_to', $proposalTo, $format);
        $format = _info_format_replace('address', process_text_content_for_display($proposal->address), $format);
        $format = _info_format_replace('city', e($proposal->city), $format);
        $format = _info_format_replace('state', e($proposal->state), $format);

        $format = _info_format_replace('country_code', e($countryCode), $format);
        $format = _info_format_replace('country_name', e($countryName), $format);

        $format = _info_format_replace('zip_code', e($proposal->zip), $format);
        $format = _info_format_replace('phone', $phone, $format);
        $format = _info_format_replace('email', $email, $format);

        $whereCF = [];
        if (is_custom_fields_for_customers_portal()) {
            $whereCF['show_on_client_portal'] = 1;
        }
        $customFieldsProposals = get_custom_fields('proposal', $whereCF);

        foreach ($customFieldsProposals as $field) {
            $value  = get_custom_field_value($proposal->id, $field['id'], 'proposal');
            $format = _info_format_custom_field($field['id'], $field['name'], $value, $format);
        }

        // If no custom fields found replace all custom fields merge fields to empty
        $format = _info_format_custom_fields_check($customFieldsProposals, $format);
        $format = _maybe_remove_first_and_last_br_tag($format);

        // Remove multiple white spaces
        $format = preg_replace('/\s+/', ' ', $format);
        $format = trim($format);

        return hooks()->apply_filters('proposal_info_text', $format, ['proposal' => $proposal, 'for' => $for]);
    }
}

if (! function_exists('format_organization_info')) {
    /**
     * Format company info/address format
     *
     * @return string
     */
    function format_organization_info()
    {
        $format = get_option('company_info_format');
        $vat    = get_option('company_vat');

        $format = _info_format_replace('company_name', '<b style="color:black" class="company-name-formatted">' . e(get_option('invoice_company_name')) . '</b>', $format);
        $format = _info_format_replace('address', e(get_option('invoice_company_address')), $format);
        $format = _info_format_replace('city', e(get_option('invoice_company_city')), $format);
        $format = _info_format_replace('state', e(get_option('company_state')), $format);

        $format = _info_format_replace('zip_code', e(get_option('invoice_company_postal_code')), $format);
        $format = _info_format_replace('country_code', e(get_option('invoice_company_country_code')), $format);
        $format = _info_format_replace('phone', e(get_option('invoice_company_phonenumber')), $format);
        $format = _info_format_replace('vat_number', e($vat), $format);
        $format = _info_format_replace('vat_number_with_label', $vat == '' ? '' : _l('company_vat_number') . ': ' . e($vat), $format);

        $custom_company_fields = get_company_custom_fields();

        foreach ($custom_company_fields as $field) {
            $format = _info_format_custom_field($field['id'], $field['label'], $field['value'], $format);
        }

        $format = _info_format_custom_fields_check($custom_company_fields, $format);
        $format = _maybe_remove_first_and_last_br_tag($format);

        // Remove multiple white spaces
        $format = preg_replace('/\s+/', ' ', $format);
        $format = trim($format);

        return hooks()->apply_filters('organization_info_text', $format);
    }
}

/**
 * Return decimal places
 * The srcipt do not support more then 2 decimal places but developers can use action hook to change the decimal places
 *
 * @return [type] [description]
 */
function get_decimal_places()
{
    return hooks()->apply_filters('app_decimal_places', 2);
}

/**
 * Get all items by type eq. invoice, proposal, estimates, credit note
 *
 * @param string $type rel_type value
 * @param mixed  $id
 *
 * @return array
 */
function get_items_by_type($type, $id)
{
    $CI = &get_instance();
    $CI->db->select();
    $CI->db->from(db_prefix() . 'itemable');
    $CI->db->where('rel_id', $id);
    $CI->db->where('rel_type', $type);
    $CI->db->order_by('item_order', 'asc');

    $result = $CI->db->get()->result_array();

    // Get function name for taxes based on type
    $func_taxes = 'get_' . $type . '_item_taxes';

    return array_map(function ($item) use ($func_taxes) {
        // Get taxes for this item if function exists
        $item_taxes = [];
        if (function_exists($func_taxes)) {
            $item_taxes = call_user_func($func_taxes, $item['id']);
        }

        return array_merge($item, [
            'is_optional' => isset($item['is_optional']) ? (bool) $item['is_optional'] : false,
            'is_selected' => isset($item['is_selected']) ? (bool) $item['is_selected'] : true,
            'taxes'       => $item_taxes,
        ]);
    }, $result);
}
/**
 * Function that update total tax in sales table eq. invoice, proposal, estimates, credit note
 *
 * @param mixed $id
 * @param mixed $type
 * @param mixed $table
 *
 * @return void
 */
function update_sales_total_tax_column($id, $type, $table)
{
    $CI = &get_instance();
    $CI->db->select('discount_percent, discount_type, discount_total, subtotal');
    $CI->db->from($table);
    $CI->db->where('id', $id);

    $data = $CI->db->get()->row();

    $items = get_items_by_type($type, $id);

    $total_tax         = 0;
    $taxes             = [];
    $_calculated_taxes = [];

    $func_taxes = 'get_' . $type . '_item_taxes';

    foreach ($items as $item) {
        // Check if item is optional and not selected - skip calculation if so
        $isOptional = isset($item['is_optional']) && $item['is_optional'] == 1;
        $isSelected = isset($item['is_selected']) && $item['is_selected'] == 1;

        if ($isOptional && ! $isSelected) {
            continue; // skip this item from calculations
        }

        $item_taxes = call_user_func($func_taxes, $item['id']);
        if (count($item_taxes) > 0) {
            foreach ($item_taxes as $tax) {
                $calc_tax     = 0;
                $tax_not_calc = false;
                if (! in_array($tax['taxname'], $_calculated_taxes)) {
                    array_push($_calculated_taxes, $tax['taxname']);
                    $tax_not_calc = true;
                }

                if ($tax_not_calc == true) {
                    $taxes[$tax['taxname']]          = [];
                    $taxes[$tax['taxname']]['total'] = [];
                    array_push($taxes[$tax['taxname']]['total'], (($item['qty'] * $item['rate']) / 100 * $tax['taxrate']));
                    $taxes[$tax['taxname']]['tax_name'] = $tax['taxname'];
                    $taxes[$tax['taxname']]['taxrate']  = $tax['taxrate'];
                } else {
                    array_push($taxes[$tax['taxname']]['total'], (($item['qty'] * $item['rate']) / 100 * $tax['taxrate']));
                }
            }
        }
    }

    foreach ($taxes as $tax) {
        $total = array_sum($tax['total']);
        if ($data->discount_percent != 0 && $data->discount_type == 'before_tax') {
            $total_tax_calculated = ($total * $data->discount_percent) / 100;
            $total                = ($total - $total_tax_calculated);
        } elseif ($data->discount_total != 0 && $data->discount_type == 'before_tax') {
            $t     = ($data->discount_total / $data->subtotal) * 100;
            $total = ($total - $total * $t / 100);
        }
        $total_tax += $total;
    }

    $CI->db->where('id', $id);
    $CI->db->update($table, [
        'total_tax' => $total_tax,
    ]);
}

/**
 * Function used for sales eq. invoice, estimate, proposal, credit note
 *
 * @param mixed  $item_id   item id
 * @param array  $post_item $item from $_POST
 * @param mixed  $rel_id    rel_id
 * @param string $rel_type  where this item tax is related
 */
function _maybe_insert_post_item_tax($item_id, $post_item, $rel_id, $rel_type)
{
    $affectedRows = 0;
    if (isset($post_item['taxname']) && is_array($post_item['taxname'])) {
        $CI = &get_instance();

        foreach ($post_item['taxname'] as $taxname) {
            if ($taxname != '') {
                $tax_array = explode('|', $taxname);
                if (isset($tax_array[0], $tax_array[1])) {
                    $tax_name = trim($tax_array[0]);
                    $tax_rate = trim($tax_array[1]);
                    if (total_rows(db_prefix() . 'item_tax', [
                        'itemid'   => $item_id,
                        'taxrate'  => $tax_rate,
                        'taxname'  => $tax_name,
                        'rel_id'   => $rel_id,
                        'rel_type' => $rel_type,
                    ]) == 0) {
                        $CI->db->insert(db_prefix() . 'item_tax', [
                            'itemid'   => $item_id,
                            'taxrate'  => $tax_rate,
                            'taxname'  => $tax_name,
                            'rel_id'   => $rel_id,
                            'rel_type' => $rel_type,
                        ]);
                        $affectedRows++;
                    }
                }
            }
        }
    }

    return $affectedRows > 0 ? true : false;
}

/**
 * Add new item do database, used for proposals,estimates,credit notes,invoices
 * This is repetitive action, that's why this function exists
 *
 * @param array  $item     item from $_POST
 * @param mixed  $rel_id   relation id eq. invoice id
 * @param string $rel_type relation type eq invoice
 */
function add_new_sales_item_post($item, $rel_id, $rel_type)
{
    $custom_fields = false;

    if (isset($item['custom_fields'])) {
        $custom_fields = $item['custom_fields'];
    }

    $CI         = &get_instance();
    $isOptional = $item['is_optional'] ?? false;

    $CI->db->insert(db_prefix() . 'itemable', [
        'description'      => $item['description'],
        'long_description' => nl2br($item['long_description']),
        'qty'              => $item['qty'],
        'rate'             => number_format($item['rate'], get_decimal_places(), '.', ''),
        'rel_id'           => $rel_id,
        'rel_type'         => $rel_type,
        'item_order'       => $item['order'],
        'unit'             => $item['unit'],
        'is_optional'      => $isOptional ? 1 : 0,
        'is_selected'      => $isOptional ? ($item['is_selected'] ?? 0) : 1,
    ]);

    $id = $CI->db->insert_id();

    if ($custom_fields !== false) {
        handle_custom_fields_post($id, $custom_fields);
    }

    return $id;
}

/**
 * Update sales item from $_POST, eq invoice item, estimate item
 *
 * @param mixed  $item_id item id to update
 * @param array  $data    item $_POST data
 * @param string $field   field is require to be passed for long_description,rate,item_order to do some additional checkings
 *
 * @return bool
 */
function update_sales_item_post($item_id, $data, $field = '')
{
    $update = [];

    if ($field !== '') {
        if ($field == 'long_description') {
            $update[$field] = nl2br($data[$field]);
        } elseif ($field == 'rate') {
            $update[$field] = number_format($data[$field], get_decimal_places(), '.', '');
        } elseif ($field == 'item_order') {
            $update[$field] = $data['order'];
        } else {
            $update[$field] = $data[$field];
        }

        if ($field === 'is_optional') {
            $isOptional            = $data['is_optional'] ?? false;
            $update['is_optional'] = $isOptional ? 1 : 0;
            $update['is_selected'] = $isOptional ? ($data['is_selected'] ?? 0) : 1;
        }
    } else {
        $isOptional = $data['is_optional'] ?? false;

        $update = [
            'item_order'       => $data['order'],
            'description'      => $data['description'],
            'long_description' => nl2br($data['long_description']),
            'rate'             => number_format($data['rate'], get_decimal_places(), '.', ''),
            'qty'              => $data['qty'],
            'unit'             => $data['unit'],
            'is_optional'      => $isOptional ? 1 : 0,
            'is_selected'      => $isOptional ? ($data['is_selected'] ?? 0) : 1,
        ];
    }

    $CI = &get_instance();
    $CI->db->where('id', $item_id);
    $CI->db->update(db_prefix() . 'itemable', $update);

    return $CI->db->affected_rows() > 0 ? true : false;
}

/**
 * When item is removed eq from invoice will be stored in removed_items in $_POST
 * With foreach loop this function will remove the item from database and it's taxes
 *
 * @param mixed  $id       item id to remove
 * @param string $rel_type item relation eq. invoice, estimate
 *
 * @return boolena
 */
function handle_removed_sales_item_post($id, $rel_type)
{
    $CI = &get_instance();

    $CI->db->where('id', $id);
    $CI->db->delete(db_prefix() . 'itemable');
    if ($CI->db->affected_rows() > 0) {
        delete_taxes_from_item($id, $rel_type);

        $CI->db->where('relid', $id);
        $CI->db->where('fieldto', 'items');
        $CI->db->delete(db_prefix() . 'customfieldsvalues');

        return true;
    }

    return false;
}

/**
 * Remove taxes from item
 *
 * @param mixed  $item_id  item id
 * @param string $rel_type relation type eq. invoice, estimate etc.
 *
 * @return bool
 */
function delete_taxes_from_item($item_id, $rel_type)
{
    $CI = &get_instance();
    $CI->db->where('itemid', $item_id)
        ->where('rel_type', $rel_type)
        ->delete(db_prefix() . 'item_tax');

    return $CI->db->affected_rows() > 0 ? true : false;
}

function is_sale_discount_applied($data)
{
    return $data->discount_total > 0;
}

function is_sale_discount($data, $is)
{
    if ($data->discount_percent == 0 && $data->discount_total == 0) {
        return false;
    }

    $discount_type = 'fixed';
    if ($data->discount_percent != 0) {
        $discount_type = 'percent';
    }

    return $discount_type == $is;
}

/**
 * Get items table for preview
 *
 * @param object $transaction   e.q. invoice, estimate from database result row
 * @param string $type          type, e.q. invoice, estimate, proposal
 * @param string $for           where the items will be shown, html or pdf
 * @param bool   $admin_preview is the preview for admin area
 *
 * @return object
 */
function get_items_table_data($transaction, $type, $for = 'html', $admin_preview = false)
{
    include_once APPPATH . 'libraries/App_items_table.php';
    $class = new App_items_table($transaction, $type, $for, $admin_preview);

    $class = hooks()->apply_filters('items_table_class', $class, $transaction, $type, $for, $admin_preview);

    if (! $class instanceof App_items_table_template) {
        show_error(get_class($class) . ' must be instance of "App_items_template"');
    }

    return $class;
}

function sales_number_format($number, $format, $applied_prefix, $date)
{
    $originalNumber = $number;
    $prefixPadding  = get_option('number_padding_prefixes');

    if ($format == 1) {
        // Number based
        $number = $applied_prefix . str_pad($number, $prefixPadding, '0', STR_PAD_LEFT);
    } elseif ($format == 2) {
        // Year based
        $number = $applied_prefix . date('Y', strtotime($date)) . '/' . str_pad($number, $prefixPadding, '0', STR_PAD_LEFT);
    } elseif ($format == 3) {
        // Number-yy based
        $number = $applied_prefix . str_pad($number, $prefixPadding, '0', STR_PAD_LEFT) . '-' . date('y', strtotime($date));
    } elseif ($format == 4) {
        // Number-mm-yyyy based
        $number = $applied_prefix . str_pad($number, $prefixPadding, '0', STR_PAD_LEFT) . '/' . date('m', strtotime($date)) . '/' . date('Y', strtotime($date));
    }

    return hooks()->apply_filters('sales_number_format', $number, [
        'format'         => $format,
        'date'           => $date,
        'number'         => $originalNumber,
        'prefix_padding' => $prefixPadding,
    ]);
}

/**
 * Helper function to get currency by ID or by Name
 *
 * @since  2.3.2
 *
 * @param mixed $id_or_name
 *
 * @return object
 */
function get_currency($id_or_name)
{
    $CI = &get_instance();
    if (! class_exists('currencies_model', false)) {
        $CI->load->model('currencies_model');
    }

    if (is_numeric($id_or_name)) {
        return $CI->currencies_model->get($id_or_name);
    }

    return $CI->currencies_model->get_by_name($id_or_name);
}

/**
 * Get base currency
 *
 * @since  2.3.2
 *
 * @return object
 */
function get_base_currency()
{
    $CI = &get_instance();

    if (! class_exists('currencies_model', false)) {
        $CI->load->model('currencies_model');
    }

    return $CI->currencies_model->get_base_currency();
}

/**
 * Calculate sales totals (subtotal, taxes, discounts, total)
 * PHP equivalent of the JavaScript calculate_total() function
 *
 * @param array $items   Array of items with keys: qty, rate, taxname (array of tax names|rates)
 * @param array $options Array with keys: discount_percent, discount_total, discount_type, adjustment
 *
 * @return array Array with calculated values: subtotal, taxes, total_tax, discount_calculated, total, discount_html
 */
function calculate_sales_total($items = [], $options = [])
{
    // Default options
    $defaults = [
        'discount_percent' => 0,
        'discount_total'   => 0,
        'discount_type'    => 'before_tax',
        'adjustment'       => 0,
    ];

    $defaults['discount_total_type'] = $options['discount_total_type'] ?? ($options['discount_percent'] > 0 ? 'percent' : 'fixed');

    $options = array_merge($defaults, $options);

    $taxes                     = [];
    $subtotal                  = 0;
    $total                     = 0;
    $total_discount_calculated = 0;

    // Calculate subtotal and taxes for each item
    foreach ($items as $item) {
        $quantity = ! empty($item['qty']) ? $item['qty'] : 1;
        $rate     = ! empty($item['rate']) ? $item['rate'] : 0;

        // Check if item is optional and not chosen - skip calculation if so
        $isOptional = isset($item['is_optional']) && $item['is_optional'] == 1;
        $isChosen   = isset($item['is_selected']) && $item['is_selected'] == 1;

        if ($isOptional && ! $isChosen) {
            continue; // skip this item from calculations
        }

        // Calculate item amount
        $amount = $rate * $quantity;
        $amount = round($amount, get_decimal_places());

        $subtotal += $amount;

        // Process item taxes
        if (! empty($item['taxes']) && is_array($item['taxes'])) {
            foreach ($item['taxes'] as $tax) {
                if (! empty($tax['taxname']) && ! empty($tax['taxrate'])) {
                    $taxname  = $tax['taxname'];
                    $tax_rate = (float) $tax['taxrate'];

                    // Extract tax name from the taxname field (format: "TAX1|18.00")
                    $tax_parts = explode('|', $taxname);
                    $tax_name  = count($tax_parts) >= 1 ? trim($tax_parts[0]) : $taxname;

                    if ($tax_rate != 0) {
                        $calculated_tax = ($amount / 100) * $tax_rate;

                        if (! isset($taxes[$taxname])) {
                            $taxes[$taxname] = [
                                'name'  => $tax_name,
                                'rate'  => $tax_rate,
                                'total' => $calculated_tax,
                            ];
                        } else {
                            $taxes[$taxname]['total'] += $calculated_tax;
                        }
                    }
                }
            }
        } elseif (! empty($item['taxname']) && is_array($item['taxname'])) {
            // Fallback for the original format
            foreach ($item['taxname'] as $taxname) {
                if (! empty($taxname)) {
                    $tax_parts = explode('|', $taxname);
                    if (count($tax_parts) == 2) {
                        $tax_name = trim($tax_parts[0]);
                        $tax_rate = (float) trim($tax_parts[1]);

                        if ($tax_rate != 0) {
                            $calculated_tax = ($amount / 100) * $tax_rate;

                            if (! isset($taxes[$taxname])) {
                                $taxes[$taxname] = [
                                    'name'  => $tax_name,
                                    'rate'  => $tax_rate,
                                    'total' => $calculated_tax,
                                ];
                            } else {
                                $taxes[$taxname]['total'] += $calculated_tax;
                            }
                        }
                    }
                }
            }
        }
    }

    // Calculate discount before tax
    if ($options['discount_percent'] > 0
        && $options['discount_type'] == 'before_tax'
        && $options['discount_total_type'] == 'percent') {
        $total_discount_calculated = ($subtotal * $options['discount_percent']) / 100;
    } elseif ($options['discount_total'] > 0
              && $options['discount_type'] == 'before_tax'
              && $options['discount_total_type'] == 'fixed') {
        $total_discount_calculated = $options['discount_total'];
    }

    // Apply discount to taxes if discount is before tax
    $total_tax = 0;

    foreach ($taxes as $taxname => $tax_data) {
        $tax_total = $tax_data['total'];

        if ($options['discount_percent'] > 0
            && $options['discount_type'] == 'before_tax'
            && $options['discount_total_type'] == 'percent') {
            $tax_discount = ($tax_total * $options['discount_percent']) / 100;
            $tax_total    = $tax_total - $tax_discount;
        } elseif ($options['discount_total'] > 0
                  && $options['discount_type'] == 'before_tax'
                  && $options['discount_total_type'] == 'fixed') {
            $discount_percentage = ($options['discount_total'] / $subtotal) * 100;
            $tax_total           = $tax_total - ($tax_total * $discount_percentage / 100);
        }

        $taxes[$taxname]['total'] = $tax_total;
        $total_tax += $tax_total;
    }

    $total = $total_tax + $subtotal;

    // Calculate discount after tax
    if ($options['discount_percent'] > 0
        && $options['discount_type'] == 'after_tax'
        && $options['discount_total_type'] == 'percent') {
        $total_discount_calculated = ($total * $options['discount_percent']) / 100;
    } elseif ($options['discount_total'] > 0
              && $options['discount_type'] == 'after_tax'
              && $options['discount_total_type'] == 'fixed') {
        $total_discount_calculated = $options['discount_total'];
    }

    $total = $total - $total_discount_calculated;

    // Add adjustment
    if (! empty($options['adjustment']) && is_numeric($options['adjustment'])) {
        $total += $options['adjustment'];
    }

    // Format discount for display
    $discount_html = '-' . app_format_money($total_discount_calculated, get_base_currency());

    return [
        'subtotal'            => round($subtotal, get_decimal_places()),
        'taxes'               => $taxes,
        'total_tax'           => round($total_tax, get_decimal_places()),
        'discount_calculated' => round($total_discount_calculated, get_decimal_places()),
        'total'               => round($total, get_decimal_places()),
        'discount_html'       => $discount_html,
        'adjustment'          => $options['adjustment'],
    ];
}

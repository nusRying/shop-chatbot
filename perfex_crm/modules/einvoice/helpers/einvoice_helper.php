<?php


function einvioce_module_get_templates(): array
{
    $ci = &get_instance();
    $ci->load->model('templates_model');
    return $ci->templates_model->getByType('einvoice');
}

function einvioce_custom_field_placeholder($slug): string
{
    $slug = 'CF_' . strtoupper($slug);
    return '{{' . $slug . '}}';
}

function einvioce_custom_field_value_placeholder($slug): string
{
    return 'CF_' . strtoupper($slug);
}

function encodeForXml($value, $doubleEncode = true): string
{
    if ($value instanceof BackedEnum) {
        $value = $value->value;
    }

    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8', $doubleEncode);
}
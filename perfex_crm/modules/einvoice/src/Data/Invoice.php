<?php

namespace Perfexcrm\EInvoice\Data;

use JsonSerializable;

class Invoice implements JsonSerializable
{
    /**
     * @var string[]
     */
    private static array $placeholders;

    public function __construct(private readonly object $invoice)
    {
    }

    public static function getPlaceholders(): array
    {
        if (! isset(self::$placeholders)) {
            $placeholders = [
                '{{INVOICE_ID}}',
                '{{INVOICE_NUMBER}}',
                '{{INVOICE_DATE}}',
                '{{INVOICE_DUE_DATE}}',
                '{{INVOICE_STATUS}}',
                '{{INVOICE_SUBTOTAL}}',
                '{{INVOICE_TOTAL_TAX}}',
                '{{INVOICE_ADJUSTMENT}}',
                '{{INVOICE_DISCOUNT_TOTAL}}',
                '{{INVOICE_TOTAL}}',
                '{{INVOICE_AMOUNT_PAID}}',
                '{{INVOICE_BALANCE_DUE}}',
                '{{CURRENCY_CODE}}',
                '{{INVOICE_BILLING_ADRESS}}',
                '{{INVOICE_BILLING_CITY}}',
                '{{INVOICE_BILLING_STATE}}',
                '{{INVOICE_BILLING_ZIP}}',
                '{{INVOICE_BILLING_COUNTRY_NAME}}',
                '{{INVOICE_BILLING_COUNTRY_ISO2}}',
                '{{INVOICE_BILLING_COUNTRY_ISO3}}',
                '{{INVOICE_SHIPPING_ADRESS}}',
                '{{INVOICE_SHIPPING_CITY}}',
                '{{INVOICE_SHIPPING_STATE}}',
                '{{INVOICE_SHIPPING_ZIP}}',
                '{{INVOICE_SHIPPING_COUNTRY_NAME}}',
                '{{INVOICE_SHIPPING_COUNTRY_ISO2}}',
                '{{INVOICE_SHIPPING_COUNTRY_ISO3}}',
            ];

            $custom_fields = get_custom_fields('invoice');

            foreach ($custom_fields as $field) {
                $placeholders[] = einvioce_custom_field_placeholder($field['slug']);
            }
            self::$placeholders = $placeholders;
        }

        return hooks()->apply_filters('before_get_einvoice_invoice_placeholders', self::$placeholders);
    }

    public function getPlaceHolderValues(): array
    {
        $currency = get_currency($this->invoice->currency);

        $amountLeftToPay = get_invoice_total_left_to_pay($this->invoice->id, $this->invoice->total);
        $shipCountry     = get_country($this->invoice->shipping_country);
        $billCountry     = get_country($this->invoice->billing_country);
        $values          = [
            'INVOICE_ID'                    => encodeForXml($this->invoice->id),
            'INVOICE_NUMBER'                => encodeForXml(format_invoice_number($this->invoice->id)),
            'INVOICE_DUE_DATE'              => encodeForXml($this->invoice->duedate),
            'INVOICE_DATE'                  => encodeForXml(_d($this->invoice->date)),
            'INVOICE_STATUS'                => encodeForXml(format_invoice_status($this->invoice->status, '', false)),
            'INVOICE_SUBTOTAL'              => encodeForXml(number_format($this->invoice->subtotal, get_decimal_places())),
            'INVOICE_TOTAL_TAX'             => encodeForXml(number_format($this->invoice->total_tax, get_decimal_places())),
            'INVOICE_ADJUSTMENT'            => encodeForXml(number_format($this->invoice->adjustment, get_decimal_places())),
            'INVOICE_DISCOUNT_TOTAL'        => encodeForXml(number_format($this->invoice->discount_total, get_decimal_places())),
            'INVOICE_TOTAL'                 => encodeForXml(number_format($this->invoice->total, get_decimal_places())),
            'INVOICE_AMOUNT_PAID'           => encodeForXml(number_format($this->invoice->total - $amountLeftToPay, get_decimal_places())),
            'INVOICE_BALANCE_DUE'           => encodeForXml(number_format($amountLeftToPay, get_decimal_places())),
            'CURRENCY_CODE'                 => encodeForXml($currency->name),
            'INVOICE_BILLING_ADRESS'        => encodeForXml($this->invoice->billing_street, false),
            'INVOICE_BILLING_CITY'          => encodeForXml($this->invoice->billing_city, false),
            'INVOICE_BILLING_STATE'         => encodeForXml($this->invoice->billing_state, false),
            'INVOICE_BILLING_ZIP'           => encodeForXml($this->invoice->billing_zip, false),
            'INVOICE_BILLING_COUNTRY_NAME'  => encodeForXml($billCountry?->short_name, false),
            'INVOICE_BILLING_COUNTRY_ISO2'  => encodeForXml($billCountry?->iso2, false),
            'INVOICE_BILLING_COUNTRY_ISO3'  => encodeForXml($billCountry?->iso3, false),
            'INVOICE_SHIPPING_ADRESS'       => encodeForXml($this->invoice->shipping_street, false),
            'INVOICE_SHIPPING_CITY'         => encodeForXml($this->invoice->shipping_city, false),
            'INVOICE_SHIPPING_STATE'        => encodeForXml($this->invoice->shipping_state, false),
            'INVOICE_SHIPPING_ZIP'          => encodeForXml($this->invoice->shipping_zip, false),
            'INVOICE_SHIPPING_COUNTRY_NAME' => encodeForXml($shipCountry?->short_name, false),
            'INVOICE_SHIPPING_COUNTRY_ISO2' => encodeForXml($shipCountry?->iso2, false),
            'INVOICE_SHIPPING_COUNTRY_ISO3' => encodeForXml($shipCountry?->iso3, false),
        ];

        $custom_fields = get_custom_fields('invoice');

        foreach ($custom_fields as $field) {
            $values[einvioce_custom_field_value_placeholder($field['slug'])] = encodeForXml(
                get_custom_field_value($this->invoice->id, $field['id'], 'invoice') ?: $field['default_value'],
                false
            );
        }

        return hooks()->apply_filters('before_get_einvoice_invoice_placeholder_values', $values, $this->invoice);
    }

    public function items(): array
    {
        return collect($this->invoice->items)
            ->map(fn ($item) => (new Item($item, $this->invoice->id, 'invoice'))->getPlaceHolderValues())
            ->toArray();
    }

    public function customer(): array
    {
        return (new Customer($this->invoice->client))->getPlaceHolderValues();
    }

    public function jsonSerialize(): array
    {
        $data               = $this->getPlaceHolderValues();
        $data               = array_merge($data, (new Company())->getPlaceHolderValues(), $this->customer());
        $data['LINE_ITEMS'] = $this->items();

        return $data;
    }
}

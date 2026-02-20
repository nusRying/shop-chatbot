<?php

namespace Perfexcrm\EInvoice\Data;

use JsonSerializable;

class CreditNote implements JsonSerializable
{
    /**
     * @var string[]
     */
    private static array $placeholders;

    public function __construct(private readonly object $creditNote)
    {
    }

    public static function getPlaceholders(): array
    {
        if (! isset(self::$placeholders)) {
            $placeholders = [
                '{{CREDIT_NOTE_ID}}',
                '{{CREDIT_NOTE_NUMBER}}',
                '{{CREDIT_NOTE_DATE}}',
                '{{CREDIT_NOTE_DATE}}',
                '{{CREDIT_NOTE_STATUS}}',
                '{{CREDIT_NOTE_SUBTOTAL}}',
                '{{CREDIT_NOTE_TOTAL_TAX}}',
                '{{CREDIT_NOTE_ADJUSTMENT}}',
                '{{CREDIT_NOTE_DISCOUNT_TOTAL}}',
                '{{CREDIT_NOTE_TOTAL}}',
                '{{CURRENCY_CODE}}',
                '{{CREDIT_NOTE_BILLING_ADRESS}}',
                '{{CREDIT_NOTE_BILLING_CITY}}',
                '{{CREDIT_NOTE_BILLING_STATE}}',
                '{{CREDIT_NOTE_BILLING_ZIP}}',
                '{{CREDIT_NOTE_BILLING_COUNTRY}}',
                '{{CREDIT_NOTE_SHIPPING_ADRESS}}',
                '{{CREDIT_NOTE_SHIPPING_CITY}}',
                '{{CREDIT_NOTE_SHIPPING_STATE}}',
                '{{CREDIT_NOTE_SHIPPING_ZIP}}',
                '{{CREDIT_NOTE_SHIPPING_COUNTRY}}',
            ];

            $custom_fields = get_custom_fields('credit_note');

            foreach ($custom_fields as $field) {
                $placeholders[] = einvioce_custom_field_placeholder($field['slug']);
            }
            self::$placeholders = $placeholders;
        }

        return hooks()->apply_filters('before_get_einvoice_credit_note_placeholders', self::$placeholders);
    }

    public function getPlaceHolderValues(): array
    {
        $currency    = get_currency($this->creditNote->currency);
        $shipCountry = get_country($this->creditNote->shipping_country);
        $billCountry = get_country($this->creditNote->billing_country);
        $values      = [
            'CREDIT_NOTE_ID'                => encodeForXml($this->creditNote->id),
            'CREDIT_NOTE_NUMBER'            => encodeForXml(format_credit_note_number($this->creditNote->id)),
            'CREDIT_NOTE_DATE'              => encodeForXml(_d($this->creditNote->date)),
            'CREDIT_NOTE_STATUS'            => encodeForXml(format_credit_note_status($this->creditNote->status, '', false)),
            'CREDIT_NOTE_SUBTOTAL'          => encodeForXml($this->creditNote->subtotal),
            'CREDIT_NOTE_TOTAL_TAX'         => encodeForXml($this->creditNote->total_tax),
            'CREDIT_NOTE_ADJUSTMENT'        => encodeForXml($this->creditNote->adjustment),
            'CREDIT_NOTE_DISCOUNT_TOTAL'    => encodeForXml($this->creditNote->discount_total),
            'CREDIT_NOTE_TOTAL'             => encodeForXml($this->creditNote->total),
            'CURRENCY_CODE'                 => encodeForXml($currency->name),
            'CREDIT_NOTE_BILLING_ADRESS'    => encodeForXml($this->creditNote->billing_street, false),
            'CREDIT_NOTE_BILLING_CITY'      => encodeForXml($this->creditNote->billing_city, false),
            'CREDIT_NOTE_BILLING_STATE'     => encodeForXml($this->creditNote->billing_state, false),
            'CREDIT_NOTE_BILLING_ZIP'       => encodeForXml($this->creditNote->billing_zip, false),
            'INVOICE_BILLING_COUNTRY_NAME'  => encodeForXml($billCountry?->short_name, false),
            'INVOICE_BILLING_COUNTRY_ISO2'  => encodeForXml($billCountry?->iso2, false),
            'INVOICE_BILLING_COUNTRY_ISO3'  => encodeForXml($billCountry?->iso3, false),
            'CREDIT_NOTE_SHIPPING_ADRESS'   => encodeForXml($this->creditNote->shipping_street, false),
            'CREDIT_NOTE_SHIPPING_CITY'     => encodeForXml($this->creditNote->shipping_city, false),
            'CREDIT_NOTE_SHIPPING_STATE'    => encodeForXml($this->creditNote->shipping_state, false),
            'CREDIT_NOTE_SHIPPING_ZIP'      => encodeForXml($this->creditNote->shipping_zip, false),
            'INVOICE_SHIPPING_COUNTRY_NAME' => encodeForXml($shipCountry?->short_name, false),
            'INVOICE_SHIPPING_COUNTRY_ISO2' => encodeForXml($shipCountry?->iso2, false),
            'INVOICE_SHIPPING_COUNTRY_ISO3' => encodeForXml($shipCountry?->iso3, false),
        ];

        $custom_fields = get_custom_fields('credit_note');

        foreach ($custom_fields as $field) {
            $values[einvioce_custom_field_value_placeholder($field['slug'])] = encodeForXml(
                get_custom_field_value($this->creditNote->id, $field['id'], 'credit_note') ?: $field['default_value'],
                false
            );
        }

        return hooks()->apply_filters('before_get_einvoice_credit_note_placeholder_values', $values, $this->creditNote);
    }

    public function items(): array
    {
        return collect($this->creditNote->items)
            ->map(fn ($item) => (new Item($item, $this->creditNote->id, 'credit_note'))->getPlaceHolderValues())
            ->toArray();
    }

    public function customer(): array
    {
        return (new Customer($this->creditNote->client))->getPlaceHolderValues();
    }

    public function jsonSerialize(): array
    {
        $data = $this->getPlaceHolderValues();

        $data = array_merge(
            $data,
            (new Company())->getPlaceHolderValues(),
            $this->customer()
        );

        $data['LINE_ITEMS'] = $this->items();

        return $data;
    }
}

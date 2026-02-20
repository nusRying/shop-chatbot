<?php

namespace Perfexcrm\EInvoice\Data;

use app\services\utilities\Str;

class Item
{
    private static array $placeholders;

    public static function getPlaceholders(): array
    {
        if (! isset(self::$placeholders)) {
            $placeholders = [
                '{{LINE_ITEM_ID}}',
                '{{LINE_ITEM_ORDER}}',
                '{{LINE_ITEM_NAME}}',
                '{{LINE_ITEM_DESCRIPTION}}',
                '{{LINE_ITEM_QUANTITY_NUMBER}}',
                '{{LINE_ITEM_QUANTITY_UNIT}}',
                '{{LINE_ITEM_UNIT_PRICE}}',
                '{{LINE_ITEM_TOTAL}}',
            ];

            foreach (get_custom_fields('items') as $customField) {
                $placeholders[] = einvioce_custom_field_placeholder($customField['slug']);
            }
            self::$placeholders = $placeholders;
        }

        return hooks()->apply_filters('before_get_einvoice_line_items_placeholders', self::$placeholders);
    }

    public static function getTaxesPlaceholders(): array
    {
        return [
            '{{TAX_NAME}}',
            '{{TAX_RATE}}',
            '{{TAX_TOTAL}}',
        ];
    }

    /**
     * @param array{item_order: int, unit: string, rate: float, qty: int, description:string, long_description:string} $item
     */
    public function __construct(private readonly array $item, private readonly int $relId, private readonly string $relType)
    {
    }

    public function getPlaceHolderValues(): array
    {
        $values = [
            'LINE_ITEM_ID'              => encodeForXml($this->item['id'], false),
            'LINE_ITEM_ORDER'           => encodeForXml($this->item['item_order'], false),
            'LINE_ITEM_NAME'            => encodeForXml($this->item['description'], false),
            'LINE_ITEM_DESCRIPTION'     => encodeForXml(clear_textarea_breaks($this->item['long_description'])),
            'LINE_ITEM_QUANTITY_NUMBER' => encodeForXml($this->item['qty'], false),
            'LINE_ITEM_QUANTITY_UNIT'   => encodeForXml($this->item['unit']),
            'LINE_ITEM_UNIT_PRICE'      => encodeForXml(number_format($this->item['rate'], get_decimal_places())),
            'LINE_ITEM_TOTAL'           => encodeForXml(number_format($this->item['rate'] * $this->item['qty'], get_decimal_places())),
            'LINE_ITEM_TAXES'           => $this->getTaxes(),
        ];

        foreach (get_custom_fields('items') as $field) {
            $values[einvioce_custom_field_value_placeholder($field['slug'])] = encodeForXml(
                get_custom_field_value($this->item['id'], $field['id'], 'items') ?: $field['default_value'],
                false
            );
        }

        return hooks()->apply_filters('before_get_einvoice_line_items_placeholder_values', $values, $this->item);
    }

    public function getTaxes(): array
    {
        return collect(
            match ($this->relType) {
                'invoice'     => get_invoice_item_taxes($this->item['id']),
                'credit_note' => get_credit_note_item_taxes($this->item['id']),
                default       => []
            }
        )->map(fn ($tax) => [
            'TAX_NAME'  => encodeForXml(Str::before($tax['taxname'], '|'), false),
            'TAX_RATE'  => encodeForXml($tax['taxrate']),
            'TAX_TOTAL' => encodeForXml(number_format((($this->item['rate'] * $this->item['qty']) / 100) * $tax['taxrate'], get_decimal_places())),
        ])->toArray();
    }
}

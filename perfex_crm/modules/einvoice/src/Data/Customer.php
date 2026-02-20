<?php

namespace Perfexcrm\EInvoice\Data;

class Customer
{
    private static array $placeholders;
    private object|null $contact;

    public static function getPlaceholders(): array
    {
        if (! isset(self::$placeholders)) {
            $placeholders = [
                '{{CONTACT_FIRST_NAME}}',
                '{{CONTACT_LAST_NAME}}',
                '{{CONTACT_PHONE_NUMBER}}',
                '{{CONTACT_EMAIL}}',
                '{{CUSTOMER_NAME}}',
                '{{CUSTOMER_PHONE}}',
                '{{CUSTOMER_COUNTRY_NAME}}',
                '{{CUSTOMER_COUNTRY_ISO2}}',
                '{{CUSTOMER_COUNTRY_ISO3}}',
                '{{CUSTOMER_CITY}}',
                '{{CUSTOMER_ZIP}}',
                '{{CUSTOMER_STATE}}',
                '{{CUSTOMER_ADDRESS}}',
                '{{CUSTOMER_VAT_NUMBER}}',
                '{{CUSTOMER_ID}}',
            ];

            foreach (get_custom_fields('customers') as $customField) {
                $placeholders[] = einvioce_custom_field_placeholder($customField['slug']);
            }

            foreach (get_custom_fields('contacts') as $customField) {
                $placeholders[] = einvioce_custom_field_placeholder($customField['slug']);
            }
            self::$placeholders = $placeholders;
        }

        return hooks()->apply_filters('before_get_einvoice_customer_placeholders', self::$placeholders);
    }

    public function __construct(private readonly object $customer)
    {
        $contactId = get_primary_contact_user_id($this->customer->userid);
        if ($contactId) {
            $ci = &get_instance();
            $ci->db->where('userid', $this->customer->userid);
            $ci->db->where('id', $contactId);
            $this->contact = $ci->db->get(db_prefix() . 'contacts')->row();
        }
    }

    public function getPlaceHolderValues(): array
    {
        $country = get_country($this->customer->country);
        $values  = [
            'CONTACT_FIRST_NAME'    => encodeForXml($this->contact?->firstname, false),
            'CONTACT_LAST_NAME'     => encodeForXml($this->contact?->lastname, false),
            'CONTACT_PHONE_NUMBER'  => encodeForXml($this->contact?->phonenumber),
            'CONTACT_EMAIL'         => encodeForXml($this->contact?->email),
            'CUSTOMER_NAME'         => encodeForXml($this->customer->company, false),
            'CUSTOMER_PHONE'        => encodeForXml($this->customer->phonenumber),
            'CUSTOMER_COUNTRY_ISO2' => encodeForXml($country?->iso2, false),
            'CUSTOMER_COUNTRY_ISO3' => encodeForXml($country?->iso3, false),
            'CUSTOMER_COUNTRY_NAME' => encodeForXml($country?->short_name, false),
            'CUSTOMER_CITY'         => encodeForXml($this->customer->city, false),
            'CUSTOMER_ZIP'          => encodeForXml($this->customer->zip),
            'CUSTOMER_STATE'        => encodeForXml($this->customer->state, false),
            'CUSTOMER_ADDRESS'      => encodeForXml($this->customer->address, false),
            'CUSTOMER_VAT_NUMBER'   => encodeForXml($this->customer->vat),
            'CUSTOMER_ID'           => $this->customer->userid,
        ];

        foreach (get_custom_fields('contacts') as $field) {
            $values[einvioce_custom_field_value_placeholder($field['slug'])] = encodeForXml(
                get_custom_field_value($this->contact->id, $field['id'], 'contacts'),
                false
            );
        }

        foreach (get_custom_fields('customers') as $field) {
            $values[einvioce_custom_field_value_placeholder($field['slug'])] = encodeForXml(
                get_custom_field_value($this->customer->userid, $field['id'], 'customers') ?: $field['default_value'],
                false
            );
        }

        return hooks()->apply_filters('before_get_einvoice_customer_placeholder_values', $values, $this->customer);
    }
}

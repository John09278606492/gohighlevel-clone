<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\CustomValue;
use App\Models\SubAccount;

/**
 * Replaces {{ contact.first_name }}, {{ contact.custom.<key> }}, {{ business.name }},
 * {{ custom_values.<key> }}, {{ unsubscribe_url }} and friends in message text.
 */
class MergeTags
{
    public const AVAILABLE = [
        '{{ contact.first_name }}' => 'First name ("there" when empty)',
        '{{ contact.last_name }}' => 'Last name',
        '{{ contact.full_name }}' => 'Full name',
        '{{ contact.email }}' => 'Email',
        '{{ contact.phone }}' => 'Phone',
        '{{ contact.company }}' => 'Company',
        '{{ contact.custom.<key> }}' => 'A custom field',
        '{{ business.name }}' => 'Business name',
        '{{ business.phone }}' => 'Business phone',
        '{{ business.email }}' => 'Business email',
        '{{ business.website }}' => 'Business website',
        '{{ custom_values.<key> }}' => 'A custom value',
        '{{ unsubscribe_url }}' => 'Unsubscribe link',
        '{{ link.<code> }}' => 'A trigger link, tracked for this contact',
    ];

    public static function render(?string $text, ?Contact $contact, ?SubAccount $account = null): string
    {
        $text = (string) $text;

        if (! str_contains($text, '{{')) {
            return $text;
        }

        $account ??= $contact?->subAccount;
        $customValues = $account
            ? CustomValue::where('sub_account_id', $account->id)->pluck('value', 'key')->all()
            : [];

        return preg_replace_callback('/\{\{\s*([\w.\-]+)\s*\}\}/', function (array $m) use ($contact, $account, $customValues) {
            $path = $m[1];

            return match (true) {
                $path === 'contact.first_name' => $contact?->first_name ?: 'there',
                $path === 'contact.full_name' => $contact?->full_name ?? '',
                str_starts_with($path, 'contact.custom.') => (string) data_get($contact?->custom_fields, substr($path, 15), ''),
                str_starts_with($path, 'contact.') => (string) ($contact?->getAttribute(substr($path, 8)) ?? ''),
                str_starts_with($path, 'business.') => (string) ($account?->getAttribute(substr($path, 9)) ?? ''),
                str_starts_with($path, 'custom_values.') => (string) ($customValues[substr($path, 14)] ?? ''),
                $path === 'unsubscribe_url' => $contact ? $contact->unsubscribeUrl() : '',
                str_starts_with($path, 'link.') => self::link(substr($path, 5), $contact, $account),
                default => $m[0],
            };
        }, $text);
    }

    private static function link(string $code, ?Contact $contact, ?SubAccount $account): string
    {
        $link = $account?->triggerLinks()->where('code', $code)->first();

        return $link ? $link->shortUrl($contact) : '';
    }
}

<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\CustomField;
use App\Models\SubAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Contact CSV import (matched by email/phone) and export. */
class ContactCsv
{
    private const ALIASES = [
        'first_name' => ['first_name', 'firstname', 'first', 'given_name'],
        'last_name' => ['last_name', 'lastname', 'last', 'surname', 'family_name'],
        'full_name' => ['name', 'full_name', 'fullname', 'contact_name'],
        'email' => ['email', 'email_address', 'e_mail'],
        'phone' => ['phone', 'phone_number', 'mobile', 'mobile_phone', 'cell'],
        'company' => ['company', 'company_name', 'business', 'organization'],
        'source' => ['source', 'lead_source'],
        'type' => ['type', 'contact_type'],
        'address' => ['address', 'address1', 'street'],
        'city' => ['city', 'town'],
        'country' => ['country'],
        'tags' => ['tags', 'tag', 'labels'],
    ];

    /** @return array{created: int, updated: int, skipped: int} */
    public static function import(SubAccount $account, string $path, array $extraTags = []): array
    {
        $handle = fopen($path, 'r');
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        if (! $handle) {
            return $result;
        }

        $header = fgetcsv($handle) ?: [];
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($header[0] ?? '')); // UTF-8 BOM
        $map = self::mapHeader($header, CustomField::where('sub_account_id', $account->id)->pluck('key')->all());

        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $attributes = [];
            $custom = [];
            $tags = $extraTags;

            foreach ($map as $index => $target) {
                $value = trim((string) ($row[$index] ?? ''));
                if ($value === '') {
                    continue;
                }

                match (true) {
                    $target === 'tags' => $tags = array_merge($tags, array_map('trim', preg_split('/[,;|]/', $value))),
                    $target === 'full_name' => [$attributes['first_name'], $attributes['last_name']] = array_pad(explode(' ', $value, 2), 2, null),
                    str_starts_with($target, 'custom:') => $custom[substr($target, 7)] = $value,
                    $target === 'email' => $attributes['email'] = strtolower($value),
                    default => $attributes[$target] = $value,
                };
            }

            if (empty($attributes['email']) && empty($attributes['phone'])) {
                $result['skipped']++;

                continue;
            }

            if (isset($attributes['email']) && ! filter_var($attributes['email'], FILTER_VALIDATE_EMAIL)) {
                unset($attributes['email']);
                if (empty($attributes['phone'])) {
                    $result['skipped']++;

                    continue;
                }
            }

            $attributes['source'] ??= 'CSV import';
            $contact = Contact::upsertFor($account, $attributes);

            if ($custom) {
                $contact->forceFill(['custom_fields' => array_merge((array) $contact->custom_fields, $custom)])->saveQuietly();
            }

            if ($tags = array_values(array_filter(array_unique($tags)))) {
                $contact->attachTags($tags);
            }

            $contact->wasRecentlyCreated ? $result['created']++ : $result['updated']++;
        }

        fclose($handle);

        return $result;
    }

    /** @return array<int, string> column index => contact attribute ("custom:<key>" for custom fields) */
    private static function mapHeader(array $header, array $customKeys): array
    {
        $map = [];

        foreach ($header as $i => $label) {
            $key = Str::snake(Str::ascii(trim((string) $label)));
            $key = str_replace('__', '_', $key);

            foreach (self::ALIASES as $attribute => $aliases) {
                if (in_array($key, $aliases, true)) {
                    $map[$i] = $attribute;

                    continue 2;
                }
            }

            if (in_array($key, $customKeys, true)) {
                $map[$i] = 'custom:'.$key;
            }
        }

        return $map;
    }

    public static function export(Collection $contacts, SubAccount $account): StreamedResponse
    {
        $fields = CustomField::where('sub_account_id', $account->id)->orderBy('sort')->get();
        $filename = Str::slug($account->name).'-contacts-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($contacts, $fields) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(
                ['id', 'first_name', 'last_name', 'email', 'phone', 'company', 'type', 'source', 'address', 'city', 'country', 'tags', 'dnd', 'created_at'],
                $fields->pluck('key')->all(),
            ));

            foreach ($contacts as $c) {
                fputcsv($out, array_merge(
                    [$c->id, $c->first_name, $c->last_name, $c->email, $c->phone, $c->company, $c->type, $c->source, $c->address, $c->city, $c->country, implode(', ', $c->tagNames()), $c->dnd ? 'yes' : 'no', $c->created_at?->toDateTimeString()],
                    $fields->map(fn ($f) => data_get($c->custom_fields, $f->key))->all(),
                ));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}

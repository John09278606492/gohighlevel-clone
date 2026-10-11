<?php

namespace App\Support;

use App\Models\Contact;
use Illuminate\Support\Facades\DB;
use Spatie\Tags\Tag;

/** Tag names used by the current sub-account's contacts, so one client never sees another's tags. */
class TenantTags
{
    /** @return array<string, string> */
    public static function options(?int $subAccountId = null): array
    {
        $subAccountId ??= Access::tenantId();

        $ids = DB::table('taggables')
            ->join('contacts', 'contacts.id', '=', 'taggables.taggable_id')
            ->where('taggables.taggable_type', (new Contact)->getMorphClass())
            ->where('contacts.sub_account_id', $subAccountId)
            ->distinct()
            ->pluck('taggables.tag_id');

        return Tag::whereIn('id', $ids)->get()
            ->map(fn (Tag $tag) => $tag->name)
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->mapWithKeys(fn ($name) => [$name => $name])
            ->all();
    }
}

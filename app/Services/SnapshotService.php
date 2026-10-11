<?php

namespace App\Services;

use App\Models\Calendar;
use App\Models\Course;
use App\Models\CustomField;
use App\Models\CustomValue;
use App\Models\Funnel;
use App\Models\Pipeline;
use App\Models\Product;
use App\Models\SmartList;
use App\Models\Snapshot;
use App\Models\SubAccount;
use App\Models\TriggerLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Packstub\Flow\Facades\Flow;
use Packstub\Flow\Models\Workflow;
use Packstub\FormBuilder\Models\Form;

/**
 * GoHighLevel snapshots: copy a sub-account's setup (pipelines, calendars, funnels,
 * forms, workflows, custom fields, products, courses...) but never its contacts,
 * conversations or money. Loading a snapshot adds to the target; it never deletes.
 */
class SnapshotService
{
    public const ASSETS = [
        'custom_fields' => 'Custom fields and custom values',
        'pipelines' => 'Pipelines and stages',
        'calendars' => 'Calendars',
        'funnels' => 'Funnels, websites and pages',
        'forms' => 'Forms and surveys',
        'workflows' => 'Workflows (imported switched off)',
        'smart_lists' => 'Smart lists',
        'trigger_links' => 'Trigger links',
        'products' => 'Products',
        'courses' => 'Courses and lessons',
    ];

    public static function capture(SubAccount $account, string $name, array $assets, ?string $description = null): Snapshot
    {
        $payload = [];
        $has = fn (string $asset) => in_array($asset, $assets, true);

        if ($has('custom_fields')) {
            $payload['custom_fields'] = CustomField::where('sub_account_id', $account->id)->get()->map->only(['name', 'key', 'type', 'options', 'placeholder', 'is_required', 'sort'])->all();
            $payload['custom_values'] = CustomValue::where('sub_account_id', $account->id)->get()->map->only(['key', 'value'])->all();
        }

        if ($has('pipelines')) {
            $payload['pipelines'] = Pipeline::with('stages')->where('sub_account_id', $account->id)->get()
                ->map(fn ($p) => ['name' => $p->name, 'stages' => $p->stages->map->only(['name', 'color', 'sort'])->all()])->all();
        }

        if ($has('calendars')) {
            $payload['calendars'] = Calendar::where('sub_account_id', $account->id)->get()
                ->map->only(['name', 'slug', 'description', 'color', 'duration_minutes', 'slot_interval_minutes', 'minimum_notice_minutes', 'booking_window_days', 'working_hours', 'timezone', 'confirmation_message', 'is_active'])->all();
        }

        if ($has('funnels')) {
            $payload['funnels'] = Funnel::with('pages')->where('sub_account_id', $account->id)->get()
                ->map(fn ($f) => ['name' => $f->name, 'type' => $f->type, 'pages' => $f->pages->map->only(['title', 'slug', 'step_order', 'blocks', 'seo_title', 'seo_description', 'is_published'])->all()])->all();
        }

        if ($has('forms')) {
            $payload['forms'] = Form::query()->withoutGlobalScopes()->where('tenant_id', $account->id)->get()
                ->map->only(['name', 'slug', 'description', 'fields', 'is_active', 'submit_label', 'success_message', 'redirect_url', 'store_submissions', 'settings'])->all();
        }

        if ($has('workflows')) {
            $payload['workflows'] = Workflow::query()->withoutGlobalScopes()
                ->where('tenant_type', SubAccount::class)->where('tenant_id', (string) $account->id)->get()
                ->map(fn (Workflow $w) => Flow::export($w))->all();
        }

        if ($has('smart_lists')) {
            $payload['smart_lists'] = SmartList::where('sub_account_id', $account->id)->where('is_shared', true)->get()->map->only(['name', 'criteria', 'sort'])->all();
        }

        if ($has('trigger_links')) {
            $payload['trigger_links'] = TriggerLink::where('sub_account_id', $account->id)->get()->map->only(['name', 'url', 'add_tags'])->all();
        }

        if ($has('products')) {
            $payload['products'] = Product::where('sub_account_id', $account->id)->get()->map->only(['name', 'description', 'price', 'billing', 'is_active'])->all();
        }

        if ($has('courses')) {
            $payload['courses'] = Course::with('lessons')->where('sub_account_id', $account->id)->get()
                ->map(fn ($c) => $c->only(['title', 'description', 'is_published']) + ['lessons' => $c->lessons->map->only(['module', 'title', 'content', 'video_url', 'sort', 'is_preview'])->all()])->all();
        }

        return Snapshot::create([
            'name' => $name,
            'description' => $description,
            'source_sub_account_id' => $account->id,
            'contents' => array_values(array_intersect(array_keys(self::ASSETS), $assets)),
            'payload' => $payload,
        ]);
    }

    /** @return array<string, int> how many of each asset were created */
    public static function apply(Snapshot $snapshot, SubAccount $target): array
    {
        $p = $snapshot->payload ?? [];
        $created = [];
        $bump = function (string $key, int $n = 1) use (&$created) {
            $created[$key] = ($created[$key] ?? 0) + $n;
        };

        DB::transaction(function () use ($p, $target, $bump, $snapshot) {
            foreach ($p['custom_fields'] ?? [] as $field) {
                CustomField::firstOrCreate(['sub_account_id' => $target->id, 'key' => $field['key']], $field) && $bump('custom_fields');
            }
            foreach ($p['custom_values'] ?? [] as $value) {
                CustomValue::firstOrCreate(['sub_account_id' => $target->id, 'key' => $value['key']], $value);
            }

            // Forms first, so form shortcodes on copied pages can be pointed at the new slugs.
            $formSlugs = [];
            $sourceSlug = $snapshot->source?->slug;
            foreach ($p['forms'] ?? [] as $form) {
                $old = $form['slug'];
                $suffix = $sourceSlug && str_starts_with($old, $sourceSlug.'-') ? substr($old, strlen($sourceSlug) + 1) : $old;
                $form['slug'] = self::freeSlug(Form::query()->withoutGlobalScopes(), $target->slug.'-'.$suffix);
                Form::query()->withoutGlobalScopes()->create($form + ['tenant_id' => $target->id]);
                $formSlugs[$old] = $form['slug'];
                $bump('forms');
            }

            foreach ($p['pipelines'] ?? [] as $pipeline) {
                $name = Pipeline::where('sub_account_id', $target->id)->where('name', $pipeline['name'])->exists() ? $pipeline['name'].' (snapshot)' : $pipeline['name'];
                $new = Pipeline::create(['sub_account_id' => $target->id, 'name' => $name]);
                foreach ($pipeline['stages'] as $stage) {
                    $new->stages()->create($stage);
                }
                $bump('pipelines');
            }

            foreach ($p['calendars'] ?? [] as $calendar) {
                $calendar['slug'] = self::freeSlug(Calendar::where('sub_account_id', $target->id), $calendar['slug']);
                $calendar['timezone'] = $target->timezone ?: $calendar['timezone'];
                Calendar::create($calendar + ['sub_account_id' => $target->id]);
                $bump('calendars');
            }

            foreach ($p['funnels'] ?? [] as $funnel) {
                $new = Funnel::create(['sub_account_id' => $target->id, 'name' => $funnel['name'], 'type' => $funnel['type']]);
                foreach ($funnel['pages'] as $page) {
                    $page['slug'] = self::freeSlug(\App\Models\Page::where('sub_account_id', $target->id), $page['slug']);
                    $page['blocks'] = self::retarget($page['blocks'] ?? null, $formSlugs);
                    $new->pages()->create($page + ['sub_account_id' => $target->id]);
                }
                $bump('funnels');
            }

            foreach ($p['workflows'] ?? [] as $workflow) {
                Flow::import($workflow, ['tenant_type' => SubAccount::class, 'tenant_id' => (string) $target->id, 'is_active' => false]);
                $bump('workflows');
            }

            foreach ($p['smart_lists'] ?? [] as $list) {
                SmartList::create($list + ['sub_account_id' => $target->id, 'is_shared' => true]);
                $bump('smart_lists');
            }

            foreach ($p['trigger_links'] ?? [] as $link) {
                TriggerLink::create($link + ['sub_account_id' => $target->id]);
                $bump('trigger_links');
            }

            foreach ($p['products'] ?? [] as $product) {
                Product::create($product + ['sub_account_id' => $target->id]);
                $bump('products');
            }

            foreach ($p['courses'] ?? [] as $course) {
                $lessons = $course['lessons'] ?? [];
                unset($course['lessons']);
                $new = Course::create($course + ['sub_account_id' => $target->id]);
                foreach ($lessons as $lesson) {
                    $new->lessons()->create($lesson);
                }
                $bump('courses');
            }
        });

        return $created;
    }

    private static function freeSlug($query, string $slug): string
    {
        $base = $slug;
        $i = 2;
        while ((clone $query)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /** Point [form slug="..."] shortcodes on copied pages at the forms created for the target. */
    private static function retarget(mixed $blocks, array $formSlugs): mixed
    {
        if (! $blocks || ! $formSlugs) {
            return $blocks;
        }

        $json = json_encode($blocks);
        foreach ($formSlugs as $old => $new) {
            $json = str_replace(['slug=\\"'.$old.'\\"', "slug='".$old."'", 'slug=&quot;'.$old.'&quot;'], ['slug=\\"'.$new.'\\"', "slug='".$new."'", 'slug=&quot;'.$new.'&quot;'], $json);
        }

        return json_decode($json, true);
    }
}

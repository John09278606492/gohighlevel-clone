<?php

namespace App\PageBuilder;

use App\Models\Calendar;
use App\Models\Page;
use App\Models\Review;
use Illuminate\Support\HtmlString;
use Packstub\FormBuilder\Models\Form;

/**
 * Shortcodes funnel pages can use in the page builder's Shortcode block.
 * The public page controller binds the page being rendered as "funnels.page".
 */
class Shortcodes
{
    protected static function page(): ?Page
    {
        return app()->bound('funnels.page') ? app('funnels.page') : null;
    }

    /** [form slug="contact-us"] renders a Form Builder form of the page's sub-account. */
    public static function form(array $attributes = []): HtmlString|string
    {
        $slug = (string) ($attributes['slug'] ?? '');
        $page = static::page();

        $exists = Form::query()->withoutGlobalScopes()
            ->where('slug', $slug)
            ->when($page, fn ($q) => $q->where(config('packstub-form-builder.tenancy.column', 'tenant_id'), $page->sub_account_id))
            ->exists();

        if (! $exists) {
            return new HtmlString('<p class="ghl-missing">Form "'.e($slug).'" not found.</p>');
        }

        return new HtmlString(view('shortcodes.form', ['slug' => $slug])->render());
    }

    /** [booking calendar="discovery-call"] embeds the calendar's booking widget. */
    public static function booking(array $attributes = []): HtmlString|string
    {
        $page = static::page();
        $calendar = Calendar::query()
            ->where('slug', (string) ($attributes['calendar'] ?? ''))
            ->when($page, fn ($q) => $q->where('sub_account_id', $page->sub_account_id))
            ->first();

        if (! $calendar) {
            return new HtmlString('<p class="ghl-missing">Calendar not found.</p>');
        }

        return new HtmlString(view('shortcodes.booking', ['calendar' => $calendar])->render());
    }

    /** [next_step label="Continue"] links to the next published step of the page's funnel. */
    public static function nextStep(array $attributes = []): HtmlString|string
    {
        $next = static::page()?->nextStep();

        if (! $next) {
            return '';
        }

        return new HtmlString(sprintf(
            '<p style="text-align:center"><a class="ghl-button" href="%s">%s</a></p>',
            e($next->publicUrl()),
            e($attributes['label'] ?? 'Continue'),
        ));
    }

    /** [reviews limit="6" min="4"] shows recent public reviews of the page's sub-account. */
    public static function reviews(array $attributes = []): HtmlString|string
    {
        $page = static::page();

        if (! $page) {
            return '';
        }

        $reviews = Review::where('sub_account_id', $page->sub_account_id)
            ->where('is_public', true)
            ->where('rating', '>=', (int) ($attributes['min'] ?? 4))
            ->whereNotNull('comment')
            ->latest()
            ->limit(min(24, (int) ($attributes['limit'] ?? 6)))
            ->get();

        return new HtmlString(view('shortcodes.reviews', ['reviews' => $reviews])->render());
    }

    /** [chat] loads the website chat widget on this page. */
    public static function chat(array $attributes = []): HtmlString|string
    {
        $account = static::page()?->subAccount;

        return $account && $account->chat_widget_enabled ? new HtmlString($account->chatWidgetSnippet()) : '';
    }
}

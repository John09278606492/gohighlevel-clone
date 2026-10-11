<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Course;
use App\Models\CustomField;
use App\Models\CustomValue;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewRequest;
use App\Models\SmartList;
use App\Models\SocialPost;
use App\Models\TriggerLink;
use App\Models\Contact;
use App\Models\Funnel;
use App\Models\Message;
use App\Models\Opportunity;
use App\Models\SubAccount;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Packstub\Flow\Facades\Flow;
use Packstub\FormBuilder\Models\Form;

/**
 * Demo data: one agency admin, two client sub-accounts with contacts,
 * a pipeline full of opportunities, appointments, conversations, a form and a funnel.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(\FinityLabs\FinMail\Database\Seeders\EmailTemplateSeeder::class);

        Flow::suppress(function (): void {
            $admin = User::create([
                'name' => 'Agency Admin',
                'email' => 'admin@example.com',
                'password' => 'password',
                'is_agency_admin' => true,
            ]);

            $staff = User::create([
                'name' => 'Sam Staff',
                'email' => 'staff@example.com',
                'password' => 'password',
            ]);

            $dental = SubAccount::create(['name' => 'Bright Smile Dental', 'email' => 'hello@brightsmile.test', 'timezone' => 'America/New_York']);
            $roofing = SubAccount::create(['name' => 'Peak Roofing Co', 'email' => 'office@peakroofing.test', 'timezone' => 'America/Chicago']);

            $dental->users()->attach($admin, ['role' => 'admin']);
            $dental->users()->attach($staff, ['role' => 'user']);
            $roofing->users()->attach($admin, ['role' => 'admin']);

            foreach ([$dental, $roofing] as $account) {
                $this->seedAccount($account, $admin);
            }
        });
    }

    private function seedAccount(SubAccount $account, User $owner): void
    {
        $faker = fake();
        $pipeline = $account->pipelines()->with('stages')->first();
        $calendar = $account->calendars()->first();
        $sources = ['Website form', 'Facebook ad', 'Google ads', 'Referral', 'Booking: Discovery Call'];
        $tags = ['new-lead', 'vip', 'follow-up', 'newsletter', 'hot'];

        $contacts = collect(range(1, 24))->map(function (int $i) use ($account, $faker, $sources, $tags, $owner) {
            $contact = Contact::create([
                'sub_account_id' => $account->id,
                'first_name' => $faker->firstName(),
                'last_name' => $faker->lastName(),
                'email' => $faker->unique()->safeEmail(),
                'phone' => $faker->numerify('+1 (###) ###-####'),
                'company' => $faker->boolean(40) ? $faker->company() : null,
                'type' => $faker->boolean(30) ? 'customer' : 'lead',
                'source' => $faker->randomElement($sources),
                'owner_id' => $owner->id,
                'city' => $faker->city(),
                'created_at' => Carbon::now()->subDays($faker->numberBetween(0, 45)),
                'last_activity_at' => Carbon::now()->subDays($faker->numberBetween(0, 10)),
            ]);
            $contact->attachTags($faker->randomElements($tags, $faker->numberBetween(0, 2)));
            $contact->notes()->create(['body' => 'Initial enquiry via '.$contact->source.'.', 'user_id' => $owner->id]);

            return $contact;
        });

        foreach ($contacts->take(16) as $i => $contact) {
            $stage = $pipeline->stages[$i % $pipeline->stages->count()];
            Opportunity::create([
                'sub_account_id' => $account->id,
                'pipeline_id' => $pipeline->id,
                'pipeline_stage_id' => $stage->id,
                'contact_id' => $contact->id,
                'owner_id' => $owner->id,
                'name' => $contact->full_name.' - '.$faker->randomElement(['Consultation', 'Full package', 'Repair', 'Upgrade']),
                'value' => $faker->randomElement([500, 1200, 2500, 4800, 9000]),
                'status' => $stage->sort === 4 ? $faker->randomElement(['won', 'won', 'lost']) : 'open',
                'source' => $contact->source,
            ]);
        }

        foreach ($contacts->take(10) as $i => $contact) {
            $start = Carbon::now($calendar->timezone)->addDays($i - 3)->setTime(9 + ($i % 7), 0)->utc();
            Appointment::create([
                'sub_account_id' => $account->id,
                'calendar_id' => $calendar->id,
                'contact_id' => $contact->id,
                'user_id' => $owner->id,
                'title' => $calendar->name.' with '.$contact->full_name,
                'starts_at' => $start,
                'ends_at' => $start->copy()->addMinutes($calendar->duration_minutes),
                'status' => $start->isPast() ? $faker->randomElement(['showed', 'no_show']) : 'booked',
            ]);
        }

        foreach ($contacts->take(8) as $i => $contact) {
            $conversation = $contact->conversationOrCreate();
            Message::create(['conversation_id' => $conversation->id, 'channel' => 'sms', 'direction' => 'inbound', 'body' => $faker->randomElement([
                'Hi, do you have any openings this week?', 'What are your prices?', 'Can I reschedule my appointment?', 'Thanks for the quick reply!',
            ]), 'status' => 'received']);
            if ($i % 2 === 0) {
                Message::create(['conversation_id' => $conversation->id, 'user_id' => $owner->id, 'channel' => 'sms', 'direction' => 'outbound', 'body' => 'Hi '.$contact->first_name.'! Yes, we do. Want me to send you a booking link?', 'status' => 'sent']);
                $conversation->update(['unread_count' => 0]);
            }
        }

        $form = Form::query()->withoutGlobalScopes()->create([
            'name' => 'Free consultation request',
            'slug' => $account->slug.'-consultation',
            'tenant_id' => $account->id,
            'user_id' => $owner->id,
            'success_message' => 'Thanks! We will be in touch shortly.',
            'fields' => [
                ['type' => 'text', 'data' => ['label' => 'Full name', 'key' => 'full_name', 'required' => true, 'width' => 'half']],
                ['type' => 'email', 'data' => ['label' => 'Email', 'key' => 'email', 'required' => true, 'width' => 'half']],
                ['type' => 'phone', 'data' => ['label' => 'Phone', 'key' => 'phone', 'width' => 'half']],
                ['type' => 'textarea', 'data' => ['label' => 'How can we help?', 'key' => 'message', 'rows' => 4]],
            ],
        ]);

        $funnel = Funnel::create(['sub_account_id' => $account->id, 'name' => 'Free consultation funnel']);
        $funnel->pages()->create([
            'sub_account_id' => $account->id,
            'title' => 'Book your free consultation',
            'slug' => 'free-consultation',
            'step_order' => 1,
            'is_published' => true,
            'blocks' => [
                ['type' => 'heading', 'data' => ['text' => $account->name.': your free consultation', 'level' => 'h1']],
                ['type' => 'text', 'data' => ['body' => 'Tell us a little about what you need and we will get back to you within one business day.']],
                ['type' => 'shortcode', 'data' => ['code' => '[form slug="'.$form->slug.'"]']],
                ['type' => 'shortcode', 'data' => ['code' => '[next_step label="Prefer to book a time now?"]']],
            ],
        ]);
        $funnel->pages()->create([
            'sub_account_id' => $account->id,
            'title' => 'Pick a time',
            'slug' => 'book',
            'step_order' => 2,
            'is_published' => true,
            'blocks' => [
                ['type' => 'heading', 'data' => ['text' => 'Pick a time that suits you', 'level' => 'h2']],
                ['type' => 'shortcode', 'data' => ['code' => '[booking calendar="'.$calendar->slug.'"]']],
            ],
        ]);

        $this->seedGrowth($account, $contacts, $owner, $pipeline);
    }

    /** Demo data for the marketing, payments, reputation and membership features. */
    private function seedGrowth(SubAccount $account, $contacts, User $owner, $pipeline): void
    {
        $faker = fake();
        $isDental = str_contains($account->slug, 'dental');

        $account->update([
            'google_review_url' => 'https://search.google.com/local/writereview?placeid=demo',
            'chat_widget_greeting' => 'Hi! Questions about '.$account->name.'? We usually reply within minutes.',
            'default_tax_rate' => 8.25,
            'invoice_footer' => 'Thank you for your business! Payment by bank transfer: ACME Bank 000-111-222.',
        ]);

        // Custom fields and values
        $fields = $isDental
            ? [['Insurance provider', 'insurance_provider', 'select', ['Delta Dental', 'Cigna', 'MetLife', 'None']], ['Last cleaning', 'last_cleaning', 'date', null]]
            : [['Roof age (years)', 'roof_age', 'number', null], ['Roof type', 'roof_type', 'select', ['Asphalt shingle', 'Metal', 'Tile', 'Flat']]];
        foreach ($fields as $i => [$name, $key, $type, $options]) {
            CustomField::create(['sub_account_id' => $account->id, 'name' => $name, 'key' => $key, 'type' => $type, 'options' => $options, 'sort' => $i]);
        }
        CustomValue::create(['sub_account_id' => $account->id, 'key' => 'promo_code', 'value' => $isDental ? 'SMILE20' : 'ROOF10']);
        foreach ($contacts->take(12) as $contact) {
            $contact->forceFill(['custom_fields' => $isDental
                ? ['insurance_provider' => $faker->randomElement(['Delta Dental', 'Cigna', 'MetLife', 'None'])]
                : ['roof_age' => $faker->numberBetween(2, 30), 'roof_type' => $faker->randomElement(['Asphalt shingle', 'Metal', 'Tile'])]])->saveQuietly();
        }

        // Smart lists
        SmartList::create(['sub_account_id' => $account->id, 'user_id' => $owner->id, 'name' => 'Hot leads', 'criteria' => ['tags_any' => ['hot', 'vip'], 'type' => 'lead']]);
        SmartList::create(['sub_account_id' => $account->id, 'user_id' => $owner->id, 'name' => 'Customers', 'criteria' => ['type' => 'customer', 'exclude_dnd' => true], 'sort' => 1]);
        SmartList::create(['sub_account_id' => $account->id, 'user_id' => $owner->id, 'name' => 'Gone quiet (14 days)', 'criteria' => ['inactive_for_days' => 14], 'sort' => 2]);

        // Products, invoices and payments
        $products = collect($isDental
            ? [['Teeth whitening', 350], ['Cleaning & exam', 180], ['Invisalign consult', 0]]
            : [['Roof inspection', 199], ['Shingle repair (per sq)', 450], ['Gutter cleaning', 150]])
            ->map(fn ($p) => Product::create(['sub_account_id' => $account->id, 'name' => $p[0], 'price' => $p[1]]));

        foreach ($contacts->slice(2, 6)->values() as $i => $contact) {
            $product = $products[$i % $products->count()];
            $invoice = Invoice::create([
                'sub_account_id' => $account->id,
                'contact_id' => $contact->id,
                'title' => $product->name,
                'status' => 'sent',
                'issue_date' => now()->subDays(20 - $i * 3),
                'due_date' => now()->subDays(20 - $i * 3)->addDays(14),
                'tax_rate' => 8.25,
                'sent_at' => now()->subDays(20 - $i * 3),
            ]);
            $invoice->items()->create(['product_id' => $product->id, 'description' => $product->name, 'quantity' => 1 + $i % 2, 'unit_price' => max(99, (float) $product->price)]);
            $invoice->recalculate();
            if ($i < 4) {
                $invoice->recordPayment((float) $invoice->total, $faker->randomElement(['card', 'bank_transfer', 'cash']), 'DEMO-'.$account->id.'-'.$i);
            }
        }

        // Reviews
        foreach ($contacts->slice(8, 7)->values() as $i => $contact) {
            $rating = [5, 5, 4, 5, 3, 5, 2][$i];
            $request = ReviewRequest::create(['sub_account_id' => $account->id, 'contact_id' => $contact->id, 'channel' => 'sms', 'sent_at' => now()->subDays(10 - $i), 'opened_at' => now()->subDays(9 - $i), 'completed_at' => now()->subDays(9 - $i)]);
            Review::create([
                'sub_account_id' => $account->id,
                'contact_id' => $contact->id,
                'review_request_id' => $request->id,
                'rating' => $rating,
                'author_name' => $contact->full_name,
                'is_public' => $rating >= 4,
                'comment' => $rating >= 4
                    ? $faker->randomElement(['Friendly team and quick service!', 'Highly recommend, very professional.', 'Best experience I\'ve had, thank you!', 'On time and great value.'])
                    : 'Had to wait longer than expected.',
                'created_at' => now()->subDays(9 - $i),
            ]);
        }

        // A proposal waiting for signature
        $opportunity = Opportunity::where('sub_account_id', $account->id)->where('status', 'open')->first();
        Document::create([
            'sub_account_id' => $account->id,
            'contact_id' => $opportunity?->contact_id ?? $contacts->first()->id,
            'opportunity_id' => $opportunity?->id,
            'title' => $isDental ? 'Treatment plan & estimate' : 'Roof replacement proposal',
            'amount' => $opportunity?->value ?? 2500,
            'status' => 'sent',
            'sent_at' => now()->subDay(),
            'expires_on' => now()->addDays(14),
            'body' => '<h2>Scope</h2><p>'.($isDental ? 'Full exam, cleaning and whitening over two visits.' : 'Tear-off and replacement with architectural shingles, new underlayment and flashing.').'</p><h2>Investment</h2><p>See the amount above. 50% deposit on signing, balance on completion.</p><h2>Terms</h2><p>Valid for 14 days.</p>',
        ]);

        // Membership course
        $course = Course::create([
            'sub_account_id' => $account->id,
            'title' => $isDental ? 'Healthy Smile Academy' : 'Homeowner Roof Care 101',
            'description' => $isDental ? 'Short videos on caring for your teeth between visits.' : 'Keep your roof in shape between inspections.',
            'is_published' => true,
        ]);
        foreach (['Welcome', 'The basics', 'Common mistakes', 'Your checklist'] as $i => $title) {
            $course->lessons()->create([
                'module' => $i < 2 ? 'Module 1: Getting started' : 'Module 2: Going further',
                'title' => $title,
                'video_url' => $i === 0 ? 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' : null,
                'content' => '<p>Lesson content for "'.$title.'". Replace this with your own material.</p>',
                'sort' => $i,
            ]);
        }
        $products->first()->update(['course_id' => $course->id]);
        $course->enroll($contacts[2], 'purchase');

        // Marketing
        $link = TriggerLink::create(['sub_account_id' => $account->id, 'name' => 'Spring offer', 'url' => $account->website ?: 'https://example.com/offer', 'add_tags' => ['clicked-offer']]);
        $campaign = Campaign::create([
            'sub_account_id' => $account->id,
            'user_id' => $owner->id,
            'name' => 'Spring newsletter',
            'channel' => 'email',
            'subject' => 'Spring news from '.$account->name,
            'body' => '<p>Hi {{ contact.first_name }},</p><p>Spring is here! Use code <strong>{{ custom_values.promo_code }}</strong> this month.</p><p><a href="https://example.com/offer">See the offer</a></p>',
            'tags' => ['newsletter'],
            'status' => 'sent',
            'sent_at' => now()->subDays(5),
        ]);
        $recipients = $contacts->take(10);
        foreach ($recipients as $i => $contact) {
            CampaignRecipient::create([
                'campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'status' => 'sent', 'sent_at' => now()->subDays(5),
                'opened_at' => $i < 6 ? now()->subDays(4) : null, 'clicked_at' => $i < 2 ? now()->subDays(4) : null,
            ]);
        }
        $campaign->update(['recipients_count' => 10, 'sent_count' => 10, 'opened_count' => 6, 'clicked_count' => 2]);
        Campaign::create([
            'sub_account_id' => $account->id, 'user_id' => $owner->id, 'name' => 'Re-engagement SMS', 'channel' => 'sms',
            'body' => 'Hi {{ contact.first_name }}, it\'s been a while! Book your next visit: {{ link.'.$link->code.' }}',
            'smart_list_id' => SmartList::where('sub_account_id', $account->id)->where('name', 'Gone quiet (14 days)')->value('id'),
            'status' => 'draft',
        ]);

        foreach ([[1, 'scheduled'], [3, 'scheduled'], [-2, 'published']] as [$days, $status]) {
            SocialPost::create([
                'sub_account_id' => $account->id,
                'user_id' => $owner->id,
                'content' => $faker->randomElement(['Spring special: book this week and save!', 'Meet our team 👋', 'Tip of the week from the experts.', '5-star review from a happy customer ⭐']),
                'platforms' => ['facebook', 'instagram'],
                'status' => $status,
                'scheduled_at' => now()->addDays($days)->setTime(9, 0),
                'published_at' => $status === 'published' ? now()->addDays($days)->setTime(9, 0) : null,
            ]);
        }
    }
}

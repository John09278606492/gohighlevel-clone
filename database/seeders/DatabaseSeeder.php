<?php

namespace Database\Seeders;

use App\Models\Appointment;
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
    }
}

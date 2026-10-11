<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Calendar;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Page;
use App\Models\SubAccount;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Loads every screen with the demo data and checks it renders. */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->first();
    }

    public function test_sub_account_screens_render(): void
    {
        $account = SubAccount::where('slug', 'bright-smile-dental')->firstOrFail();
        $contact = $account->contacts()->first();
        $conversation = Conversation::where('sub_account_id', $account->id)->first();
        $page = Page::where('sub_account_id', $account->id)->first();
        $calendar = $account->calendars()->first();
        $pipeline = $account->pipelines()->first();
        $funnel = $account->funnels()->first();

        $this->actingAs($this->admin());

        foreach ([
            '',
            '/contacts',
            '/contacts/create',
            "/contacts/{$contact->id}",
            "/contacts/{$contact->id}/edit",
            '/conversations',
            "/conversations/{$conversation->id}",
            '/tasks',
            '/pipeline',
            '/opportunities',
            '/pipelines',
            "/pipelines/{$pipeline->id}/edit",
            '/calendar',
            '/appointments',
            '/calendars',
            "/calendars/{$calendar->id}/edit",
            '/funnels',
            "/funnels/{$funnel->id}/edit",
            '/pages',
            "/pages/{$page->id}/edit",
            "/pages/{$page->id}/design",
            '/forms',
            '/workflows',
            '/workflows/create',
            '/profile',
        ] as $path) {
            $response = $this->get("/app/{$account->slug}{$path}");
            $this->assertSame(200, $response->status(), "GET /app/{$account->slug}{$path} returned {$response->status()}");
        }
    }

    public function test_agency_screens_render(): void
    {
        $this->actingAs($this->admin());

        foreach (['', '/sub-accounts', '/sub-accounts/create', '/users', '/email-templates', '/sent-emails'] as $path) {
            $this->get("/agency{$path}")->assertOk();
        }
    }

    public function test_opening_a_sub_account_is_remembered(): void
    {
        $this->actingAs($this->admin());

        $account = SubAccount::where('slug', 'peak-roofing-co')->firstOrFail();

        $this->get('/app/peak-roofing-co')->assertOk()
            ->assertSee('Agency view')
            ->assertSessionHas('last_sub_account_id', $account->id);
    }

    public function test_agency_shows_back_to_last_sub_account_link(): void
    {
        $this->actingAs($this->admin());
        $account = SubAccount::where('slug', 'peak-roofing-co')->firstOrFail();

        $this->withSession(['last_sub_account_id' => $account->id])
            ->get('/agency')->assertOk()
            ->assertSee('Back to Peak Roofing Co')
            ->assertSee(url('/app/peak-roofing-co'), false);
    }

    public function test_staff_cannot_open_agency_or_other_sub_accounts(): void
    {
        $staff = User::where('email', 'staff@example.com')->first();
        $this->actingAs($staff);

        $this->get('/agency')->assertForbidden();
        $this->get('/app/peak-roofing-co')->assertNotFound();
        $this->get('/app/bright-smile-dental')->assertOk();
    }

    public function test_public_booking_and_funnel_pages_render(): void
    {
        $account = SubAccount::where('slug', 'bright-smile-dental')->firstOrFail();

        $this->get("/book/{$account->slug}/discovery-call")->assertOk()->assertSee('Discovery Call');
        $this->get("/p/{$account->slug}/free-consultation")->assertOk()->assertSee('free consultation');
        $this->get("/p/{$account->slug}/book")->assertOk()->assertSee('/book/'.$account->slug.'/discovery-call', false);
    }

    public function test_booking_creates_contact_and_appointment(): void
    {
        $calendar = Calendar::whereHas('subAccount', fn ($q) => $q->where('slug', 'bright-smile-dental'))->firstOrFail();
        $slot = now($calendar->timezone)->next('Wednesday')->setTime(11, 0);

        \Livewire\Livewire::test(\App\Livewire\BookingWidget::class, ['calendar' => $calendar])
            ->fillForm([
                'slot' => $slot->format('Y-m-d H:i'),
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'email' => 'ada@example.com',
            ])
            ->call('book')
            ->assertHasNoFormErrors()
            ->assertSee("You're booked", false);

        $contact = Contact::where('email', 'ada@example.com')->firstOrFail();
        $this->assertSame($calendar->sub_account_id, $contact->sub_account_id);

        $appointment = Appointment::where('contact_id', $contact->id)->firstOrFail();
        $this->assertTrue($appointment->starts_at->equalTo($slot->copy()->utc()));
        $this->assertSame('booking_page', $appointment->source);
    }

    public function test_form_submission_creates_contact(): void
    {
        $account = SubAccount::where('slug', 'bright-smile-dental')->firstOrFail();

        \Packstub\FormBuilder\Facades\FormBuilder::submit($account->slug.'-consultation', [
            'full_name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'message' => 'I need a cleaning',
        ]);

        $contact = Contact::where('email', 'grace@example.com')->firstOrFail();
        $this->assertSame($account->id, $contact->sub_account_id);
        $this->assertSame('Grace', $contact->first_name);
        $this->assertTrue($contact->tags->pluck('name')->contains('Free consultation request'));
    }

    public function test_workflow_messages_new_contact_and_adds_opportunity(): void
    {
        $account = SubAccount::where('slug', 'bright-smile-dental')->firstOrFail();
        $pipeline = $account->pipelines()->with('stages')->first();

        $template = json_decode(file_get_contents(resource_path('flow-templates/new-lead-nurture.json')), true);
        // Fill the pipeline the template leaves blank, and drop the wait + SMS tail for the test.
        foreach ($template['definition']['nodes'] as &$node) {
            if ($node['id'] === 'opportunity') {
                $node['data']['config']['pipeline_id'] = $pipeline->id;
                $node['data']['config']['pipeline_stage_id'] = $pipeline->stages->first()->id;
            }
        }
        unset($node);
        $template['definition']['nodes'] = array_values(array_filter($template['definition']['nodes'], fn ($n) => ! in_array($n['id'], ['wait', 'sms'])));
        $template['definition']['edges'] = array_values(array_filter($template['definition']['edges'], fn ($e) => ! in_array($e['target'], ['wait', 'sms'])));

        \Packstub\Flow\Models\Workflow::import(json_encode($template), [
            'is_active' => true,
            'tenant_type' => SubAccount::class,
            'tenant_id' => $account->id,
        ]);

        $contact = Contact::create([
            'sub_account_id' => $account->id,
            'first_name' => 'Linus',
            'email' => 'linus@example.com',
        ]);

        $message = $contact->conversation?->messages()->first();
        $this->assertNotNull($message, 'The workflow should have logged a welcome email in the conversation.');
        $this->assertSame('email', $message->channel);
        $this->assertStringContainsString('Linus', $message->subject);

        $this->assertDatabaseHas('opportunities', [
            'contact_id' => $contact->id,
            'pipeline_id' => $pipeline->id,
        ]);

        // A contact in another sub-account must not trigger this sub-account's workflow.
        $other = Contact::create([
            'sub_account_id' => SubAccount::where('slug', 'peak-roofing-co')->value('id'),
            'first_name' => 'Other',
            'email' => 'other@example.com',
        ]);
        $this->assertNull($other->conversation);
    }

    public function test_dragging_a_card_moves_the_opportunity_to_the_stage(): void
    {
        $account = SubAccount::where('slug', 'bright-smile-dental')->firstOrFail();
        $pipeline = $account->pipelines()->with('stages')->first();
        $opportunity = \App\Models\Opportunity::where('pipeline_stage_id', $pipeline->stages[0]->id)->where('status', 'open')->firstOrFail();
        $target = $pipeline->stages[2];

        $this->actingAs($this->admin());
        \Filament\Facades\Filament::setCurrentPanel('app');
        \Filament\Facades\Filament::setTenant($account);

        \Livewire\Livewire::test(\App\Filament\App\Pages\PipelineBoard::class, ['pipeline' => $pipeline->id])
            ->call('moveCard', (string) $opportunity->id, (string) $target->id);

        $this->assertSame($target->id, $opportunity->fresh()->pipeline_stage_id);
    }
}

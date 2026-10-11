<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Course;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\Review;
use App\Models\ReviewRequest;
use App\Models\Snapshot;
use App\Models\SubAccount;
use App\Models\TriggerLink;
use App\Models\User;
use App\Services\CampaignSender;
use App\Services\SnapshotService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Packstub\Flow\Models\Workflow;
use Tests\TestCase;

/** Marketing, payments, reputation, documents, memberships, API, chat, snapshots. */
class GrowthFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->first();
    }

    private function dental(): SubAccount
    {
        return SubAccount::where('slug', 'bright-smile-dental')->firstOrFail();
    }

    public function test_new_sub_account_screens_render(): void
    {
        $account = $this->dental();
        $this->actingAs($this->admin());

        $campaign = Campaign::where('sub_account_id', $account->id)->where('status', 'sent')->first();
        $draft = Campaign::where('sub_account_id', $account->id)->where('status', 'draft')->first();
        $invoice = Invoice::where('sub_account_id', $account->id)->first();
        $document = Document::where('sub_account_id', $account->id)->first();
        $course = Course::where('sub_account_id', $account->id)->first();
        $contact = $account->contacts()->first();

        foreach ([
            '/reports',
            '/smart-lists',
            '/contacts?tab=list-1',
            "/contacts/{$contact->id}",
            '/campaigns',
            '/campaigns/create',
            "/campaigns/{$campaign->id}",
            "/campaigns/{$draft->id}/edit",
            '/trigger-links',
            '/social-posts',
            '/reviews',
            '/invoices',
            '/invoices/create',
            "/invoices/{$invoice->id}/edit",
            '/payments',
            '/products',
            '/documents',
            '/documents/create',
            "/documents/{$document->id}/edit",
            '/courses',
            "/courses/{$course->id}/edit",
            '/custom-fields',
            '/custom-values',
            '/api-keys',
            '/team',
            '/profile',
        ] as $path) {
            $response = $this->get("/app/{$account->slug}{$path}");
            $this->assertSame(200, $response->status(), "GET /app/{$account->slug}{$path} returned {$response->status()}");
        }
    }

    public function test_new_agency_screens_render(): void
    {
        $this->actingAs($this->admin());

        foreach (['/agency', '/agency/snapshots', '/agency/branding', '/agency/sub-accounts', '/agency/sub-accounts/create'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_settings_are_admin_only(): void
    {
        $this->actingAs(User::where('email', 'staff@example.com')->first());

        foreach (['/team', '/api-keys', '/custom-fields', '/profile'] as $path) {
            $this->assertContains($this->get('/app/bright-smile-dental'.$path)->status(), [403, 404], $path);
        }

        $this->get('/app/bright-smile-dental/contacts')->assertOk();
    }

    public function test_public_pages_render(): void
    {
        $account = $this->dental();
        $invoice = Invoice::where('sub_account_id', $account->id)->where('status', 'sent')->first();
        $document = Document::where('sub_account_id', $account->id)->first();
        $enrollment = Course::where('sub_account_id', $account->id)->first()->enrollments()->first();
        $contact = $account->contacts()->first();

        $this->get($invoice->publicUrl())->assertOk()->assertSee($invoice->number);
        $this->get($document->publicUrl())->assertOk()->assertSee($document->title);
        $this->get(route('reviews.generic', $account))->assertOk();
        $this->get($enrollment->portalUrl())->assertOk()->assertSee($enrollment->course->title);
        $this->get(route('portal.lesson', [$enrollment->token, $enrollment->course->lessons->first()]))->assertOk();
        $this->get($contact->unsubscribeUrl())->assertOk();
        $this->get(route('widget.script', $account))->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
        $this->get(route('pages.show', [$account, 'free-consultation']))->assertOk();
    }

    public function test_recording_payment_marks_invoice_paid_and_grants_course(): void
    {
        $account = $this->dental();
        $course = Course::where('sub_account_id', $account->id)->first();
        $product = $account->products()->where('course_id', $course->id)->first();
        $contact = Contact::create(['sub_account_id' => $account->id, 'email' => 'buyer@example.test', 'first_name' => 'Bea']);

        $invoice = Invoice::create(['sub_account_id' => $account->id, 'contact_id' => $contact->id, 'status' => 'sent', 'tax_rate' => 10]);
        $invoice->items()->create(['product_id' => $product->id, 'description' => $product->name, 'quantity' => 2, 'unit_price' => 100]);
        $invoice->recalculate();

        $this->assertSame('220.00', $invoice->fresh()->total);

        $invoice->recordPayment(100, 'cash');
        $this->assertSame('partially_paid', $invoice->fresh()->status);

        $invoice->fresh()->recordPayment(120, 'cash');
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertTrue($course->enrollments()->where('contact_id', $contact->id)->exists());
        $this->assertSame('customer', $contact->fresh()->type);

        // Same reference twice (e.g. Stripe webhook + redirect) is only counted once.
        $second = Invoice::create(['sub_account_id' => $account->id, 'contact_id' => $contact->id, 'status' => 'sent']);
        $second->items()->create(['description' => 'Thing', 'quantity' => 1, 'unit_price' => 50]);
        $second->recalculate();
        $second->recordPayment(50, 'stripe', 'cs_test_1');
        $this->assertNull($second->fresh()->recordPayment(50, 'stripe', 'cs_test_1'));
        $this->assertSame('50.00', $second->fresh()->amount_paid);
    }

    public function test_stripe_checkout_redirect_is_verified_with_stripe(): void
    {
        $account = $this->dental();
        $account->update(['stripe_secret_key' => 'sk_test_123']);
        $invoice = Invoice::where('sub_account_id', $account->id)->where('status', 'sent')->firstOrFail();

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.test/cs_1']),
            'api.stripe.com/v1/checkout/sessions/cs_1' => Http::response(['payment_status' => 'paid', 'amount_total' => (int) round($invoice->balance() * 100), 'metadata' => ['invoice_id' => $invoice->id]]),
        ]);

        $this->post(route('invoices.pay', $invoice->token))->assertRedirect('https://checkout.stripe.test/cs_1');
        $this->get(route('invoices.paid', $invoice->token).'?session_id=cs_1')->assertRedirect();

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'method' => 'stripe', 'reference' => 'cs_1']);
    }

    public function test_campaign_sends_skips_opt_outs_and_tracks_opens_and_clicks(): void
    {
        $account = $this->dental();
        $contacts = $account->contacts()->take(3)->get();
        $contacts->each->attachTags(['spring-test']);
        $contacts[2]->update(['dnd_email' => true]);

        $campaign = Campaign::create([
            'sub_account_id' => $account->id,
            'name' => 'Test blast',
            'channel' => 'email',
            'subject' => 'Hello {{ contact.first_name }}',
            'body' => '<p>Hi {{ contact.first_name }}, code {{ custom_values.promo_code }}</p><p><a href="https://example.com/x">Go</a></p>',
            'tags' => ['spring-test'],
        ]);

        CampaignSender::send($campaign);
        $campaign->refresh();

        $this->assertSame('sent', $campaign->status);
        $this->assertSame(3, $campaign->recipients_count);
        $this->assertSame(2, $campaign->sent_count);
        $this->assertSame(1, $campaign->skipped_count);

        $message = Message::where('campaign_id', $campaign->id)->first();
        $this->assertStringContainsString('SMILE20', $message->body);
        $this->assertStringContainsString('/t/c/', $message->body);
        $this->assertStringNotContainsString('{{', $message->body);

        $recipient = $campaign->recipients()->where('status', 'sent')->first();
        $this->get(route('tracking.open', $recipient->token))->assertOk();
        $this->get(URL::signedRoute('tracking.click', ['token' => $recipient->token, 'u' => 'https://example.com/x']))->assertRedirect('https://example.com/x');
        $this->get(route('tracking.click', ['token' => $recipient->token, 'u' => 'https://evil.test']))->assertForbidden();

        $campaign->refresh();
        $this->assertSame(1, $campaign->opened_count);
        $this->assertSame(1, $campaign->clicked_count);
    }

    public function test_trigger_link_tags_contact_and_runs_workflow(): void
    {
        $account = $this->dental();
        $contact = $account->contacts()->first();
        $link = TriggerLink::where('sub_account_id', $account->id)->first();

        $this->tagWorkflow($account, 'trigger_link_clicked', 'wf-ran');

        $this->get($link->shortUrl($contact))->assertRedirect($link->url);

        $this->assertContains('clicked-offer', $contact->fresh()->tagNames());
        $this->assertContains('wf-ran', $contact->fresh()->tagNames());
        $this->assertSame(1, $link->fresh()->clicks_count);
    }

    private function tagWorkflow(SubAccount $account, string $event, string $tag): Workflow
    {
        return Workflow::import(json_encode([
            'name' => "On {$event}",
            'definition' => [
                'nodes' => [
                    ['id' => 't1', 'type' => 'trigger', 'data' => ['identifier' => \App\Flow\Triggers\CrmEvent::class, 'config' => ['event' => $event]], 'position' => ['x' => 0, 'y' => 0]],
                    ['id' => 'a1', 'type' => 'action', 'data' => ['identifier' => \App\Flow\Actions\AddContactTag::class, 'config' => ['tags' => [$tag]]], 'position' => ['x' => 0, 'y' => 100]],
                ],
                'edges' => [['id' => 'e1', 'source' => 't1', 'target' => 'a1']],
            ],
        ]), ['tenant_type' => SubAccount::class, 'tenant_id' => (string) $account->id, 'is_active' => true]);
    }

    public function test_review_funnel_routes_happy_and_unhappy_customers(): void
    {
        $account = $this->dental();
        $happy = ReviewRequest::create(['sub_account_id' => $account->id, 'contact_id' => $account->contacts()->first()->id]);
        $unhappy = ReviewRequest::create(['sub_account_id' => $account->id, 'contact_id' => $account->contacts()->skip(1)->first()->id]);

        $this->post(route('reviews.submit', $happy->token), ['rating' => 5, 'comment' => 'Great'])->assertOk()->assertSee('Review us on Google');
        $this->post(route('reviews.submit', $unhappy->token), ['rating' => 2, 'comment' => 'Slow'])->assertOk()->assertDontSee('Review us on Google');

        $this->assertTrue(Review::where('review_request_id', $happy->id)->value('is_public'));
        $this->assertFalse(Review::where('review_request_id', $unhappy->id)->value('is_public'));
        $this->assertNotNull($happy->fresh()->completed_at);
    }

    public function test_document_can_be_signed_once_and_wins_the_opportunity(): void
    {
        $document = Document::where('sub_account_id', $this->dental()->id)->whereNotNull('opportunity_id')->firstOrFail();

        $this->get($document->publicUrl())->assertOk();
        $this->assertSame('viewed', $document->fresh()->status);

        $this->post(route('documents.sign', $document->token), ['signer_name' => 'Pat Client', 'agree' => '1', 'decision' => 'sign'])->assertRedirect();

        $document->refresh();
        $this->assertSame('signed', $document->status);
        $this->assertSame('Pat Client', $document->signer_name);
        $this->assertSame('won', $document->opportunity->status);

        $this->post(route('documents.sign', $document->token), ['signer_name' => 'Again', 'agree' => '1', 'decision' => 'sign'])->assertStatus(422);
    }

    public function test_course_progress_and_completion(): void
    {
        $enrollment = Course::where('sub_account_id', $this->dental()->id)->first()->enrollments()->first();

        foreach ($enrollment->course->lessons as $lesson) {
            $this->post(route('portal.complete', [$enrollment->token, $lesson]))->assertRedirect();
        }

        $this->assertSame(100, $enrollment->fresh()->progress());
        $this->assertNotNull($enrollment->fresh()->completed_at);
    }

    public function test_chat_widget_creates_contact_and_shows_agent_replies(): void
    {
        $account = $this->dental();

        $response = $this->postJson(route('widget.send', $account), ['name' => 'Wanda Web', 'email' => 'wanda@example.test', 'message' => 'Do you do braces?'])->assertOk();
        $visitor = $response->json('visitor');

        $contact = Contact::where('email', 'wanda@example.test')->firstOrFail();
        $this->assertSame('Wanda', $contact->first_name);

        \App\Services\MessageSender::send($contact, 'chat', 'Yes we do!');

        $this->getJson(route('widget.poll', $account).'?visitor='.$visitor)
            ->assertOk()
            ->assertJsonPath('messages.1.body', 'Yes we do!')
            ->assertJsonPath('messages.1.from', 'agent');

        // Another account can't read this visitor's thread.
        $this->getJson(route('widget.poll', SubAccount::where('slug', 'peak-roofing-co')->first()).'?visitor='.$visitor)->assertJsonCount(0, 'messages');
    }

    public function test_inbound_sms_requires_twilio_signature_and_handles_stop(): void
    {
        $account = $this->dental();
        $account->update(['twilio_sid' => 'AC1', 'twilio_token' => 'secret', 'twilio_from' => '+15550000000']);
        $url = route('webhooks.twilio.sms', $account);
        $params = ['From' => '+15551112222', 'Body' => 'STOP', 'MessageSid' => 'SM1'];

        $this->post($url, $params)->assertForbidden();

        ksort($params);
        $data = $url;
        foreach ($params as $k => $v) {
            $data .= $k.$v;
        }
        $signature = base64_encode(hash_hmac('sha1', $data, 'secret', true));

        $this->post($url, $params, ['X-Twilio-Signature' => $signature])->assertOk();
        $this->post($url, $params, ['X-Twilio-Signature' => $signature])->assertOk(); // retry is idempotent

        $contact = Contact::where('sub_account_id', $account->id)->where('phone', '+15551112222')->firstOrFail();
        $this->assertTrue($contact->dnd_sms);
        $this->assertSame(1, Message::where('external_id', 'SM1')->count());
    }

    public function test_outbound_sms_goes_through_twilio(): void
    {
        $account = $this->dental();
        $account->update(['twilio_sid' => 'AC1', 'twilio_token' => 'secret', 'twilio_from' => '+15550000000']);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM99', 'status' => 'queued'], 201)]);

        $message = \App\Services\MessageSender::send($account->contacts()->first(), 'sms', 'Hi {{ contact.first_name }}');

        $this->assertSame('sent', $message->status);
        $this->assertSame('SM99', $message->external_id);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/Accounts/AC1/Messages.json') && $request['From'] === '+15550000000');
    }

    public function test_api_is_scoped_to_the_keys_sub_account(): void
    {
        $dental = $this->dental();
        $roofing = SubAccount::where('slug', 'peak-roofing-co')->first();
        [, $token] = ApiKey::issue($dental, 'Test');
        $auth = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/v1/contacts')->assertUnauthorized();
        $this->getJson('/api/v1/contacts', $auth)->assertOk()->assertJsonPath('total', $dental->contacts()->count());

        $created = $this->postJson('/api/v1/contacts', ['email' => 'api@example.test', 'first_name' => 'Ada', 'tags' => ['from-api']], $auth)->assertCreated();
        $this->assertSame(['from-api'], $created->json('data.tags'));
        $this->postJson('/api/v1/contacts', ['email' => 'api@example.test', 'last_name' => 'Lovelace'], $auth)->assertOk()->assertJsonPath('data.full_name', 'Ada Lovelace');

        $foreign = $roofing->contacts()->first();
        $this->getJson("/api/v1/contacts/{$foreign->id}", $auth)->assertNotFound();

        $pipeline = $dental->pipelines()->first();
        $this->postJson('/api/v1/opportunities', ['name' => 'API deal', 'pipeline_id' => $pipeline->id, 'value' => 999], $auth)->assertCreated();
        $this->postJson('/api/v1/opportunities', ['name' => 'Nope', 'pipeline_id' => $roofing->pipelines()->first()->id], $auth)->assertUnprocessable();
    }

    public function test_snapshot_copies_setup_into_new_sub_account(): void
    {
        $this->tagWorkflow($this->dental(), 'invoice_paid', 'paid');
        $snapshot = SnapshotService::capture($this->dental(), 'Dental starter', array_keys(SnapshotService::ASSETS));
        $target = SubAccount::create(['name' => 'New Dental Client']);

        $created = SnapshotService::apply(Snapshot::find($snapshot->id), $target);

        $this->assertGreaterThan(0, $created['custom_fields'] ?? 0);
        $this->assertSame(1, $created['courses'] ?? 0);
        $this->assertTrue($target->customFields()->where('key', 'insurance_provider')->exists());
        $this->assertSame(0, $target->contacts()->count());
        $this->assertTrue(Workflow::query()->withoutGlobalScopes()->where('tenant_id', (string) $target->id)->exists());

        // The copied funnel page points at the copied form.
        $page = $target->pages()->where('slug', 'free-consultation')->first();
        $this->assertStringContainsString($target->slug.'-consultation', json_encode($page->blocks));
        $this->get(route('pages.show', [$target, 'free-consultation']))->assertOk()->assertDontSee('not found');
    }

    public function test_unsubscribe_link_opts_contact_out(): void
    {
        $contact = $this->dental()->contacts()->first();

        $this->post(route('unsubscribe.confirm', $contact->trackingToken()), ['channel' => 'email'])->assertOk();

        $this->assertTrue($contact->fresh()->dnd_email);
        $this->assertFalse($contact->fresh()->canReceive('email'));
    }
}

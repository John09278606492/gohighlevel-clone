<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Everything beyond the CRM core: messaging settings, custom fields, smart lists,
 * campaigns, trigger links, payments, reputation, documents, courses, social
 * planner, API keys, snapshots and the contact activity timeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_accounts', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD');
            // Messaging
            $table->string('email_from_name')->nullable();
            $table->string('email_from_address')->nullable();
            $table->string('twilio_sid')->nullable();
            $table->text('twilio_token')->nullable(); // encrypted cast
            $table->string('twilio_from')->nullable();
            $table->string('inbound_secret', 64)->nullable(); // signs inbound email / generic webhooks
            // Payments
            $table->text('stripe_secret_key')->nullable(); // encrypted cast
            $table->decimal('default_tax_rate', 5, 2)->default(0);
            $table->text('invoice_footer')->nullable();
            // Reputation
            $table->string('google_review_url')->nullable();
            $table->string('facebook_review_url')->nullable();
            $table->unsignedTinyInteger('review_threshold')->default(4);
            $table->text('review_request_message')->nullable();
            // Web chat widget
            $table->boolean('chat_widget_enabled')->default(true);
            $table->string('chat_widget_color', 20)->default('#4f46e5');
            $table->string('chat_widget_greeting')->nullable();
            // Social planner
            $table->string('social_webhook_url')->nullable();
            $table->string('brand_color', 20)->nullable();
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('dnd_email')->default(false)->after('dnd');
            $table->boolean('dnd_sms')->default(false)->after('dnd_email');
            $table->string('unsubscribe_token', 40)->nullable()->unique()->after('dnd_sms');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('external_id')->nullable()->index()->after('error');
            $table->foreignId('campaign_id')->nullable()->after('user_id');
        });

        Schema::create('agency_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key');
            $table->string('type')->default('text'); // text | textarea | number | date | select | checkbox | url
            $table->json('options')->nullable();
            $table->string('placeholder')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['sub_account_id', 'key']);
        });

        Schema::create('custom_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['sub_account_id', 'key']);
        });

        Schema::create('smart_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->json('criteria')->nullable();
            $table->boolean('is_shared')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // see Activity::TYPES
            $table->string('description');
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['contact_id', 'created_at']);
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('smart_list_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('channel')->default('email'); // email | sms
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->json('tags')->nullable(); // audience: contacts with any of these tags
            $table->string('status')->default('draft'); // draft | scheduled | sending | sent | cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('opened_count')->default(0);
            $table->unsignedInteger('clicked_count')->default(0);
            $table->unsignedInteger('unsubscribed_count')->default(0);
            $table->timestamps();
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 40)->unique();
            $table->string('status')->default('pending'); // pending | sent | failed | skipped
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'contact_id']);
        });

        Schema::create('trigger_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 16)->unique();
            $table->string('url', 2048);
            $table->json('add_tags')->nullable();
            $table->unsignedInteger('clicks_count')->default(0);
            $table->unsignedInteger('unique_contacts_count')->default(0);
            $table->timestamps();
        });

        Schema::create('trigger_link_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trigger_link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable(); // buying it grants course access
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('billing')->default('one_time'); // one_time | monthly | yearly
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number');
            $table->string('title')->nullable();
            $table->string('status')->default('draft'); // draft | sent | partially_paid | paid | void
            $table->string('currency', 3)->default('USD');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('token', 40)->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['sub_account_id', 'number']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('method')->default('manual'); // manual | cash | bank_transfer | card | stripe
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();
            $table->unique(['method', 'reference']);
        });

        Schema::create('review_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('token', 40)->unique();
            $table->string('channel')->default('email');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('review_request_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('author_name')->nullable();
            $table->string('source')->default('direct'); // direct | google | facebook | manual
            $table->boolean('is_public')->default(true);
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('status')->default('draft'); // draft | sent | viewed | signed | declined
            $table->string('token', 40)->unique();
            $table->date('expires_on')->nullable();
            $table->string('signer_name')->nullable();
            $table->string('signer_ip', 45)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('module')->nullable(); // section heading the lesson sits under
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('video_url')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_preview')->default(false);
            $table->timestamps();
        });

        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('token', 40)->unique();
            $table->string('source')->default('manual'); // manual | purchase | workflow
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'contact_id']);
        });

        Schema::create('lesson_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at')->useCurrent();
            $table->unique(['course_enrollment_id', 'lesson_id']);
        });

        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('content');
            $table->json('platforms')->nullable(); // facebook | instagram | linkedin | x | google_business | tiktok
            $table->string('image')->nullable();
            $table->string('link')->nullable();
            $table->string('status')->default('draft'); // draft | scheduled | published | failed
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('prefix', 12);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_sub_account_id')->nullable()->constrained('sub_accounts')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('contents')->nullable(); // which asset types were captured
            $table->longText('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['snapshots', 'api_keys', 'social_posts', 'lesson_completions', 'course_enrollments', 'lessons', 'courses', 'documents', 'reviews', 'review_requests', 'payments', 'invoice_items', 'invoices', 'products', 'trigger_link_clicks', 'trigger_links', 'campaign_recipients', 'campaigns', 'activities', 'smart_lists', 'custom_values', 'custom_fields', 'agency_settings'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('messages', fn (Blueprint $table) => $table->dropColumn(['external_id', 'campaign_id']));
        Schema::table('contacts', fn (Blueprint $table) => $table->dropColumn(['dnd_email', 'dnd_sms', 'unsubscribe_token']));
        Schema::table('sub_accounts', fn (Blueprint $table) => $table->dropColumn([
            'currency', 'email_from_name', 'email_from_address', 'twilio_sid', 'twilio_token', 'twilio_from', 'inbound_secret',
            'stripe_secret_key', 'default_tax_rate', 'invoice_footer', 'google_review_url', 'facebook_review_url',
            'review_threshold', 'review_request_message', 'chat_widget_enabled', 'chat_widget_color', 'chat_widget_greeting',
            'social_webhook_url', 'brand_color',
        ]));
    }
};

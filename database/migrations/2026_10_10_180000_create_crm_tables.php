<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_agency_admin')->default(false)->after('password');
            $table->string('phone')->nullable()->after('email');
        });

        // A sub-account is GoHighLevel's "location": one client business.
        Schema::create('sub_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('logo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sub_account_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('user'); // admin | user
            $table->timestamps();
            $table->unique(['sub_account_id', 'user_id']);
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('type')->default('lead'); // lead | customer
            $table->string('source')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->boolean('dnd')->default(false);
            $table->json('custom_fields')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->index(['sub_account_id', 'email']);
        });

        Schema::create('contact_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
        });

        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color')->default('gray');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->decimal('value', 12, 2)->default(0);
            $table->string('status')->default('open'); // open | won | lost | abandoned
            $table->string('source')->nullable();
            $table->decimal('position', 20, 10)->nullable(); // Flowforge card order
            $table->timestamps();
            $table->unique(['pipeline_stage_id', 'position']);
        });

        Schema::create('calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('color')->default('#6366f1');
            $table->unsignedInteger('duration_minutes')->default(30);
            $table->unsignedInteger('slot_interval_minutes')->default(30);
            $table->unsignedInteger('minimum_notice_minutes')->default(60);
            $table->unsignedInteger('booking_window_days')->default(60);
            $table->json('working_hours')->nullable(); // ['monday' => ['09:00','17:00'], ...]
            $table->string('timezone')->default('UTC');
            $table->string('confirmation_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['sub_account_id', 'slug']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calendar_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default('booked'); // booked | confirmed | showed | no_show | cancelled
            $table->string('source')->default('internal'); // internal | booking_page
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['calendar_id', 'starts_at']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('unread_count')->default(0);
            $table->boolean('is_starred')->default(false);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->unique(['sub_account_id', 'contact_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel')->default('email'); // email | sms | note
            $table->string('direction')->default('outbound'); // inbound | outbound
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status')->default('sent'); // queued | sent | delivered | failed | received
            $table->text('error')->nullable();
            $table->timestamps();
        });

        // Funnel and website pages, edited with the drag-and-drop page builder.
        Schema::create('funnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('funnel'); // funnel | website
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('funnel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->unsignedInteger('step_order')->default(0);
            $table->json('blocks')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
            $table->unique(['sub_account_id', 'slug']);
        });
    }

    public function down(): void
    {
        foreach (['pages', 'funnels', 'messages', 'conversations', 'appointments', 'calendars', 'opportunities', 'pipeline_stages', 'pipelines', 'tasks', 'contact_notes', 'contacts', 'sub_account_user', 'sub_accounts'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_agency_admin', 'phone']);
        });
    }
};

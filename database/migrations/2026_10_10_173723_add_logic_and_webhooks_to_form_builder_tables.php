<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1.2.0: the sequential number and the visitor fingerprint on submissions,
 * the tenant column on forms, and the webhook deliveries table. Guarded so
 * a fresh install (whose create migration already has them) is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        $forms = config('packstub-form-builder.tables.forms', 'form_builder_forms');
        $submissions = config('packstub-form-builder.tables.submissions', 'form_builder_submissions');
        $deliveries = config('packstub-form-builder.tables.webhook_deliveries', 'form_builder_webhook_deliveries');
        $tenant = config('packstub-form-builder.tenancy.column', 'tenant_id');

        Schema::table($forms, function (Blueprint $table) use ($forms, $tenant): void {
            if (! Schema::hasColumn($forms, $tenant)) {
                $table->string($tenant, 64)->nullable()->index();
            }
        });

        Schema::table($submissions, function (Blueprint $table) use ($submissions): void {
            if (! Schema::hasColumn($submissions, 'number')) {
                $table->unsignedInteger('number')->nullable();
            }

            if (! Schema::hasColumn($submissions, 'fingerprint')) {
                $table->string('fingerprint', 64)->nullable();
                $table->index(['form_id', 'fingerprint']);
            }
        });

        if (! Schema::hasTable($deliveries)) {
            Schema::create($deliveries, function (Blueprint $table) use ($forms, $submissions): void {
                $table->id();
                $table->foreignId('form_id')->constrained($forms)->cascadeOnDelete();
                $table->foreignId('submission_id')->nullable()->constrained($submissions)->nullOnDelete();
                $table->string('url', 2048);
                $table->string('event', 64)->default('submission.received');
                $table->string('status', 16)->default('pending')->index();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->text('response_body')->nullable();
                $table->text('error')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('next_attempt_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Additive: nothing to undo inside the major.
    }
};

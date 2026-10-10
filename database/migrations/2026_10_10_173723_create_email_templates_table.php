<?php

declare(strict_types=1);

use FinityLabs\FinMail\Models\EmailTheme;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('fin-mail.table_names.templates') ?? 'email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 255)->unique();
            $table->json('name');
            $table->string('category', 100)->default('transactional')->index();
            $table->json('tags')->nullable();

            // Content (translatable — stored as JSON: {"en": "...", "hu": "..."})
            $table->json('subject');
            $table->json('preheader')->nullable();
            $table->json('body');
            $table->string('view_path', 255)->nullable();

            // Sender
            $table->json('from')->nullable();

            // Theme
            $table->foreignIdFor(EmailTheme::class)
                ->nullable()
                ->constrained(config('fin-mail.table_names.themes') ?? 'email_themes')
                ->nullOnDelete();

            // Token documentation
            $table->json('token_schema')->nullable();

            // State
            $table->boolean('is_active')->default(true);
            $table->boolean('is_locked')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('fin-mail.table_names.templates') ?? 'email_templates');
    }
};

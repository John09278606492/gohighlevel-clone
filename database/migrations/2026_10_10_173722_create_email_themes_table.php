<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('fin-mail.table_names.themes') ?? 'email_themes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->json('colors');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('fin-mail.table_names.themes') ?? 'email_themes');
    }
};

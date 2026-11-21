<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Traducerile continutului (servicii, pagini, articole, intrebari, echipa) in engleza si maghiara. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('translatable_type', 120);
            $table->unsignedBigInteger('translatable_id');
            $table->string('locale', 5);
            $table->string('field', 60);
            $table->longText('value')->nullable();
            $table->timestamps();
            $table->unique(['translatable_type', 'translatable_id', 'locale', 'field'], 'translations_unique');
            $table->index(['translatable_type', 'locale']);
        });

        // Continutul existent primeste traducerile livrate cu aceasta versiune (doar unde lipsesc).
        app(\App\Services\Translations\ContentTranslations::class)->importBundled();
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};

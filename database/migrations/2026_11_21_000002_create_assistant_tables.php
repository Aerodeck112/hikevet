<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asistentul AI de pe site: ce stie in plus fata de continutul site-ului (scris de clinica)
 * si intrebarile primite (pastrate 60 de zile, fara IP, ca sa vedem ce trebuie completat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_facts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('content');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('assistant_logs', function (Blueprint $table) {
            $table->id();
            $table->string('conversation', 40)->index();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->string('status', 20)->default('answered')->index();
            $table->string('model', 60)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->boolean('reviewed')->default(false)->index();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_logs');
        Schema::dropIfExists('assistant_facts');
    }
};

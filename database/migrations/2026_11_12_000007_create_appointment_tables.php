<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_member_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unique(['team_member_id', 'weekday', 'starts_at']);
        });

        Schema::create('schedule_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_member_id')->nullable()->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_member_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->string('status', 20)->default('requested')->index();
            $table->string('client_name');
            $table->string('client_phone', 60);
            $table->string('client_email')->nullable();
            $table->string('pet_name')->nullable();
            $table->string('pet_species', 30)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('consent_processing')->default(false);
            $table->string('manage_token', 64)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });

        // Un rand pe interval de 15 minute ocupat. Cheia unica face imposibila dubla rezervare
        // chiar si la doua cereri simultane, pe MySQL si pe SQLite.
        Schema::create('appointment_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_member_id')->constrained()->cascadeOnDelete();
            $table->dateTime('slot_at');
            $table->unique(['team_member_id', 'slot_at']);
        });
    }

    public function down(): void
    {
        foreach (['appointment_slots', 'appointments', 'schedule_blocks', 'doctor_schedules'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

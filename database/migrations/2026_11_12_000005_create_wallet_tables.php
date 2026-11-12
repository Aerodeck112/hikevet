<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registru imuabil. Soldurile se calculeaza exclusiv ca suma a randurilor; nu exista coloana de sold.
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 40)->index();
            $table->string('balance_type', 30)->index();
            $table->decimal('amount_delta', 12, 2)->default(0);
            $table->integer('points_delta')->default(0);
            $table->text('note')->nullable();
            $table->string('related_type', 50)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->foreignId('pet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->foreignId('reverses_id')->nullable()->constrained('wallet_transactions')->restrictOnDelete();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->unsignedBigInteger('wp_tx_id')->nullable()->unique();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['user_id', 'balance_type']);
        });

        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('pet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pet_name')->nullable();
            $table->string('name');
            $table->decimal('price_standard', 12, 2)->default(0);
            $table->unsignedInteger('points_used')->default(0);
            $table->string('payment_category', 30)->default('subscription');
            $table->decimal('principal_used', 12, 2)->default(0);
            $table->decimal('bonus_used', 12, 2)->default(0);
            $table->string('bonus_balance_type', 30)->nullable();
            $table->boolean('bonus_blocked')->default(false);
            $table->string('bonus_block_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('delete_reason')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('wp_treatment_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('wallet_topup_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('client_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->longText('message');
            $table->string('channel', 30)->default('email');
            $table->string('related_type', 50)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->unsignedBigInteger('wp_notification_id')->nullable()->unique();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('doctor_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pet_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('note_text');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('wp_note_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['doctor_notes', 'client_notifications', 'wallet_topup_requests', 'treatments', 'wallet_transactions'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platile online cu cardul (Stripe Checkout): alimentarea Wallet-ului si plata abonamentului. Datele cardului nu
 * ajung niciodata pe server. Factura se emite manual; aici se tine evidenta ei.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('purpose', 20); // wallet_topup | subscription
            $table->foreignId('contract_id')->nullable()->constrained('contracts');
            $table->unsignedTinyInteger('months')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('ron');
            // pending -> paid | expired | failed; paid_needs_review = bani incasati, dar operatiunea nu s-a putut aplica automat
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('live_mode')->default(false);
            $table->string('stripe_session_id', 255)->nullable()->unique();
            $table->string('stripe_payment_intent', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('review_reason', 500)->nullable();
            $table->string('invoice_number', 60)->nullable();
            $table->date('invoice_date')->nullable();
            $table->foreignId('invoice_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('invoice_recorded_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['status', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payments');
    }
};

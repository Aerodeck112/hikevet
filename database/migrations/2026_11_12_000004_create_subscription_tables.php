<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('species', 20)->index();
            $table->string('sex', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('breed')->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->string('microchip')->nullable()->index();
            $table->string('sterilized', 10)->nullable();
            $table->string('tier_label', 40)->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('archive_reason')->nullable();
            // ID-ul animalului din user_meta hv_pets (unic doar in cadrul unui utilizator WordPress).
            $table->unsignedBigInteger('wp_pet_id')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'archived_at']);
        });

        // Liste de preturi versionate: o lista noua nu modifica niciodata contractele emise pe una veche.
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('version', 40)->unique();
            $table->string('source')->nullable();
            $table->date('effective_from');
            $table->boolean('is_active')->default(false)->index();
            $table->unsignedSmallInteger('contract_valid_days')->default(5);
            $table->json('multi_pet_rules');
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('subscription_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_list_id')->constrained()->cascadeOnDelete();
            $table->string('species', 20);
            $table->string('label', 40);
            $table->decimal('min_kg', 7, 2);
            $table->decimal('max_kg', 7, 2);
            $table->boolean('max_inclusive')->default(false);
            $table->unsignedInteger('price_monthly');
            $table->unsignedInteger('price_6m');
            $table->unsignedInteger('price_12m');
            $table->unsignedInteger('total_normal');
            $table->unsignedInteger('total_subscription');
            $table->unsignedInteger('sort')->default(0);
            $table->unique(['price_list_id', 'species', 'label']);
        });

        Schema::create('subscription_tier_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_tier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('display_name');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('quantity_label', 20)->nullable();
            $table->unsignedInteger('price_normal');
            $table->unsignedInteger('price_subscription');
            $table->unsignedInteger('points_per_use')->default(0);
            $table->unsignedInteger('sort')->default(0);
        });

        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->string('version', 40)->unique();
            $table->json('lines');
            $table->string('sha256', 64);
            $table->boolean('is_active')->default(false);
            $table->string('source')->nullable();
            $table->timestamps();
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('pet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('price_list_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('contract_template_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('submission_token', 80)->unique();
            $table->string('status', 30)->default('awaiting_clinic')->index();
            $table->string('species', 20);
            $table->string('tier_label', 40);
            $table->string('pet_name');
            $table->unsignedInteger('standard_monthly');
            $table->unsignedInteger('standard_6m');
            $table->unsignedInteger('standard_12m');
            $table->unsignedInteger('price_monthly');
            $table->unsignedInteger('price_6m');
            $table->unsignedInteger('price_12m');
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->string('discount_label')->nullable();
            $table->unsignedSmallInteger('pet_ordinal')->default(1);
            $table->unsignedInteger('total_normal')->default(0);
            $table->unsignedInteger('total_subscription')->default(0);
            $table->unsignedTinyInteger('period_months')->default(1);
            $table->string('payment_method', 30)->default('clinic');
            $table->decimal('payment_amount', 12, 2)->default(0);
            // Payload complet (include CNP si CI) criptat la nivel de aplicatie.
            $table->longText('payload');
            $table->json('legal_consents');
            $table->string('signature_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_sha256', 64)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_ip', 45)->nullable();
            $table->string('signed_user_agent', 500)->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('expiry_notice_sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->unsignedBigInteger('wp_post_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('contract_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['contract_status_history', 'contracts', 'contract_templates', 'subscription_tier_services', 'subscription_tiers', 'price_lists', 'pets'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

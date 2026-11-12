<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 60)->nullable()->after('email');
            $table->string('address')->nullable();
            $table->string('city', 120)->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('must_set_password')->default(false);
            $table->timestamp('marketing_opt_in_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            // 2FA (TOTP) pentru personal.
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
            // Migrare WordPress: ID original si hash-ul parolei WP, verificat o singura data la primul login.
            $table->unsignedBigInteger('wp_user_id')->nullable()->unique();
            $table->string('wp_password_hash')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['wp_user_id']);
            $table->dropColumn(['phone', 'address', 'city', 'internal_notes', 'is_active', 'must_set_password',
                'marketing_opt_in_at', 'last_login_at', 'app_authentication_secret',
                'app_authentication_recovery_codes', 'wp_user_id', 'wp_password_hash']);
        });
    }
};

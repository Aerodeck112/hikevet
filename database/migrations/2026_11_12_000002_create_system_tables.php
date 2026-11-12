<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 60)->default('general')->index();
            $table->string('key', 120)->unique();
            $table->json('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });

        // Jurnal de audit: cine a creat / modificat / vazut / exportat ce. Nu se editeaza si nu se sterge din aplicatie.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('action', 60)->index();
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('summary')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->unsignedBigInteger('wp_audit_id')->nullable()->unique();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('to_email');
            $table->string('subject');
            $table->string('context', 80)->nullable()->index();
            $table->string('status', 20)->default('sent')->index();
            $table->text('error_message')->nullable();
            $table->string('related_type', 50)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->unsignedBigInteger('wp_email_log_id')->nullable()->unique();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedInteger('hits')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 60)->nullable();
            $table->text('message');
            $table->string('source', 60)->default('contact');
            $table->string('status', 20)->default('new')->index();
            $table->boolean('consent_processing')->default(false);
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 60)->index();
            $table->string('source_label')->nullable();
            $table->string('source_sha256', 64)->nullable();
            $table->boolean('dry_run')->default(true);
            $table->string('status', 30)->default('running');
            $table->json('counts')->nullable();
            $table->json('errors')->nullable();
            $table->json('missing')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_id_map', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->string('entity', 60);
            $table->string('source_id', 120);
            $table->unsignedBigInteger('target_id');
            $table->string('row_sha256', 64)->nullable();
            $table->unique(['entity', 'source_id']);
            $table->index(['entity', 'target_id']);
        });

        Schema::create('update_runs', function (Blueprint $table) {
            $table->id();
            $table->string('from_version', 40);
            $table->string('to_version', 40);
            $table->string('package_sha256', 64);
            $table->string('status', 30)->default('pending');
            $table->string('backup_path')->nullable();
            $table->longText('log')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['update_runs', 'import_id_map', 'import_batches', 'contact_messages', 'redirects', 'email_logs', 'audit_logs', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

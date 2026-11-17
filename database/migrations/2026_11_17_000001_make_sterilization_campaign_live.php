<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campania de sterilizare devine modul activ (decizia clientului din 17.11.2026, varianta B).
 *
 * - `data` tine starea curenta a dosarului, cu exact aceleasi chei ca tabela hv_st_cases din plugin.
 * - `source_row` + `row_sha256` raman copia neatinsa a randului importat din WordPress (dovada).
 * - Orice modificare facuta in platforma noua este scrisa in `sterilization_case_changes` (doar adaugare).
 * - Programarile si jurnalul de emailuri primesc coloanele pluginului, ca sa poata fi folosite activ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sterilization_cases', function (Blueprint $table) {
            $table->unsignedBigInteger('source_id')->nullable()->change();
            $table->json('source_row')->nullable()->change();
            $table->string('row_sha256', 64)->nullable()->change();
            $table->json('data')->nullable()->after('source_row');
            $table->string('ora_start', 10)->nullable()->after('data_interventie');
            $table->date('data_fisa')->nullable()->after('data_validare');
            $table->string('procedura_programata')->nullable()->after('procedura_efectuata');
            $table->string('responsabil_caz')->nullable()->after('medic_operator');
            $table->string('photo_upload_token', 64)->nullable()->unique();
            $table->foreignId('photo_document_id')->nullable();
            $table->foreignId('ci_document_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('live_created_at')->nullable();
            $table->timestamp('live_updated_at')->nullable()->index();
            $table->timestamp('deleted_at')->nullable()->index();
        });

        Schema::table('sterilization_documents', function (Blueprint $table) {
            $table->string('source', 40)->nullable()->after('kind');
            $table->string('original_name')->nullable()->after('stored_path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            // Un document inlocuit nu se sterge: ramane in istoricul dosarului.
            $table->timestamp('superseded_at')->nullable()->index();
        });

        Schema::table('sterilization_appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('source_id')->nullable()->change();
            $table->json('source_row')->nullable()->change();
            $table->string('row_sha256', 64)->nullable()->change();
            $table->string('title')->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_phone', 60)->nullable();
            $table->string('source', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('reminder_enabled', 20)->default('Da');
            $table->unsignedSmallInteger('reminder_days')->default(1);
            $table->timestamp('reminder_sent_at')->nullable()->index();
            $table->timestamp('reminder_manual_sent_at')->nullable();
            $table->string('email_last_status', 20)->nullable();
            $table->timestamp('email_last_at')->nullable();
            $table->text('email_last_error')->nullable();
            $table->string('created_by_name')->nullable();
            $table->string('updated_by_name')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::table('sterilization_email_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('source_id')->nullable()->change();
            $table->json('source_row')->nullable()->change();
            $table->foreignId('appointment_id')->nullable();
            $table->text('to_email')->nullable();
            $table->text('cc_email')->nullable();
            $table->string('subject')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        // Lista pacientilor unei vizite se tine separat de documentul semnat (ca meta hv_consent_visit_patients din
        // plugin): adaugarea unui animal dupa semnare nu atinge payload-ul semnat si amprenta lui.
        Schema::table('consents', function (Blueprint $table) {
            $table->unsignedBigInteger('visit_group_id')->nullable()->index();
            $table->json('visit_patients')->nullable();
            $table->timestamp('visit_patients_updated_at')->nullable();
            $table->string('correction_reason')->nullable();
            $table->unsignedBigInteger('superseded_by_id')->nullable()->index();
        });

        Schema::create('sterilization_case_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sterilization_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('action', 60);
            $table->string('section', 40)->nullable();
            $table->json('changes')->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('sterilization_case_changes');
        Schema::table('consents', fn (Blueprint $t) => $t->dropColumn(['visit_group_id', 'visit_patients', 'visit_patients_updated_at', 'correction_reason', 'superseded_by_id']));
        Schema::table('sterilization_email_logs', fn (Blueprint $t) => $t->dropColumn(['appointment_id', 'to_email', 'cc_email', 'subject', 'error_message', 'created_at']));
        Schema::table('sterilization_appointments', fn (Blueprint $t) => $t->dropColumn(['title', 'client_email', 'client_phone', 'source', 'notes', 'reminder_enabled', 'reminder_days',
            'reminder_sent_at', 'reminder_manual_sent_at', 'email_last_status', 'email_last_at', 'email_last_error', 'created_by_name', 'updated_by_name', 'created_at', 'updated_at']));
        Schema::table('sterilization_documents', function (Blueprint $t) {
            $t->dropConstrainedForeignId('uploaded_by');
            $t->dropColumn(['source', 'original_name', 'superseded_at']);
        });
        Schema::table('sterilization_cases', function (Blueprint $t) {
            $t->dropConstrainedForeignId('created_by');
            $t->dropConstrainedForeignId('updated_by');
            $t->dropUnique(['photo_upload_token']);
            $t->dropColumn(['data', 'data_fisa', 'ora_start', 'procedura_programata', 'responsabil_caz', 'photo_upload_token', 'photo_document_id', 'ci_document_id', 'live_created_at', 'live_updated_at', 'deleted_at']);
        });
    }
};

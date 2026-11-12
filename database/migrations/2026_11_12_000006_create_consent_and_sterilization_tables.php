<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            // Codul de 6 cifre se pastreaza doar ca hash; expira si are limita de incercari.
            $table->string('code_hash', 64)->nullable()->index();
            $table->timestamp('code_expires_at')->nullable();
            $table->unsignedSmallInteger('failed_attempts')->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->string('origin', 30)->default('manual');
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('owner_name')->nullable()->index();
            $table->string('pet_name')->nullable();
            $table->string('procedure')->nullable();
            $table->string('vet_name')->nullable();
            $table->date('consent_date')->nullable();
            $table->longText('payload');
            $table->longText('owner_signature')->nullable();
            $table->longText('refusal_signature')->nullable();
            $table->longText('doctor_signature')->nullable();
            $table->timestamp('doctor_signed_at')->nullable();
            $table->foreignId('doctor_signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->string('signed_ip', 45)->nullable();
            // Amprenta documentului la momentul semnarii proprietarului (payload + semnaturi).
            $table->string('document_sha256', 64)->nullable();
            $table->string('template_version', 40)->nullable();
            $table->foreignId('supersedes_id')->nullable()->constrained('consents')->nullOnDelete();
            $table->unsignedBigInteger('sterilization_case_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('wp_post_id')->nullable()->unique();
            $table->timestamps();
        });

        // Arhiva istorica read-only. Coloanele de mai jos sunt doar pentru cautare si filtrare;
        // randul original complet (toate cele ~190 de coloane) se pastreaza neschimbat in source_row.
        Schema::create('sterilization_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('source_id')->unique();
            $table->string('cod_caz', 50)->unique();
            $table->string('numar_pacient', 80)->nullable()->index();
            $table->string('numar_pacient_raport', 80)->nullable();
            $table->date('data_programare')->nullable();
            $table->string('ora_programare', 10)->nullable();
            $table->date('data_prezentare')->nullable();
            $table->date('data_interventie')->nullable()->index();
            $table->date('data_raportare_primarie')->nullable()->index();
            $table->date('data_externare')->nullable();
            $table->string('nume_proprietar')->nullable()->index();
            $table->string('telefon', 60)->nullable();
            $table->string('email')->nullable();
            $table->string('localitate', 120)->nullable()->index();
            $table->string('specie', 50)->nullable()->index();
            $table->string('sex', 50)->nullable();
            $table->string('nume_pacient')->nullable();
            $table->string('rasa')->nullable();
            $table->string('microcip')->nullable();
            $table->string('procedura_efectuata')->nullable();
            $table->string('tip_anestezie')->nullable();
            $table->string('medic_operator')->nullable()->index();
            $table->string('status_caz', 80)->nullable()->index();
            $table->string('status_interventie', 80)->nullable()->index();
            $table->string('validat_pentru_primarie', 20)->nullable();
            $table->string('validat_de')->nullable();
            $table->date('data_validare')->nullable();
            $table->unsignedBigInteger('visit_group_id')->nullable()->index();
            $table->unsignedBigInteger('source_consent_id')->nullable();
            $table->unsignedBigInteger('source_shared_consent_id')->nullable();
            $table->foreignId('consent_id')->nullable()->constrained('consents')->nullOnDelete();
            $table->string('reported_month', 7)->nullable()->index();
            $table->json('source_row');
            $table->string('row_sha256', 64);
            $table->json('missing_in_source')->nullable();
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('imported_at')->nullable();
        });

        Schema::create('sterilization_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sterilization_case_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->text('source_reference')->nullable();
            $table->string('stored_path')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('mime', 100)->nullable();
            $table->boolean('is_missing')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('sterilization_appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id')->unique();
            $table->string('appointment_code', 50)->nullable();
            $table->unsignedBigInteger('source_case_id')->nullable()->index();
            $table->foreignId('sterilization_case_id')->nullable()->constrained()->nullOnDelete();
            $table->date('appointment_date')->nullable()->index();
            $table->string('start_time', 10)->nullable();
            $table->string('end_time', 10)->nullable();
            $table->string('appointment_type')->nullable();
            $table->string('status', 50)->nullable();
            $table->string('owner_name')->nullable();
            $table->string('patient_name')->nullable();
            $table->json('source_row');
            $table->string('row_sha256', 64);
        });

        Schema::create('sterilization_email_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id')->unique();
            $table->unsignedBigInteger('source_case_id')->nullable()->index();
            $table->foreignId('sterilization_case_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email_type', 80)->nullable();
            $table->string('status', 20)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('source_row');
        });

        Schema::create('sterilization_reference_lists', function (Blueprint $table) {
            $table->id();
            $table->string('list_key', 80);
            $table->string('cod_caz', 50);
            $table->string('value', 80)->nullable();
            $table->unique(['list_key', 'cod_caz']);
        });
    }

    public function down(): void
    {
        foreach (['sterilization_reference_lists', 'sterilization_email_logs', 'sterilization_appointments', 'sterilization_documents', 'sterilization_cases', 'consents'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

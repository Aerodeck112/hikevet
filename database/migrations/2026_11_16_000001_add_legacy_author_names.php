<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notitele importate din WordPress au fost scrise de personal care nu primeste cont in platforma noua.
 * Numele autorului se pastreaza ca text, ca istoricul sa ramana lizibil dupa oprirea site-ului vechi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_notes', function (Blueprint $table) {
            $table->string('actor_name')->nullable()->after('actor_id');
            $table->string('deleted_by_name')->nullable()->after('deleted_by');
        });
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('legacy_pdf_reference')->nullable()->after('pdf_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('doctor_notes', fn (Blueprint $table) => $table->dropColumn(['actor_name', 'deleted_by_name']));
        Schema::table('contracts', fn (Blueprint $table) => $table->dropColumn('legacy_pdf_reference'));
    }
};

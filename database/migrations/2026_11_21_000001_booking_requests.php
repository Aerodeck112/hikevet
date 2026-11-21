<?php

use App\Support\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programarile se fac pe site, ca cereri: clientul alege ziua si intervalul preferat (sau o ora exacta,
 * daca medicul are orar), iar clinica confirma din admin. Medicul poate ramane „oricare”.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('team_member_id')->nullable()->change();
            $table->string('preferred_period', 20)->nullable()->after('ends_at');
        });

        // Butoanele „Programează-te” duc de acum la formularul propriu (cererea clinicii, 21.11.2026).
        Settings::set('booking.mode', 'internal');
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('preferred_period');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ID-urile WordPress ale dosarului si ale vizitei unui consimtamant importat. Se pastreaza separat, ca legaturile
 * spre dosarele din platforma noua sa poata fi refacute oricand (CampaignRelinker), fara sa se piarda originalul.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consents', function (Blueprint $table) {
            $table->unsignedBigInteger('wp_case_id')->nullable();
            $table->unsignedBigInteger('wp_visit_group_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('consents', fn (Blueprint $t) => $t->dropColumn(['wp_case_id', 'wp_visit_group_id']));
    }
};

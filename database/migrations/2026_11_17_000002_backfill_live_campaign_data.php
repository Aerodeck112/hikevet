<?php

use App\Services\Import\CampaignRelinker;
use App\Services\Import\WordPressSource;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Datele pentru campania activa, separat de schimbarile de structura: pe MySQL structura nu se anuleaza la eroare,
 * iar pasul de date trebuie sa poata fi reluat. Operatiile sunt repetabile.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Permisiunile noi ale campaniei (campaign.manage, campaign.validate) pentru instalarile existente.
        if (Schema::hasTable('roles')) {
            (new RolesSeeder)->run();
        }

        // Dosarele deja importate pornesc cu starea curenta = randul original, cu legaturile traduse in ID-urile noi.
        CampaignRelinker::run();
        foreach (DB::table('sterilization_appointments')->whereNotNull('source_row')->get(['id', 'source_row']) as $a) {
            $r = json_decode($a->source_row, true) ?: [];
            DB::table('sterilization_appointments')->where('id', $a->id)->update(array_filter([
                'title' => $r['title'] ?? null, 'client_email' => $r['client_email'] ?? null, 'client_phone' => $r['client_phone'] ?? null,
                'source' => $r['source'] ?? null, 'notes' => $r['notes'] ?? null, 'reminder_enabled' => $r['reminder_enabled'] ?? null,
                'reminder_days' => isset($r['reminder_days']) ? (int) $r['reminder_days'] : null,
                'reminder_sent_at' => WordPressSource::date($r['reminder_sent_at'] ?? ''), 'created_by_name' => $r['created_by'] ?? null,
                'created_at' => WordPressSource::date($r['created_at'] ?? ''), 'updated_at' => WordPressSource::date($r['updated_at'] ?? ''),
            ], fn ($v) => $v !== null && $v !== ''));
        }
    }

    public function down(): void
    {
        // Nimic de anulat: datele sunt derivate din randurile originale, care raman neschimbate.
    }
};

<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dupa import, legaturile din dosarele WordPress trimit inca la ID-uri WordPress (vizita, programarea initiala,
 * consimtamantul). Aici sunt traduse in ID-urile din platforma noua, in `data` si in coloanele de legatura.
 * Randul original (`source_row`) nu se atinge. Dosarele deja modificate in platforma noua nu sunt rescrise.
 * Operatia este repetabila: porneste mereu de la randul original.
 */
class CampaignRelinker
{
    /** @return array{cases:int,appointments:int,email_logs:int,consents:int} */
    public static function run(): array
    {
        $caseMap = DB::table('sterilization_cases')->whereNotNull('source_id')->pluck('id', 'source_id')->all();
        $appointmentMap = DB::table('sterilization_appointments')->whereNotNull('source_id')->pluck('id', 'source_id')->all();
        $consentMap = DB::table('consents')->whereNotNull('wp_post_id')->pluck('id', 'wp_post_id')->all();
        $stats = ['cases' => 0, 'appointments' => 0, 'email_logs' => 0, 'consents' => 0];

        // Consimtamintele importate: dosarul si vizita din WordPress, plus ID-urile dosarelor din lista vizitei.
        if (Schema::hasColumn('consents', 'wp_case_id')) {
            foreach (DB::table('consents')->whereNotNull('wp_post_id')->get(['id', 'wp_case_id', 'wp_visit_group_id', 'visit_patients']) as $c) {
                $patients = json_decode((string) $c->visit_patients, true);
                if (is_array($patients)) {
                    foreach ($patients as &$p) {
                        if (is_array($p) && isset($p['case_id']) && ! isset($p['wp_case_id'])) {
                            $p['wp_case_id'] = (int) $p['case_id'];
                            $p['case_id'] = (int) ($caseMap[(int) $p['case_id']] ?? 0);
                        }
                    }
                    unset($p);
                }
                DB::table('consents')->where('id', $c->id)->update([
                    'sterilization_case_id' => $c->wp_case_id ? ($caseMap[(int) $c->wp_case_id] ?? null) : null,
                    'visit_group_id' => $c->wp_visit_group_id ? ($caseMap[(int) $c->wp_visit_group_id] ?? null) : null,
                    'visit_patients' => is_array($patients) ? json_encode($patients, JSON_UNESCAPED_UNICODE) : $c->visit_patients,
                ]);
            }
        }

        foreach (DB::table('sterilization_cases')->whereNotNull('source_row')->whereNull('live_updated_at')->orderBy('id')->get(['id', 'source_row']) as $case) {
            $data = json_decode((string) $case->source_row, true) ?: [];
            $map = fn (array $m, $old) => (int) ($m[(int) $old] ?? 0);
            $data['visit_group_id'] = (int) ($data['visit_group_id'] ?? 0) ? $map($caseMap, $data['visit_group_id']) : 0;
            $data['initial_appointment_id'] = (int) ($data['initial_appointment_id'] ?? 0) ? $map($appointmentMap, $data['initial_appointment_id']) : 0;
            $consent = (int) ($data['consent_id'] ?? 0) ? $map($consentMap, $data['consent_id']) : 0;
            $shared = (int) ($data['shared_consent_id'] ?? 0) ? $map($consentMap, $data['shared_consent_id']) : 0;
            $data['consent_id'] = $consent ?: $shared;
            $data['shared_consent_id'] = $shared ?: $consent;
            $date = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) && ! str_starts_with((string) $v, '0000') ? $v : null;
            $text = fn ($v, $max) => trim((string) $v) === '' ? null : mb_substr(trim((string) $v), 0, $max);
            DB::table('sterilization_cases')->where('id', $case->id)->update([
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'visit_group_id' => $data['visit_group_id'] ?: null,
                'consent_id' => $data['consent_id'] ?: null, 'photo_upload_token' => $text($data['photo_upload_token'] ?? '', 64),
                'data_fisa' => $date($data['data_fisa'] ?? ''), 'ora_start' => $text($data['ora_start'] ?? '', 10),
                'procedura_programata' => $text($data['procedura_programata'] ?? '', 255), 'responsabil_caz' => $text($data['responsabil_caz'] ?? '', 255),
            ]);
            if ($data['consent_id']) {
                DB::table('consents')->where('id', $data['consent_id'])->update(['sterilization_case_id' => $data['visit_group_id'] ?: $case->id]);
                $stats['consents']++;
            }
            $stats['cases']++;
        }

        foreach (DB::table('sterilization_appointments')->whereNotNull('source_row')->whereNull('updated_by_name')->get(['id', 'source_case_id']) as $a) {
            DB::table('sterilization_appointments')->where('id', $a->id)->update(['sterilization_case_id' => $caseMap[(int) $a->source_case_id] ?? null]);
            $stats['appointments']++;
        }
        foreach (DB::table('sterilization_email_logs')->whereNotNull('source_row')->whereNull('appointment_id')->get(['id', 'source_row']) as $log) {
            $row = json_decode((string) $log->source_row, true) ?: [];
            if ($id = $appointmentMap[(int) ($row['appointment_id'] ?? 0)] ?? null) {
                DB::table('sterilization_email_logs')->where('id', $log->id)->update(['appointment_id' => $id]);
                $stats['email_logs']++;
            }
        }

        return $stats;
    }
}

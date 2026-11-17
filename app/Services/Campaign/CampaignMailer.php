<?php

namespace App\Services\Campaign;

use App\Models\SterilizationAppointment;
use App\Models\SterilizationCase;
use App\Models\SterilizationEmailLog;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emailurile campaniei, cu textele si subiectele din plugin: confirmarea programarii, reamintirea automata,
 * rezumatul de externare (si versiunea ACTUALIZATA), follow-up-ul la 48 de ore. Fiecare trimitere se scrie in jurnal.
 */
class CampaignMailer
{
    /** ABATERE: pluginul reincerca follow-up-ul esuat la fiecare ora, la nesfarsit (80.784 de incercari esuate in backup). */
    public const MAX_AUTOMATIC_FOLLOWUP_ATTEMPTS = 3;

    /** Copiile interne ale emailurilor campaniei (clinic_copy_addresses). */
    public static function copyAddresses(): array
    {
        return Settings::emails('campaign.copy_addresses') ?: ['site@hikevet.com'];
    }

    /** @return array{0:bool,1:string} */
    private function deliver(string $to, string $subject, string $html): array
    {
        try {
            Mail::html($html, function ($m) use ($to, $subject) {
                $m->to($to)->subject($subject);
                if ($cc = self::copyAddresses()) {
                    $m->cc($cc);
                }
            });

            return [true, ''];
        } catch (Throwable $e) {
            report($e);

            return [false, 'Trimiterea a eșuat: '.mb_substr($e->getMessage(), 0, 300)];
        }
    }

    private function log(?int $appointmentId, ?int $caseId, string $to, string $subject, string $type, bool $sent, string $error): void
    {
        SterilizationEmailLog::query()->create(['appointment_id' => $appointmentId, 'sterilization_case_id' => $caseId, 'to_email' => $to,
            'cc_email' => implode(', ', self::copyAddresses()), 'subject' => mb_substr($subject, 0, 250), 'email_type' => $type,
            'status' => $sent ? 'sent' : 'failed', 'error_message' => $error ?: null, 'sent_at' => $sent ? now() : null, 'created_at' => now()]);
    }

    /** @return array{0:bool,1:string} booking_confirmation | manual | automatic */
    public function sendAppointmentEmail(SterilizationAppointment $a, string $type, ?User $actor): array
    {
        $to = filter_var((string) $a->client_email, FILTER_VALIDATE_EMAIL) ? (string) $a->client_email : '';
        if ($to === '') {
            return [false, 'Clientul nu are o adresă de email validă.'];
        }
        $patient = trim((string) $a->patient_name) ?: 'pacient';
        $date = $a->appointment_date?->format('d.m.Y') ?? '';
        $subject = ($type === 'automatic' ? 'HiKeVet – Reamintire intervenție chirurgicală' : 'HiKeVet – Programare intervenție chirurgicală')
            .' pentru '.$patient.($date ? ' – '.$date : '');
        [$sent, $error] = $this->deliver($to, $subject, self::appointmentBody($a, $type));
        $this->log($a->id, $a->sterilization_case_id, $to, $subject, $type, $sent, $error);

        $updates = ['email_last_status' => $sent ? 'sent' : 'failed', 'email_last_at' => now(), 'email_last_error' => $error ?: null, 'updated_by_name' => $actor?->name ?: 'Sistem'];
        if ($type === 'manual') {
            $updates['reminder_manual_sent_at'] = now();
        }
        if ($type === 'automatic') {
            $updates['reminder_sent_at'] = now();
        }
        $a->update($updates);

        return [$sent, $error];
    }

    /** Rezumatul de externare; la retrimitere manuala dupa un email trimis devine versiunea ACTUALIZATA. */
    public function sendFinalSummary(SterilizationCase $case, bool $manual, ?User $actor): array
    {
        $row = $case->row();
        $corrected = $manual && ! empty($row['final_summary_email_sent_at']);
        $type = $corrected ? 'postop_instant_manual_corrected' : ($manual ? 'postop_instant_manual' : 'postop_instant_auto');
        $patient = trim((string) $row['nume_pacient']);
        $subject = $corrected ? 'HiKeVet – Rezumat postoperator ACTUALIZAT'.($patient ? ' pentru '.$patient : '')
            : 'HiKeVet – Email postoperator și rezumat de externare'.($patient ? ' pentru '.$patient : '');
        $to = filter_var((string) $row['email'], FILTER_VALIDATE_EMAIL) ? (string) $row['email'] : '';
        [$sent, $error] = $to === '' ? [false, 'Adresa de email a clientului lipsește sau nu este validă.']
            : $this->deliver($to, $subject, self::postopSummaryBody($row, $corrected));
        $this->log(null, $case->id, $to, $subject, $type, $sent, $error);

        $row['final_summary_email_sent_at'] = $sent ? CaseFields::now() : ($row['final_summary_email_sent_at'] ?? '');
        $row['final_summary_email_status'] = $sent ? 'sent' : 'failed';
        $row['final_summary_email_error'] = $error;
        app(CampaignCases::class)->persist($case, $row, $actor, 'email', 'externare', null, false);

        return [$sent, $error];
    }

    public static function postopDueAt(array $row): string
    {
        $date = CaseFields::sanitizeDate($row['data_externare'] ?? '');
        if (! $date) {
            return '';
        }
        $time = CaseFields::sanitizeTime($row['ora_externare'] ?? '') ?: '10:00';

        return Carbon::parse($date.' '.$time.':00')->addHours(48)->format('Y-m-d H:i:s');
    }

    /** Follow-up-ul la 48 de ore dupa externare. */
    public function sendFollowup(SterilizationCase $case, bool $manual, ?User $actor): array
    {
        $row = $case->row();
        $patient = trim((string) $row['nume_pacient']);
        $subject = 'HiKeVet – Cum decurge recuperarea'.($patient ? ' lui '.$patient : '').'?';
        $to = filter_var((string) $row['email'], FILTER_VALIDATE_EMAIL) ? (string) $row['email'] : '';
        [$sent, $error] = $to === '' ? [false, 'Adresa de email a clientului lipsește sau nu este validă.'] : $this->deliver($to, $subject, self::followupBody($row));
        $this->log(null, $case->id, $to, $subject, $manual ? 'followup_48h_manual' : 'followup_48h_auto', $sent, $error);

        $row['postop_email_status'] = $sent ? 'sent' : 'failed';
        $row['postop_email_error'] = $error;
        if ($sent) {
            $row['postop_email_sent_at'] = CaseFields::now();
        }
        if ($manual) {
            $row['postop_email_manual_at'] = CaseFields::now();
        }
        app(CampaignCases::class)->persist($case, $row, $actor, 'email', 'externare', null, false);

        return [$sent, $error];
    }

    /** run_postop_emails: follow-up automat pentru externarile ajunse la 48 de ore, cu numar limitat de reincercari. */
    public function runFollowups(?Carbon $now = null): array
    {
        $now ??= now();
        $sent = $failed = $skipped = 0;
        $cases = SterilizationCase::query()->active()->whereNotNull('data_externare')->orderBy('data_externare')->orderBy('id')->get();
        foreach ($cases as $case) {
            $row = $case->row();
            if (! empty($row['postop_email_sent_at'])) {
                continue;
            }
            $due = self::postopDueAt($row);
            if ($due === '' || Carbon::parse($due)->gt($now)) {
                continue;
            }
            // Incercarile se numara de la ultima externare salvata (o externare noua reporneste numaratoarea).
            $since = CaseFields::sanitizeDate(substr((string) $row['timestamp_externare'], 0, 10)) ? Carbon::parse($row['timestamp_externare']) : Carbon::parse($due)->subHours(48);
            $attempts = SterilizationEmailLog::query()->where('sterilization_case_id', $case->id)->where('email_type', 'followup_48h_auto')
                ->where('status', 'failed')->where('created_at', '>=', $since)->count();
            if ($attempts >= self::MAX_AUTOMATIC_FOLLOWUP_ATTEMPTS) {
                $skipped++;

                continue;
            }
            [$ok] = $this->sendFollowup($case, false, null);
            $ok ? $sent++ : $failed++;
        }

        return ['sent' => $sent, 'failed' => $failed, 'stopped_after_retries' => $skipped];
    }

    private static function e(?string $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    public static function appointmentBody(SterilizationAppointment $a, string $type): string
    {
        $e = fn ($v) => self::e($v);
        $patient = $e(trim((string) $a->patient_name) ?: 'pacientul dumneavoastră');
        $date = $a->appointment_date?->format('d.m.Y') ?? '-';
        $time = trim((string) $a->start_time) ?: '-';
        $procedure = $e(trim((string) $a->appointment_type) ?: 'intervenție chirurgicală');
        $auto = $type === 'automatic';
        $intro = $auto
            ? 'Vă reamintim că pacientul <strong>'.$patient.'</strong> este programat pentru intervenția chirurgicală în data de <strong>'.$e($date).'</strong>.'
            : 'Vă confirmăm programarea pacientului <strong>'.$patient.'</strong> pentru intervenția chirurgicală în data de <strong>'.$e($date).'</strong>, la ora <strong>'.$e($time).'</strong>.';
        $schedule = $auto ? $procedure.' · '.$e($date) : $procedure.' · '.$e($date).' · ora '.$e($time);
        $arrival = $auto
            ? '<div style="margin:18px 0;padding:18px 20px;border-radius:14px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;">'
                .'<div style="font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;">Interval de prezentare la clinică</div>'
                .'<div style="margin-top:7px;font-size:22px;font-weight:800;color:#0f766e;">08:30 – 12:00</div>'
                .'<p style="margin:8px 0 0;">Vă rugăm să ajungeți cu animalul în acest interval în ziua intervenției. <strong>Ora exactă poate fi concretizată telefonic cu clinica</strong> la <a href="tel:+40771280345" style="color:#0f766e;font-weight:700;">0771 280 345</a>.</p>'
                .'</div>'
            : '';
        $important = $auto
            ? '<strong>Important:</strong> Nu este necesar să veniți la ora internă din calendarul clinicii. Pentru organizarea intervențiilor, animalele sunt primite în intervalul <strong>08:30–12:00</strong>. Vă rugăm să aveți asupra dumneavoastră carnetul de sănătate al animalului, dacă acesta există.'
            : '<strong>Important:</strong> Vă rugăm să respectați ora programării și să aveți asupra dumneavoastră carnetul de sănătate al animalului, dacă acesta există.';
        $step = fn ($n, $title, $text, $last = false) => '<div style="margin:0'.($last ? '' : ' 0 14px').';padding-left:40px;position:relative;"><span style="position:absolute;left:0;top:0;width:28px;height:28px;line-height:28px;text-align:center;border-radius:50%;background:#0f766e;color:#fff;font-weight:700;">'.$n.'</span><strong>'.$title.'</strong><br>'.$text.'</div>';

        return '<div style="margin:0;padding:24px 10px;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;line-height:1.65;">'
            .'<div style="max-width:680px;margin:0 auto;background:#ffffff;border:1px solid #dbe4ea;border-radius:20px;overflow:hidden;box-shadow:0 14px 38px rgba(15,23,42,.10);">'
            .'<div style="padding:28px 30px;background:linear-gradient(135deg,#0f766e,#15803d);color:#ffffff;">'
            .'<div style="font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#d1fae5;">Clinica Veterinară HiKeVet</div>'
            .'<h1 style="margin:8px 0 0;color:#ffffff;font-size:27px;line-height:1.25;">'.($auto ? 'Reamintire intervenție chirurgicală' : 'Programare pentru intervenție chirurgicală').'</h1>'
            .'</div><div style="padding:30px;">'
            .'<p style="margin:0 0 16px;">Bună ziua,</p><p style="margin:0 0 20px;">'.$intro.'</p>'
            .'<div style="margin:20px 0;padding:18px 20px;border-radius:14px;background:#ecfdf5;border:1px solid #bbf7d0;">'
            .'<div style="font-size:13px;color:#166534;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Detalii programare</div>'
            .'<div style="margin-top:8px;font-size:17px;"><strong>'.$patient.'</strong></div><div style="margin-top:4px;color:#334155;">'.$schedule.'</div></div>'
            .$arrival
            .'<p style="margin:0 0 14px;">Pentru desfășurarea procedurii în condiții de siguranță, vă rugăm să respectați următoarele recomandări:</p><div style="padding:4px 0 4px 2px;">'
            .$step(1, 'Repaus alimentar', 'Nu administrați hrană cu cel puțin 8 ore înaintea intervenției. Apa poate rămâne disponibilă până în dimineața procedurii, dacă medicul nu a recomandat altfel.')
            .$step(2, 'Medicamente și suplimente', 'Nu administrați medicamente sau suplimente în ziua intervenției decât la indicația medicului veterinar.')
            .$step(3, 'Starea generală', 'Informați echipa medicală dacă apar vărsături, diaree, tuse, secreții, apatie sau orice modificare a stării generale.')
            .$step(4, 'Transport în siguranță', 'Câinii vor fi aduși în lesă, iar pisicile într-un transportor adecvat.', true)
            .'</div><div style="margin:22px 0 0;padding:16px 18px;border-radius:13px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;">'.$important.'</div>'
            .'<p style="margin:24px 0 0;">Vă mulțumim,<br><strong>Echipa Clinicii Veterinare HiKeVet</strong></p></div>'
            .'<div style="padding:18px 30px;background:#f8fafc;border-top:1px solid #e2e8f0;color:#64748b;font-size:12px;text-align:center;">'
            .'Clinica Veterinară HiKeVet · Str. Băneasa nr. 23, Târgu Mureș · 0771 280 345 · contact@hikevet.com</div></div></div>';
    }

    /** @return array<string,string> rezumatul consultatiei (consultation_summary_details) */
    public static function consultationDetails(array $r): array
    {
        $join = fn (string $a, string $b) => trim(((string) ($r[$a] ?? '')).' '.((string) ($r[$b] ?? ''))) ?: '-';

        return [
            'Pacient' => $r['nume_pacient'] ?: '-', 'Rasă' => $r['rasa'] ?: '-', 'Vârstă' => $r['varsta_estimata'] ?: '-',
            'Specie / sex' => trim(($r['specie'] ?: '').' / '.($r['sex'] ?: '')) ?: '-', 'Vaccinat' => $r['vaccinat'] ?: '-', 'Habitat' => $r['habitat'] ?: '-',
            'Comportament neobișnuit' => $join('comportament_neobisnuit', 'comportament_detalii'), 'Deparazitat' => $join('deparazitat', 'deparazitare_detalii'),
            'Diaree / vomă' => $join('diaree_voma', 'diaree_voma_detalii'), 'Tip hrană' => $join('tip_hrana_select', 'tip_hrana_detalii'),
            'Greutate reală' => ($r['greutate_reala'] ?? '') !== '' && $r['greutate_reala'] !== null ? $r['greutate_reala'].' kg' : '-',
            'Temperatură' => ($r['temperatura'] ?? '') !== '' && $r['temperatura'] !== null ? $r['temperatura'].' °C' : '-',
            'FR (respirații/minut)' => $r['frecventa_respiratorie'] ?: '-', 'FC (bătăi/minut)' => $r['frecventa_cardiaca'] ?: '-',
            'Culoare mucoase aparente' => $r['culoare_mucoase'] ?: '-', 'Boli cronice / alergii / medicație / intervenții' => $r['istoric_medical'] ?: '-',
            'Microcip' => $r['microcip'] ?: '-', 'Pacient apt pentru intervenție' => $r['pacient_apt'] ?: '-', 'Motiv neapt' => $r['motiv_amanare'] ?: '-',
            'Mențiuni medicale' => $r['mentiuni_consult_preoperatorii'] ?: '-', 'Doctor responsabil' => $r['responsabil_caz'] ?: '-',
        ];
    }

    /** Pentru proprietar: datele generale ale interventiei; substantele, dozele si protocolul raman interne. */
    public static function interventionDetails(array $r): array
    {
        return [
            'Ora început' => $r['ora_start'] ?: '-', 'Ora final' => $r['ora_final'] ?: '-',
            'Intervenție efectuată' => $r['procedura_efectuata'] ?: ($r['procedura_programata'] ?: '-'), 'Tip anestezie generală' => $r['tip_anestezie'] ?: '-',
            'Medic operator' => $r['medic_operator'] ?: '-', 'Asistent' => $r['tehnician_asistent'] ?: '-',
            'Mențiuni intra/postoperatorii' => $r['mentiuni_intra_postoperatorii'] ?: '-',
        ];
    }

    private static function table(array $details): string
    {
        $out = '<table style="width:100%;border-collapse:collapse;margin:12px 0 18px;">';
        foreach ($details as $label => $value) {
            $out .= '<tr><td style="width:38%;padding:7px;border:1px solid #dbeafe;font-weight:700;background:#f8fafc;">'.self::e($label).'</td><td style="padding:7px;border:1px solid #dbeafe;">'.nl2br(self::e((string) $value)).'</td></tr>';
        }

        return $out.'</table>';
    }

    public static function postopSummaryBody(array $r, bool $corrected = false): string
    {
        $owner = self::e(trim((string) $r['nume_proprietar']) ?: 'Bună ziua');
        $patient = self::e(trim((string) ($r['nume_pacient'] ?: 'blănosul dumneavoastră')));
        $cell = 'padding:7px;border:1px solid #dbeafe;';
        $table = '<table style="width:100%;border-collapse:collapse;margin:14px 0;">'
            .'<tr><td style="'.$cell.'font-weight:700;">Proprietar</td><td style="'.$cell.'">'.self::e($r['nume_proprietar']).'</td></tr>'
            .'<tr><td style="'.$cell.'font-weight:700;">Telefon</td><td style="'.$cell.'">'.self::e($r['telefon']).'</td></tr>'
            .'<tr><td style="'.$cell.'font-weight:700;">Pacient</td><td style="'.$cell.'">'.$patient.' · '.self::e(trim($r['specie'].' / '.$r['sex'])).'</td></tr>'
            .'<tr><td style="'.$cell.'font-weight:700;">Data / ora externării</td><td style="'.$cell.'">'.self::e(trim(($r['data_externare'] ?: '-').' '.($r['ora_externare'] ?: ''))).'</td></tr>'
            .'<tr><td style="'.$cell.'font-weight:700;">Recontrol postoperator recomandat</td><td style="'.$cell.'">'.self::e($r['control_recomandat'] ?: '-').'</td></tr>'
            .'</table>';
        $notice = $corrected
            ? '<div style="margin:0 0 18px;padding:16px 18px;border-radius:12px;background:#fff7ed;border:1px solid #fdba74;color:#9a3412;"><strong>Actualizare dosar medical</strong><br>Acest email conține datele finale corectate din dosarul pacientului. Vă rugăm să considerați acest mesaj drept versiunea actualizată a rezumatului transmis anterior.</div>'
            : '';

        return '<div style="font-family:Arial,sans-serif;line-height:1.65;color:#0f172a;max-width:700px;margin:auto;">'
            .'<h2 style="margin:0 0 16px;color:#0f766e;">'.($corrected ? 'Rezumat postoperator actualizat HiKeVet' : 'Rezumat postoperator HiKeVet').'</h2>'
            .$notice
            .'<p>Bună ziua '.$owner.',</p>'
            .'<p>'.($corrected ? 'Vă retransmitem rezumatul final corectat pentru ' : 'Vă transmitem rezumatul de externare pentru ').'<strong>'.$patient.'</strong>.</p>'
            .$table
            .'<h3 style="color:#0f766e;">Fișa pacientului / consultație preoperatorie</h3>'.self::table(self::consultationDetails($r))
            .'<h3 style="color:#0f766e;">Intervenție intra/postoperatorie</h3>'.self::table(self::interventionDetails($r))
            .'<h3 style="color:#0f766e;">Recomandări postoperatorii</h3>'
            .'<div style="white-space:pre-line;padding:14px 16px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;">'.self::e(CaseFields::postopRecommendations($r)).'</div>'
            .'<p style="margin-top:16px;">Recontrolul postoperator se face la cel mult 14 zile de la intervenție. Pentru urgențe puteți apela 0733-449966.</p>'
            .'<p style="color:#64748b;font-size:12px;">Clinica Veterinară HiKeVet · Str. Băneasa nr. 23, Târgu Mureș · +40 771 280 345 · contact@hikevet.com</p>'
            .'</div>';
    }

    public static function followupBody(array $r): string
    {
        $owner = self::e(trim((string) $r['nume_proprietar']) ?: 'Bună ziua');
        $patient = self::e(trim((string) ($r['nume_pacient'] ?: 'pacientul dumneavoastră')));

        return '<div style="font-family:Arial,sans-serif;line-height:1.65;color:#0f172a;max-width:680px;margin:auto;background:#ffffff;">'
            .'<div style="padding:26px;border-radius:18px 18px 0 0;background:linear-gradient(135deg,#0f766e,#15803d);color:#ffffff;">'
            .'<h2 style="margin:0;color:#ffffff;font-size:26px;">Cum decurge recuperarea?</h2>'
            .'<p style="margin:8px 0 0;color:#dcfce7;">HiKeVet este alături de dumneavoastră și după intervenție.</p></div>'
            .'<div style="padding:26px;border:1px solid #dbe4ea;border-top:0;border-radius:0 0 18px 18px;">'
            .'<p>Bună ziua, <strong>'.$owner.'</strong>,</p>'
            .'<p>Vă mulțumim că ați ales <strong>HiKeVet</strong>. Sperăm că recuperarea lui <strong>'.$patient.'</strong> decurge bine și fără probleme.</p>'
            .'<div style="padding:18px;margin:18px 0;border-radius:14px;background:#ecfdf5;border:1px solid #bbf7d0;">'
            .'<h3 style="margin:0 0 10px;color:#166534;">Consult postoperator gratuit</h3>'
            .'<p style="margin:0;">Beneficiați de un <strong>consult postoperator gratuit în următoarele 30 de zile</strong>. La consult verificăm evoluția postoperatorie și răspundem întrebărilor despre recuperare.</p></div>'
            .'<h3 style="color:#0f766e;">Recomandări pentru perioada următoare</h3><ul style="padding-left:22px;">'
            .'<li>Vă recomandăm vaccinarea, microciparea și deparazitarea pacientului.</li>'
            .'<li>În următoarele <strong>60 de zile beneficiați de 20% reducere</strong> la aceste servicii.</li>'
            .'<li><strong>Carnetul de sănătate este gratuit.</strong></li></ul>'
            .'<div style="padding:20px;margin:22px 0;border-radius:16px;background:linear-gradient(135deg,#eff6ff,#f0fdf4);border:1px solid #bfdbfe;text-align:center;">'
            .'<h3 style="margin:0 0 8px;color:#0f766e;">Știi de abonamentele HiKeVet?</h3>'
            .'<p style="margin:0 0 16px;">Cu un abonament HiKeVet ai grijă de sănătatea companionului tău pe tot parcursul anului, mai simplu și mai avantajos. Descoperă opțiunile potrivite pentru voi și beneficiile incluse.</p>'
            .'<a href="https://hikevet.com/abonamente/" target="_blank" rel="noopener" style="display:inline-block;padding:12px 20px;border-radius:999px;background:#0f766e;color:#ffffff;text-decoration:none;font-weight:700;">Vreau abonament</a></div>'
            .'<div style="padding:16px;margin-top:20px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;">'
            .'<strong>Programări și informații HiKeVet</strong><br>Strada Băneasa 23, Târgu Mureș<br>'
            .'Telefon: <a href="tel:+40771280345" style="color:#0f766e;">+40 771 280 345</a><br>'
            .'Email: <a href="mailto:contact@hikevet.com" style="color:#0f766e;">contact@hikevet.com</a><br>'
            .'Urgențe: <a href="tel:0733449966" style="color:#0f766e;">0733 449 966</a></div>'
            .'<p style="margin-top:20px;">Cu grijă,<br><strong>Echipa HiKeVet</strong></p></div></div>';
    }
}

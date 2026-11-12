<?php

namespace App\Services\Sterilization;

use App\Models\SterilizationCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/** Exporturi din arhiva de sterilizari. Valorile sunt cele originale din sursa, fara recalculari. */
class ArchiveExporter
{
    /** Gruparea campurilor din hv_st_cases dupa etapele fluxului din plugin. */
    public const SECTIONS = [
        'Recepție și proprietar' => ['cod_caz', 'data_programare', 'ora_programare', 'data_prezentare', 'nume_proprietar', 'telefon', 'email', 'adresa_ci', 'serie_numar_ci', 'localitate', 'status_caz', 'observatii_initiale'],
        'Pacient' => ['nume_pacient', 'specie', 'sex', 'rasa', 'culoare_semnalmente', 'varsta_estimata', 'greutate_reala', 'greutate_estimativa', 'microcip', 'vaccinat', 'data_vaccinare', 'animal_cu_stapan'],
        'Consultație preoperatorie' => ['temperatura', 'frecventa_respiratorie', 'frecventa_cardiaca', 'culoare_mucoase', 'pacient_apt', 'motiv_amanare', 'risc_anestezic', 'habitat', 'deparazitat', 'diaree_voma', 'tip_hrana_select', 'istoric_medical', 'mentiuni_consult_preoperatorii', 'completat_preoperator_de', 'status_preoperator'],
        'Intervenție' => ['data_interventie', 'data_raportare_primarie', 'ora_start', 'ora_final', 'procedura_programata', 'procedura_efectuata', 'tip_anestezie', 'protocol_anestezic', 'substanta1', 'doza1', 'substanta2', 'doza2', 'substanta3', 'doza3', 'alte_substante', 'complicatii', 'mentiuni_intra_postoperatorii', 'medic_operator', 'tehnician_asistent', 'status_interventie'],
        'Număr pacient și fotografie' => ['numar_pacient', 'numar_pacient_vechi', 'numar_pacient_raport', 'numar_pacient_raport_sursa', 'numar_pacient_foto_confirmat', 'numar_pacient_foto_verificat_de', 'numar_pacient_foto_verificat_at', 'poza_pacient', 'photo_uploaded_at', 'photo_upload_source'],
        'Documente și consimțământ' => ['consimtamant_semnat', 'gdpr_semnat', 'consimtamant_acceptare_proceduri', 'document_gdpr', 'ci_verificat', 'documente_scanate', 'observatii_documente', 'status_documente', 'consent_id', 'shared_consent_id', 'visit_group_id'],
        'Externare' => ['data_externare', 'ora_externare', 'recomandari_postoperatorii_text', 'observatii_externare', 'control_recomandat', 'followup_efectuat', 'status_externare', 'postop_email_sent_at', 'postop_email_status', 'final_summary_email_sent_at', 'final_summary_email_status'],
        'Validare Primărie' => ['validat_pentru_primarie', 'validat_de', 'data_validare', 'observatii_validare', 'date_proprietar_complete', 'date_pacient_complete', 'consimtamant_complet', 'gdpr_complet', 'anestezie_completata', 'substante_completate', 'medic_operator_completat', 'poza_incarcata', 'complicatii_mentiuni_completate'],
        'Urme de sistem' => ['creat_de', 'timestamp_creat', 'actualizat_de', 'timestamp_actualizat'],
    ];

    public const CSV_COLUMNS = ['cod_caz', 'numar_pacient', 'data_interventie', 'nume_proprietar', 'telefon', 'localitate', 'adresa_ci', 'specie', 'sex', 'nume_pacient', 'rasa', 'microcip',
        'procedura_efectuata', 'tip_anestezie', 'medic_operator', 'status_interventie', 'validat_pentru_primarie', 'validat_de', 'data_validare'];

    /** @return array<string, array<string, string>> sectiune => [camp => valoare]; valorile goale apar ca "Lipsă în sursă" doar daca sunt asteptate */
    public static function sections(SterilizationCase $case): array
    {
        $row = $case->source_row;
        $missing = $case->missing_in_source ?? [];
        $out = [];

        foreach (self::SECTIONS as $title => $fields) {
            foreach ($fields as $field) {
                $value = trim((string) ($row[$field] ?? ''));
                $isMissing = in_array($field, $missing, true) || collect($missing)->contains(fn ($m) => str_starts_with($m, $field.' ('));
                if ($value === '' && ! $isMissing) {
                    continue;
                }
                $out[$title][$field] = $value === '' ? 'Lipsă în sursă' : $value.($isMissing ? '  (valoare invalidă în sursă)' : '');
            }
        }

        return $out;
    }

    public function csv(Builder $query, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, self::CSV_COLUMNS, ';');
            $query->clone()->reorder('source_id')->chunk(500, function ($cases) use ($out) {
                foreach ($cases as $case) {
                    fputcsv($out, array_map(fn ($c) => self::csvSafe((string) ($case->source_row[$c] ?? '')), self::CSV_COLUMNS), ';');
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }

    /** Manifest + documentele originale ale dosarului, fiecare cu amprenta SHA-256 verificabila. */
    public function caseZip(SterilizationCase $case): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'hvzip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        $manifest = ['cod_caz' => $case->cod_caz, 'id_original' => $case->source_id, 'amprenta_rand_sursa_sha256' => $case->row_sha256,
            'importat_la' => $case->imported_at?->toIso8601String(), 'date_lipsa_in_sursa' => $case->missing_in_source ?? [], 'documente' => []];

        foreach ($case->documents as $i => $document) {
            $entry = ['tip' => $document->kind, 'referinta_originala' => $document->source_reference, 'sha256' => $document->sha256, 'lipsa_in_sursa' => $document->is_missing];
            if (! $document->is_missing && $document->stored_path && Storage::disk('private')->exists($document->stored_path)) {
                $name = sprintf('documente/%02d-%s.%s', $i + 1, $document->kind, pathinfo($document->stored_path, PATHINFO_EXTENSION));
                $zip->addFromString($name, Storage::disk('private')->get($document->stored_path));
                $entry['fisier'] = $name;
            }
            $manifest['documente'][] = $entry;
        }

        $zip->addFromString('dosar-original.json', json_encode($case->source_row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        return response()->download($path, 'dosar-'.$case->cod_caz.'.zip', ['Cache-Control' => 'no-store, private'])->deleteFileAfterSend();
    }

    /** Impiedica interpretarea valorilor ca formule in Excel. */
    private static function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /**
     * Raportarea lunara reconstruita din valorile originale: interventiile Finalizat / Operat
     * dupa data_interventie, pe specie si sex (centralizator) si lista lor (desfasurator).
     */
    public function monthlyReport(string $month): array
    {
        $cases = SterilizationCase::query()->whereIn('status_interventie', ['Finalizat', 'Operat'])
            ->whereBetween('data_interventie', [$month.'-01', date('Y-m-t', strtotime($month.'-01'))])->orderBy('data_interventie')->orderBy('source_id')->get();

        $summary = [];
        foreach ($cases as $case) {
            $key = trim(($case->specie ?: 'Specie lipsă în sursă').' / '.($case->sex ?: 'sex lipsă în sursă'));
            $summary[$key] = ($summary[$key] ?? 0) + 1;
        }
        ksort($summary);

        return ['month' => $month, 'cases' => $cases, 'summary' => $summary, 'total' => $cases->count(),
            'already_reported' => $cases->filter(fn ($c) => $c->reported_month && $c->reported_month !== $month)->count(),
            'with_missing' => $cases->filter(fn ($c) => $c->missing_in_source)->count()];
    }

    /** @return array<string,int> luna => numar de interventii finalizate; "fara data" pentru cele fara data valida */
    public function finalizedByMonth(): array
    {
        $out = [];
        foreach (SterilizationCase::query()->whereIn('status_interventie', ['Finalizat', 'Operat'])->get(['data_interventie']) as $case) {
            $key = $case->data_interventie?->format('Y-m') ?? 'fără dată';
            $out[$key] = ($out[$key] ?? 0) + 1;
        }
        ksort($out);

        return $out;
    }
}

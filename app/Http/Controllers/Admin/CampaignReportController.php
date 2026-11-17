<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\SterilizationCase;
use App\Services\Campaign\CampaignCases;
use App\Services\Campaign\CampaignConsents;
use App\Services\Campaign\CaseFields;
use App\Services\Campaign\CityChecks;
use App\Services\Campaign\CityExports;
use App\Services\Campaign\CityReports;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Raportul pentru Primarie: exporturi (Excel, PDF, ZIP, CSV) si actiunile de control al numerelor, validare si
 * reparare. Exporturile contin date personale si cer permisiunea de export; fiecare export se scrie in jurnal.
 */
class CampaignReportController extends Controller
{
    public function __construct(private readonly CityReports $reports, private readonly CityExports $exports) {}

    public static function pageUrl(array $query = []): string
    {
        $query = array_filter($query, fn ($v) => $v !== null && $v !== '');

        return url('/admin/campanie/raport-primarie').($query ? '?'.http_build_query($query) : '');
    }

    private function authorize(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can($permission), 403);
    }

    private function month(Request $request): ?string
    {
        $month = (string) $request->input('month', '');

        return CityReports::validMonth($month) ? $month : null;
    }

    private function back(Request $request, array $flash): RedirectResponse
    {
        return redirect()->to(self::pageUrl(['month' => $this->month($request)] + (array) $request->input('keep', [])))->with($flash);
    }

    /**
     * Randurile exportului: selectia fixa de 145 de dosare sau interventiile finalizate ale lunii.
     *
     * @return array{0:list<array>,1:?string,2:bool}
     */
    private function rows(Request $request): array
    {
        $selection = (string) $request->input('selection', '');
        if ($selection !== '') {
            if ($selection !== CityReports::SEPTEMBER_145) {
                throw new RuntimeException('Selecție de export invalidă.');
            }
            $result = $this->reports->september145();
            if ($result['error']) {
                throw new RuntimeException('Selecția de 145 de cazuri necesită verificare: '.$result['error']);
            }

            return [$result['rows'], '2026-09', true];
        }
        $month = $this->month($request);
        $this->reports->prepare($month, $request->user());

        return [$this->reports->finalizedRows($month), $month, false];
    }

    private function download(string $content, string $name, string $type): Response
    {
        return response($content, 200, ['Content-Type' => $type, 'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function fileName(?string $month, bool $fixed, string $prefix, string $ext): string
    {
        return $prefix.'_'.($fixed ? '2026-09-selectie-145' : ($month ?: now()->format('Y-m'))).'.'.$ext;
    }

    public function xlsx(Request $request): Response|RedirectResponse
    {
        $this->authorize($request, Permission::STERILIZATION_EXPORT);
        try {
            [$rows, $month, $fixed] = $this->rows($request);
            $mapped = $this->reports->reportMap($rows);
            $content = $this->exports->xlsx($mapped, CityReports::monthLabel($month));
        } catch (RuntimeException $e) {
            return $this->back($request, ['campaign_error' => $e->getMessage()]);
        }
        Audit::log('sterilization.exported', null, 'Desfășurător Excel '.CityReports::monthLabel($month).($fixed ? ' (selecția de 145)' : '').': '.count($mapped).' intervenții');

        return $this->download($content, $this->fileName($month, $fixed, 'Desfasurator_HiKeVet', 'xlsx'), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function pdf(Request $request): Response|RedirectResponse
    {
        $this->authorize($request, Permission::STERILIZATION_EXPORT);
        try {
            [$rows, $month, $fixed] = $this->rows($request);
            $mapped = $this->reports->reportMap($rows);
            $content = $this->exports->desfasuratorPdf($mapped, CityReports::monthLabel($month));
        } catch (RuntimeException $e) {
            return $this->back($request, ['campaign_error' => $e->getMessage()]);
        }
        Audit::log('sterilization.exported', null, 'Desfășurător PDF '.CityReports::monthLabel($month).($fixed ? ' (selecția de 145)' : '').': '.count($mapped).' intervenții');

        return $this->download($content, $this->fileName($month, $fixed, 'Desfasurator_HiKeVet', 'pdf'), 'application/pdf');
    }

    public function centralizator(Request $request): Response|RedirectResponse
    {
        $this->authorize($request, Permission::STERILIZATION_EXPORT);
        try {
            [$rows, $month, $fixed] = $this->rows($request);
            if (! $rows) {
                throw new RuntimeException('Nu există intervenții finalizate pentru perioada selectată.');
            }
            $content = $this->exports->centralizatorPdf($rows, CityReports::monthLabel($month));
        } catch (RuntimeException $e) {
            return $this->back($request, ['campaign_error' => 'Centralizatorul nu poate fi generat încă. '.$e->getMessage()]);
        }
        Audit::log('sterilization.exported', null, 'Centralizator PDF '.CityReports::monthLabel($month).($fixed ? ' (selecția de 145)' : '').': '.count($rows).' intervenții');

        return $this->download($content, $this->fileName($month, $fixed, 'Centralizator_General_HiKeVet', 'pdf'), 'application/pdf');
    }

    /** Raportul intern complet, cu statusurile de validare (handle_export_report). Nu este documentul oficial. */
    public function csv(Request $request): Response|RedirectResponse
    {
        $this->authorize($request, Permission::STERILIZATION_EXPORT);
        try {
            [$rows, $month] = $this->rows($request);
        } catch (RuntimeException $e) {
            return $this->back($request, ['campaign_error' => $e->getMessage()]);
        }
        $mapped = $this->reports->reportMap($rows);
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        if (! $mapped) {
            fputcsv($out, ['Nu exista cazuri pentru filtrul selectat'], ';');
        } else {
            fputcsv($out, array_keys($mapped[0]), ';');
            foreach ($mapped as $row) {
                fputcsv($out, array_map(fn ($v) => self::csvSafe((string) $v), $row), ';');
            }
        }
        rewind($out);
        $content = (string) stream_get_contents($out);
        fclose($out);
        Audit::log('sterilization.exported', null, 'Raport intern CSV '.CityReports::monthLabel($month).': '.count($mapped).' intervenții');

        return $this->download($content, 'Raport_Primarie_Sterilizare_'.($month ?: now()->format('Y-m')).'.csv', 'text/csv; charset=UTF-8');
    }

    public static function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    public function zip(Request $request): Response|RedirectResponse
    {
        $this->authorize($request, Permission::STERILIZATION_EXPORT);
        @set_time_limit(900);
        @ini_set('memory_limit', '512M');
        try {
            [$rows, $month, $fixed] = $this->rows($request);
            $package = $this->exports->cityZip($rows, $month, $fixed);
        } catch (Throwable $e) {
            report($e);

            return $this->back($request, ['campaign_error' => 'Exportul ZIP nu a putut fi finalizat: '.$e->getMessage()]);
        }
        Audit::log('sterilization.exported', null, 'ZIP Primărie '.CityReports::monthLabel($month).($fixed ? ' (selecția de 145)' : '').': '.$package['cases'].' dosare, '.count($package['errors']).' cu erori de document');

        return response()->download($package['path'], $package['name'], ['Content-Type' => 'application/zip', 'Cache-Control' => 'no-store, private'])->deleteFileAfterSend();
    }

    public function caseZip(Request $request, SterilizationCase $case): Response|RedirectResponse
    {
        $this->authorize($request, Permission::STERILIZATION_EXPORT);
        abort_if($case->deleted_at, 404);
        @set_time_limit(300);
        try {
            $package = $this->exports->singleCaseZip($case);
        } catch (Throwable $e) {
            report($e);

            return redirect()->back()->with('campaign_error', 'Dosarul nu a putut fi descărcat: '.$e->getMessage());
        }
        Audit::log('sterilization.exported', $case, 'ZIP dosar '.$case->cod_caz);

        return response()->download($package['path'], $package['name'], ['Content-Type' => 'application/zip', 'Cache-Control' => 'no-store, private'])->deleteFileAfterSend();
    }

    public function markPhoto(Request $request, SterilizationCase $case): RedirectResponse
    {
        $this->authorize($request, Permission::CAMPAIGN_MANAGE);
        try {
            $this->reports->markPhotoChecked($case, $request->user());
        } catch (RuntimeException $e) {
            return $this->back($request, ['campaign_error' => $e->getMessage()]);
        }

        return $this->back($request, ['campaign_message' => 'Verificare fotografie salvată pentru '.$case->cod_caz.'. Numărul oficial al pacientului a fost confirmat vizual.']);
    }

    public function bulkPhoto(Request $request): RedirectResponse
    {
        $this->authorize($request, Permission::CAMPAIGN_MANAGE);
        $filters = array_map('strval', (array) $request->input('keep', []));
        $r = $this->reports->bulkMarkPhotoChecked($this->month($request), [
            'date' => $filters['verify_date'] ?? '', 'number' => $filters['verify_number'] ?? '', 'from' => $filters['verify_from'] ?? '',
            'to' => $filters['verify_to'] ?? '', 'status' => $filters['verify_status'] ?? 'all'], $request->user());

        return $this->back($request, ['campaign_message' => 'Confirmare în masă finalizată. Confirmate acum: '.$r['updated'].' · Erau deja confirmate: '.$r['already_ok']
            .' · Fără fotografie: '.$r['skipped_no_photo'].' · Fără număr oficial: '.$r['skipped_no_number'].'.']);
    }

    public function align(Request $request): RedirectResponse
    {
        $this->authorize($request, Permission::CAMPAIGN_VALIDATE);
        $r = $this->reports->alignFromPhoto($request->user());

        return $this->back($request, ['campaign_message' => 'Aliniere după fotografiile verificate finalizată. Actualizate: '.$r['updated'].' · Erau deja aliniate: '.$r['already_aligned']
            .' · Sărite din cauza duplicatelor: '.$r['skipped_duplicate'].' · Sărite fiind scoase din uz: '.$r['skipped_void'].' · Erori: '.$r['errors'].'.']);
    }

    public function registryCsv(Request $request): Response
    {
        $this->authorize($request, Permission::STERILIZATION_EXPORT);
        $registry = $this->reports->registryMatrix();
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Nr. ordine', 'Nr. confirmat din poză', 'Nr. intern', 'Nr. raport', 'Data', 'Luna', 'Sursa datei', 'Pacient', 'Proprietar', 'Cod dosar', 'Status intervenție', 'În raport', 'Situație'], ';');
        foreach ($registry['matrix'] as $entry) {
            $n = $entry['number'];
            if ($entry['void'] && ! $entry['rows']) {
                fputcsv($out, [$n, '', '', '', '', '', '', '', '', '', '', 'NU', 'SCOS DIN UZ / NEUTILIZAT'], ';');

                continue;
            }
            if ($entry['missing']) {
                fputcsv($out, [$n, '', '', '', '', '', '', '', '', '', '', 'NU', 'NUMĂR LIPSĂ'], ';');

                continue;
            }
            foreach ($entry['rows'] as $r) {
                fputcsv($out, array_map(fn ($v) => self::csvSafe((string) $v), [$n, $r['_registry_photo_number'], $r['_registry_internal_number'], $r['_registry_report_number'], $r['_registry_date'],
                    $r['_registry_date'] ? substr($r['_registry_date'], 0, 7) : '', $r['_registry_date_source'], $r['nume_pacient'], $r['nume_proprietar'], $r['cod_caz'],
                    $r['status_interventie'], CityReports::isFinal($r) ? 'DA' : 'NU',
                    $entry['void'] ? 'CONFLICT - NUMĂR MARCAT SCOS DIN UZ' : ($entry['duplicate'] ? 'DUPLICAT' : ($r['_registry_aligned'] ? 'ALINIAT' : 'DE ALINIAT'))]), ';');
            }
        }
        rewind($out);
        $content = (string) stream_get_contents($out);
        fclose($out);
        Audit::log('sterilization.exported', null, 'Registru numere pacienți CSV');

        return $this->download($content, 'Registru_numere_pacienti_HiKeVet_'.now()->format('Y-m-d').'.csv', 'text/csv; charset=UTF-8');
    }

    public function saveReconciliation(Request $request): RedirectResponse
    {
        $this->authorize($request, Permission::CAMPAIGN_VALIDATE);
        $this->reports->saveReconciliation(array_map(fn ($v) => mb_substr((string) $v, 0, 20000), (array) $request->input('registry', [])), $request->user());

        return $this->back($request, ['campaign_message' => 'Setările registrului au fost salvate. Numerele scoase din uz nu mai sunt tratate ca lipsuri, dar istoricul lor nu este șters.']);
    }

    public function reconciliationCsv(Request $request): Response
    {
        $this->authorize($request, Permission::STERILIZATION_EXPORT);
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Luna origine', 'Data intervenției / dosarului', 'Nr. din poză', 'Nr. intern', 'Nr. raport', 'Pacient', 'Proprietar', 'Cod dosar', 'Apare în raportul deja trimis', 'Situație'], ';');
        foreach ($this->reports->reconciliation() as $data) {
            foreach ($data['rows'] as $r) {
                $state = $r['_recon_void'] ? 'CONFLICT - NUMĂR MARCAT SCOS DIN UZ'
                    : ($data['has_submitted_data'] ? ($r['_recon_submitted'] ? 'DEJA RAPORTAT' : 'DE REGULARIZAT') : 'NEVERIFICAT FAȚĂ DE RAPORTUL TRIMIS');
                fputcsv($out, array_map(fn ($v) => self::csvSafe((string) $v), [$data['label'], $r['_registry_date'], $r['_registry_photo_number'], $r['_registry_internal_number'],
                    $r['_registry_report_number'], $r['nume_pacient'], $r['nume_proprietar'], $r['cod_caz'],
                    $data['has_submitted_data'] ? ($r['_recon_submitted'] ? 'DA' : 'NU') : '', $state]), ';');
            }
        }
        rewind($out);
        $content = (string) stream_get_contents($out);
        fclose($out);
        Audit::log('sterilization.exported', null, 'Reconciliere iulie–septembrie 2026 CSV');

        return $this->download($content, 'Reconciliere_HiKeVet_Iulie-August-Septembrie_2026_'.now()->format('Y-m-d').'.csv', 'text/csv; charset=UTF-8');
    }

    public function reconciliationPdf(Request $request, string $month): Response|RedirectResponse
    {
        $this->authorize($request, Permission::STERILIZATION_EXPORT);
        abort_unless(isset(CityReports::RECONCILIATION_MONTHS[$month]), 404);
        try {
            $content = $this->exports->reconciliationPdf($month);
        } catch (RuntimeException $e) {
            return $this->back($request, ['campaign_error' => $e->getMessage()]);
        }
        Audit::log('sterilization.exported', null, 'Reconciliere PDF '.CityReports::RECONCILIATION_MONTHS[$month]);

        return $this->download($content, 'HiKeVet_Reconciliere_'.str_replace(' ', '-', CityReports::RECONCILIATION_MONTHS[$month]).'.pdf', 'application/pdf');
    }

    public function savePriorReports(Request $request): RedirectResponse
    {
        $this->authorize($request, Permission::CAMPAIGN_VALIDATE);
        try {
            $count = $this->reports->savePriorCodes((string) $request->input('codes', ''), (string) $request->input('registry_hash', ''), $request->user());
        } catch (RuntimeException $e) {
            return $this->back($request, ['campaign_error' => $e->getMessage()]);
        }

        return redirect()->to(self::pageUrl(['month' => CityReports::OCTOBER]))->with('campaign_message', 'Lista de excludere a fost salvată ('.$count.' coduri).');
    }

    public function bulkValidate(Request $request): RedirectResponse
    {
        $this->authorize($request, Permission::CAMPAIGN_VALIDATE);
        $validator = (string) $request->input('validator', CityReports::BULK_VALIDATOR);
        if (! in_array($validator, CaseFields::staffList('doctori'), true)) {
            $validator = CityReports::BULK_VALIDATOR;
        }
        $r = $this->reports->bulkValidate($validator, $request->user());

        return $this->back($request, ['campaign_message' => 'Validare în masă finalizată. Verificate: '.$r['scanned'].' · Validate acum de '.$r['validator'].': '.$r['validated']
            .' · Încă incomplete (neatinse): '.$r['incomplete'].'.']);
    }

    public function repair(Request $request): RedirectResponse
    {
        $this->authorize($request, Permission::CAMPAIGN_MANAGE);
        $r = $this->reports->autoRepair($this->month($request), $request->user());
        $lines = ['Reparare automată finalizată. Numere pacient reparate: '.count($r['renumbered']).' · Numere raport recuperate din istoric: '.count($r['report_numbers_restored'])
            .' · Consimțăminte pending sincronizate: '.$r['pending_synced'].' · Consimțăminte corectate create: '.count($r['corrected_consents']).'.'];
        if ($r['renumbered']) {
            $lines[] = 'Numere schimbate: '.implode('; ', array_map(fn ($f) => ($f['patient'] ?: $f['code']).': '.($f['old'] ?: 'fără număr').' → '.$f['new'], $r['renumbered']));
        }
        if ($r['corrected_consents']) {
            $lines[] = 'De semnat din nou pe tabletă: '.implode('; ', array_map(fn ($f) => ($f['patient'] ?: $f['owner']).' · cod '.($f['code'] ?: '—'), $r['corrected_consents']));
        }

        return $this->back($request, ['campaign_message' => implode("\n", $lines)]);
    }

    /** handle_reissue_corrected_consent(): consimtamantul vechi ramane pentru audit; cel corectat se semneaza din nou. */
    public function reissueConsent(Request $request, SterilizationCase $case, CampaignCases $cases, CampaignConsents $consents, CityChecks $checks): RedirectResponse
    {
        $this->authorize($request, Permission::CAMPAIGN_MANAGE);
        abort_if($case->deleted_at, 404);
        $row = $case->row();
        $consent = $checks->consentFor($row);
        if (! $consent || ! $consent->isSigned()) {
            return $this->back($request, ['campaign_error' => 'Dosarul nu are un consimțământ semnat de corectat.']);
        }
        if ($checks->identity($row)['ok']) {
            return $this->back($request, ['campaign_error' => 'Numele din dosar coincid deja cu consimțământul semnat; nu este necesară o variantă corectată.']);
        }
        $id = $consents->createCorrected($row, $cases->groupRows($row), 'Corectare manuală din auditul Primărie', $request->user());
        $cases->assignConsentToGroup($row, $id, $request->user());

        return $this->back($request, ['campaign_message' => 'Consimțământ corectat creat pentru '.$case->cod_caz.'. Cod pentru tabletă: '.($consents->lastCode ?: '—').'. Proprietarul trebuie să îl semneze din nou.']);
    }
}

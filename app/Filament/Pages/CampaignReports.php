<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\SterilizationDocument;
use App\Services\Campaign\CaseFields;
use App\Services\Campaign\CityReports;
use BackedEnum;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use RuntimeException;
use UnitEnum;

/**
 * Raport Primarie (render_reports din plugin): selectia perioadei, exporturile oficiale, ZIP-ul, auditul de
 * integritate, validarea in masa, controlul numerelor dupa fotografie, registrul si reconcilierea.
 * Sectiunile sunt pliabile, ca in plugin.
 */
class CampaignReports extends Page
{
    protected static ?string $slug = 'campanie/raport-primarie';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    protected static ?string $navigationLabel = 'Raport Primărie';

    protected static ?string $title = 'Raport Primărie - intervenții finalizate';

    protected static ?int $navigationSort = 93;

    protected static ?string $navigationParentItem = 'Campanie sterilizare (arhivă)';

    protected string $view = 'filament.campaign.reports';

    #[Url]
    public ?string $month = null;

    #[Url]
    public ?string $verify_date = null;

    #[Url]
    public ?string $verify_number = null;

    #[Url]
    public ?string $verify_from = null;

    #[Url]
    public ?string $verify_to = null;

    #[Url]
    public ?string $verify_status = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can(Permission::CAMPAIGN_MANAGE) || $user?->can(Permission::STERILIZATION_EXPORT));
    }

    public function getSubheading(): ?string
    {
        return $this->month === CityReports::OCTOBER
            ? 'Doar intervenții cu data din octombrie 2026, fără cazurile raportate anterior.'
            : 'Totalul este calculat după luna fixată la finalizarea intervenției. Sunt incluse și dosarele legacy cu status „Operat”. Corectările ulterioare nu mută pacientul în altă lună.';
    }

    protected function getViewData(): array
    {
        $reports = app(CityReports::class);
        $user = auth()->user();
        $month = CityReports::validMonth($this->month) ? $this->month : null;
        $this->month = $month;

        $backfill = $reports->prepare($month, $user);
        $rowsError = null;
        try {
            $rows = $reports->finalizedRows($month);
        } catch (RuntimeException $e) {
            $rowsError = $e->getMessage();
            $rows = $reports->finalizedRows($month, false);
        }
        $scan = $reports->scan($rows);
        $filters = ['date' => (string) $this->verify_date, 'number' => (string) $this->verify_number, 'from' => (string) $this->verify_from,
            'to' => (string) $this->verify_to, 'status' => $this->verify_status ?: 'all'];
        $verificationAll = $reports->verificationRows($month);
        $verification = CityReports::filterVerification($verificationAll, $filters);
        foreach ($verification as &$v) {
            $v['_photo_doc'] = $v['_verify_has_photo'] ? $reports->document((int) $v['id'], SterilizationDocument::PHOTO)?->id : null;
        }
        unset($v);

        $prior = null;
        if ($month === CityReports::OCTOBER) {
            $codes = $reports->priorCodes();
            $all = $reports->finalizedRows($month, false);
            $prior = ['codes' => $codes, 'hash' => CityReports::listHash($codes), 'all' => count($all),
                'excluded' => array_values(array_filter($all, fn ($r) => isset($codes[strtoupper(trim((string) $r['cod_caz']))])))];
        }

        $months = [];
        foreach ($reports->finalizedRows(null, false) as $r) {
            $key = substr($r['data_raportare_effective'], 0, 7) ?: 'fără dată';
            $months[$key] = ($months[$key] ?? 0) + 1;
        }
        ksort($months);

        $keep = array_filter(['verify_date' => $this->verify_date, 'verify_number' => $this->verify_number, 'verify_from' => $this->verify_from,
            'verify_to' => $this->verify_to, 'verify_status' => $this->verify_status], fn ($v) => $v !== null && $v !== '');

        return [
            'reports' => $reports, 'month' => $month, 'monthLabel' => CityReports::monthLabel($month), 'months' => $months,
            'rowsError' => $rowsError, 'backfill' => $backfill, 'scan' => $scan, 'summary' => CityReports::scanSummary($scan),
            'audit' => $reports->integrityAudit($rows), 'numberAudit' => CityReports::numberSequenceAudit($rows),
            'animals' => CityReports::animalSummary($rows), 'filters' => $filters, 'hasFilters' => CityReports::hasFilters($filters),
            'verification' => $verification, 'verificationSummary' => CityReports::verificationSummary($verificationAll),
            'registry' => $reports->registryMatrix(), 'reconciliationSettings' => $reports->reconciliationSettings(), 'reconciliation' => $reports->reconciliation(),
            'september' => $month !== CityReports::OCTOBER ? $reports->september145() : null, 'prior' => $prior, 'keep' => $keep,
            'doctors' => CaseFields::staffList('doctori'),
            'canExport' => (bool) $user->can(Permission::STERILIZATION_EXPORT), 'canManage' => (bool) $user->can(Permission::CAMPAIGN_MANAGE),
            'canValidate' => (bool) $user->can(Permission::CAMPAIGN_VALIDATE),
        ];
    }
}

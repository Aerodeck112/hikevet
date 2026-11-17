<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Consents\ConsentResource;
use App\Models\SterilizationAppointment;
use App\Models\SterilizationCase;
use App\Models\SterilizationDocument;
use App\Models\SterilizationEmailLog;
use App\Services\Campaign\CampaignCases;
use App\Services\Campaign\CampaignMailer;
use App\Services\Campaign\CaseFields;
use App\Services\Campaign\CaseForm;
use App\Services\Campaign\CityChecks;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

/** Dosarul unui pacient din campanie, pe file (render_case_editor din plugin). */
class CampaignCase extends Page
{
    protected static ?string $slug = 'campanie/dosar';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.campaign.case';

    #[Url]
    public ?int $case = null;

    #[Url]
    public string $tab = 'receptie';

    #[Url]
    public ?string $focus = null;

    private ?SterilizationCase $model = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(Permission::STERILIZATION_VIEW) || (bool) auth()->user()?->can(Permission::CAMPAIGN_MANAGE);
    }

    private function model(): ?SterilizationCase
    {
        if ($this->case && ! $this->model) {
            $this->model = SterilizationCase::query()->active()->findOrFail($this->case);
        }

        return $this->model;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->model() ? 'Dosar '.$this->model()->cod_caz : 'Caz nou – campanie sterilizare';
    }

    public function getSubheading(): ?string
    {
        $m = $this->model();

        return $m ? 'Pacient: '.($m->nume_pacient ?: '-').' · Proprietar: '.($m->nume_proprietar ?: '-') : 'Completează programarea telefonică.';
    }

    protected function getViewData(): array
    {
        $model = $this->model();
        $isNew = ! $model;
        $tab = $isNew ? 'receptie' : (['fisa' => 'preoperator', 'documente' => 'receptie'][$this->tab] ?? $this->tab);
        if (! isset(CaseForm::TABS[$tab])) {
            $tab = 'receptie';
        }
        $checks = app(CityChecks::class);
        $cases = app(CampaignCases::class);
        $user = auth()->user();

        $row = $model ? $checks->autoValidation($model->row()) : ['id' => 0] + CaseFields::defaults();
        if ($isNew) {
            $row['cod_caz'] = $cases->nextCode();
            $row['data_programare'] = CaseFields::today();
        }
        $old = old('data');
        if (is_array($old)) {
            $row = array_merge($row, array_map(fn ($v) => is_string($v) ? $v : '', $old));
        }

        $data = ['row' => $row, 'model' => $model, 'isNew' => $isNew, 'tab' => $tab, 'tabs' => CaseForm::TABS,
            'cards' => CaseForm::cards($tab, $isNew), 'canEdit' => (bool) $user->can(Permission::CAMPAIGN_MANAGE),
            'canValidate' => (bool) $user->can(Permission::CAMPAIGN_VALIDATE), 'focusField' => $this->focus];
        if ($isNew) {
            return $data;
        }

        $consent = $checks->consentFor($row);
        $photo = $checks->currentDocument($model->id, SterilizationDocument::PHOTO);
        $idCard = $checks->currentDocument($model->id, SterilizationDocument::ID_CARD);
        $phoneUrl = $row['photo_upload_token'] ? route('campaign.phone', ['token' => $row['photo_upload_token']]) : null;

        return $data + [
            'group' => $cases->groupRows($row),
            'consent' => $consent, 'consentUrl' => $consent ? ConsentResource::getUrl('view', ['record' => $consent]) : null,
            'consentPrintUrl' => $consent ? route('admin.consents.print', $consent) : null,
            'gdprState' => $consent ? $checks->gdprState($consent) : null, 'identity' => $checks->identity($row),
            'checklist' => $checks->checklist($row), 'ready' => CityChecks::isReady($row), 'reportNumber' => CityChecks::reportNumber($row),
            'photo' => $photo, 'idCard' => $idCard, 'phoneUrl' => $phoneUrl,
            'qr' => $phoneUrl ? (new QRCode(new QROptions(['outputBase64' => true, 'scale' => 6])))->render($phoneUrl) : null,
            'postopDue' => CampaignMailer::postopDueAt(['data_externare' => $row['data_externare'] ?: CaseFields::today(), 'ora_externare' => $row['ora_externare'] ?: '10:00']),
            'recommendations' => CaseFields::postopRecommendations($row),
            'reportingDate' => CaseFields::sanitizeDate($row['data_raportare_primarie']) ?: (CaseFields::sanitizeDate(substr((string) $row['timestamp_interventie'], 0, 10)) ?: CaseFields::sanitizeDate($row['data_interventie'])),
            'appointments' => SterilizationAppointment::query()->where('sterilization_case_id', $model->id)->orderByDesc('appointment_date')->get(),
            'emails' => SterilizationEmailLog::query()->where('sterilization_case_id', $model->id)->latest('id')->limit(50)->get(),
            'emailsTotal' => SterilizationEmailLog::query()->where('sterilization_case_id', $model->id)->count(),
            'changes' => $tab === 'istoric' ? $model->changes()->limit(200)->get() : collect(),
            'documents' => $tab === 'istoric' ? SterilizationDocument::query()->where('sterilization_case_id', $model->id)->latest('id')->get() : collect(),
            'consultation' => CampaignMailer::consultationDetails($row), 'intervention' => CampaignMailer::interventionDetails($row),
        ];
    }
}

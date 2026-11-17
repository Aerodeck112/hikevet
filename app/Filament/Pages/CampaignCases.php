<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\SterilizationCase;
use App\Services\Campaign\CaseFields;
use App\Services\Campaign\CityChecks;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use UnitEnum;

/** Lista dosarelor campaniei, cu filtrele si coloana de completitudine din plugin (render_cases). */
class CampaignCases extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';

    protected static string|UnitEnum|null $navigationGroup = 'Campanie sterilizare';

    protected static ?string $navigationLabel = 'Dosare';

    protected static ?string $title = 'Dosare campanie sterilizare';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'campanie/dosare';

    protected string $view = 'filament.campaign.cases';

    /** Filtrele din plugin; toate sunt in adresa paginii, ca o cautare sa poata fi trimisa altcuiva. */
    #[Url]
    public string $q = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $date_field = 'data_programare';

    #[Url]
    public string $date_from = '';

    #[Url]
    public string $date_to = '';

    #[Url]
    public string $species = '';

    #[Url]
    public string $sex = '';

    #[Url]
    public string $procedure = '';

    #[Url]
    public string $doctor = '';

    #[Url]
    public string $intervention = '';

    #[Url]
    public string $photo = '';

    #[Url]
    public string $ci = '';

    #[Url]
    public string $postop_email = '';

    #[Url]
    public string $consent = '';

    #[Url]
    public string $completeness = '';

    #[Url]
    public string $multi_visit = '';

    #[Url]
    public string $sort = 'updated_desc';

    #[Url]
    public int $p = 1;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(Permission::STERILIZATION_VIEW) || (bool) auth()->user()?->can(Permission::CAMPAIGN_MANAGE);
    }

    private function query(): Builder
    {
        $q = SterilizationCase::query()->active();
        if (($term = trim($this->q)) !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $q->where(fn ($w) => $w->where('cod_caz', 'like', $like)->orWhere('numar_pacient', 'like', $like)->orWhere('nume_proprietar', 'like', $like)
                ->orWhere('telefon', 'like', $like)->orWhere('email', 'like', $like)->orWhere('nume_pacient', 'like', $like)
                ->orWhere('microcip', 'like', $like)->orWhere('rasa', 'like', $like));
        }
        match ($this->status) {
            '' => null,
            '__primarie_validat__' => $q->where(fn ($w) => $w->where('validat_pentru_primarie', 'Da')->orWhere('status_caz', 'Validat primarie')),
            '__primarie_nevalidat__' => $q->where(fn ($w) => $w->whereNull('validat_pentru_primarie')->orWhere('validat_pentru_primarie', '!=', 'Da')),
            default => $q->where('status_caz', $this->status),
        };
        $field = in_array($this->date_field, ['data_programare', 'data_prezentare', 'data_interventie', 'data_externare', 'live_created_at', 'live_updated_at'], true) ? $this->date_field : 'data_programare';
        if ($from = CaseFields::sanitizeDate($this->date_from)) {
            $q->whereDate($field, '>=', $from);
        }
        if ($to = CaseFields::sanitizeDate($this->date_to)) {
            $q->whereDate($field, '<=', $to);
        }
        foreach (['species' => 'specie', 'sex' => 'sex'] as $prop => $column) {
            if ($this->{$prop} !== '') {
                $q->where($column, $this->{$prop});
            }
        }
        if ($this->procedure !== '') {
            $q->where(fn ($w) => $w->where('procedura_programata', 'like', '%'.$this->procedure.'%')->orWhere('procedura_efectuata', 'like', '%'.$this->procedure.'%'));
        }
        if ($this->doctor !== '') {
            $q->where(fn ($w) => $w->where('medic_operator', $this->doctor)->orWhere('responsabil_caz', $this->doctor));
        }
        match ($this->intervention) {
            'done' => $q->whereIn('status_interventie', ['Finalizat', 'Operat']),
            'pending' => $q->where(fn ($w) => $w->whereNull('status_interventie')->orWhereNotIn('status_interventie', ['Finalizat', 'Operat'])),
            default => null,
        };
        foreach (['photo' => 'fotografie_pacient', 'ci' => 'act_identitate'] as $prop => $kind) {
            if (in_array($this->{$prop}, ['yes', 'no'], true)) {
                $has = fn ($w) => $w->select('sterilization_case_id')->from('sterilization_documents')->where('kind', $kind)->whereNull('superseded_at');
                $this->{$prop} === 'yes' ? $q->whereIn('id', $has) : $q->whereNotIn('id', $has);
            }
        }
        if (in_array($this->postop_email, ['sent', 'not_sent'], true)) {
            $sent = fn ($w) => $w->select('sterilization_case_id')->from('sterilization_email_logs')->where('email_type', 'like', 'postop_instant%')->where('status', 'sent');
            $this->postop_email === 'sent' ? $q->whereIn('id', $sent) : $q->whereNotIn('id', $sent);
        }
        if ($this->consent !== '') {
            $signed = fn ($w) => $w->select('id')->from('consents')->where('status', 'signed');
            $this->consent === 'signed' ? $q->whereIn('consent_id', $signed) : $q->where(fn ($w) => $w->whereNull('consent_id')->orWhereNotIn('consent_id', $signed));
        }
        $order = [
            'updated_desc' => ['live_updated_at', 'desc'], 'appointment_desc' => ['data_programare', 'desc'], 'appointment_asc' => ['data_programare', 'asc'],
            'intervention_desc' => ['data_interventie', 'desc'], 'owner_asc' => ['nume_proprietar', 'asc'], 'patient_asc' => ['nume_pacient', 'asc'],
            'code_desc' => ['id', 'desc'], 'code_asc' => ['id', 'asc'],
        ][$this->sort] ?? ['live_updated_at', 'desc'];
        $q->orderBy($order[0], $order[1])->orderBy('id', 'desc');

        return $q;
    }

    protected function getViewData(): array
    {
        $checks = app(CityChecks::class);
        $sizes = CityChecks::visitSizes();
        $perPage = 50;
        $postFilter = $this->completeness !== '' || $this->multi_visit !== '';

        if ($postFilter) {
            // Filtre care depind de toate verificarile dosarului sau de marimea vizitei: evaluate dupa interogare.
            $all = $this->query()->limit(2000)->get()->filter(function (SterilizationCase $c) use ($checks, $sizes) {
                if ($this->multi_visit !== '') {
                    $multi = ($sizes[(int) ($c->visit_group_id ?: $c->id)] ?? 1) > 1;
                    if (($this->multi_visit === 'yes') !== $multi) {
                        return false;
                    }
                }
                if ($this->completeness !== '') {
                    $ready = CityChecks::isReady($checks->autoValidation($c->row()));
                    if (($this->completeness === 'complete') !== $ready) {
                        return false;
                    }
                }

                return true;
            })->values();
            $page = new LengthAwarePaginator($all->forPage($this->p, $perPage)->values(), $all->count(), $perPage, $this->p);
        } else {
            $page = $this->query()->paginate($perPage, ['*'], 'p', $this->p);
        }

        $rows = collect($page->items())->map(function (SterilizationCase $c) use ($checks, $sizes) {
            $row = $c->row();

            return ['model' => $c, 'row' => $row, 'checklist' => $checks->checklist($row), 'documents' => $checks->documentsStatus($row),
                'consent_signed' => $checks->consentIsSigned($row), 'visit_size' => $sizes[(int) ($c->visit_group_id ?: $c->id)] ?? 1];
        });

        return ['rows' => $rows, 'page' => $page, 'choices' => CaseFields::choices(), 'doctors' => CaseFields::staffList('doctori'),
            'total' => SterilizationCase::query()->active()->count(), 'canEdit' => (bool) auth()->user()->can(Permission::CAMPAIGN_MANAGE),
            'filters' => array_filter(get_object_vars($this), fn ($v, $k) => in_array($k, ['q', 'status', 'date_field', 'date_from', 'date_to', 'species', 'sex', 'procedure', 'doctor',
                'intervention', 'photo', 'ci', 'postop_email', 'consent', 'completeness', 'multi_visit', 'sort'], true), ARRAY_FILTER_USE_BOTH)];
    }
}

<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\SterilizationAppointment;
use App\Models\SterilizationCase;
use App\Models\User;
use App\Services\Campaign\CampaignAppointments;
use App\Services\Campaign\CaseFields;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use UnitEnum;

/** Calendarul campaniei: saptamana pe intervale de 30 de minute, programare noua cu mai multi pacienti, editare. */
class CampaignCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    protected static ?string $navigationLabel = 'Programări campanie';

    protected static ?string $title = 'Programări campanie sterilizare';

    protected static ?int $navigationSort = 91;

    protected static ?string $navigationParentItem = 'Campanie sterilizare (arhivă)';

    protected static ?string $slug = 'campanie/programari';

    protected string $view = 'filament.campaign.calendar';

    #[Url]
    public ?string $week = null;

    #[Url]
    public ?int $appointment = null;

    #[Url]
    public bool $new = false;

    #[Url]
    public ?string $date = null;

    #[Url]
    public ?string $time = null;

    #[Url]
    public ?int $owner_case = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(Permission::CAMPAIGN_MANAGE) || (bool) auth()->user()?->can(Permission::STERILIZATION_VIEW);
    }

    /** Proprietarii cunoscuti, pentru cautarea la programare: din dosarele campaniei si din conturile de client. */
    private function owners(): array
    {
        $owners = [];
        foreach (SterilizationCase::query()->active()->whereNotNull('nume_proprietar')->orderByDesc('id')->get(['id', 'cod_caz', 'nume_proprietar', 'telefon', 'email']) as $c) {
            $key = mb_strtolower(preg_replace('/\D+/', '', (string) $c->telefon) ?: (string) $c->email ?: (string) $c->nume_proprietar);
            $owners[$key] ??= ['name' => $c->nume_proprietar, 'phone' => (string) $c->telefon, 'email' => (string) $c->email, 'case' => $c->id, 'codes' => [], 'source' => 'Dosare campanie'];
            $owners[$key]['codes'][] = $c->cod_caz;
        }
        foreach (User::role('client')->where('is_active', true)->get(['id', 'name', 'phone', 'email']) as $u) {
            $key = mb_strtolower(preg_replace('/\D+/', '', (string) $u->phone) ?: (string) $u->email);
            $owners[$key] ??= ['name' => $u->name, 'phone' => (string) $u->phone, 'email' => (string) $u->email, 'case' => null, 'codes' => [], 'source' => 'Clienți HiKeVet'];
        }

        return array_values(array_map(fn ($o) => $o + ['codes_text' => implode(' ', array_slice($o['codes'], 0, 5))], $owners));
    }

    protected function getViewData(): array
    {
        $start = Carbon::parse(CaseFields::sanitizeDate((string) $this->week) ?: now()->toDateString())->startOfWeek();
        $days = collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i));
        $items = SterilizationAppointment::query()->whereBetween('appointment_date', [$days->first()->toDateString(), $days->last()->toDateString()])
            ->orderBy('appointment_date')->orderBy('start_time')->get();
        $byDay = $items->groupBy(fn ($a) => $a->appointment_date->format('Y-m-d'));
        $service = app(CampaignAppointments::class);
        $times = [];
        for ($m = CampaignAppointments::DAY_START; $m < CampaignAppointments::DAY_END; $m += 30) {
            $times[] = CaseFields::timeFromMinutes($m);
        }

        $editing = $this->appointment ? SterilizationAppointment::query()->with('emailLogs')->find($this->appointment) : null;
        $ownerCase = $this->owner_case ? SterilizationCase::query()->active()->find($this->owner_case) : null;
        $newDate = CaseFields::sanitizeDate((string) $this->date) ?: ($this->new ? max(now()->toDateString(), $start->toDateString()) : null);

        return [
            'days' => $days, 'byDay' => $byDay, 'times' => $times, 'start' => $start,
            'dayInfo' => $days->mapWithKeys(fn ($d) => [$d->format('Y-m-d') => $service->dayAvailability($d->format('Y-m-d'))])->all(),
            'editing' => $editing, 'showNew' => $this->new || $this->date !== null,
            'newDate' => $newDate, 'newTime' => CaseFields::sanitizeTime((string) $this->time) ?: '09:00', 'ownerCase' => $ownerCase,
            'newAvailability' => $newDate ? $service->dayAvailability($newDate) : null,
            'owners' => ($this->new || $this->date !== null) ? $this->owners() : [],
            'types' => CaseFields::choices()['appointment_type'], 'statuses' => CaseFields::choices()['appointment_status'],
            'canEdit' => (bool) auth()->user()->can(Permission::CAMPAIGN_MANAGE),
        ];
    }
}

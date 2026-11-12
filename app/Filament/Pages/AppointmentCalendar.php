<?php

namespace App\Filament\Pages;

use App\Enums\AppointmentStatus;
use App\Enums\Permission;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use App\Models\ScheduleBlock;
use App\Models\TeamMember;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use UnitEnum;

/** Calendar saptamanal, intervale de 30 de minute intre 08:00 si 20:00, pe medic. */
class AppointmentCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static string|UnitEnum|null $navigationGroup = 'Clinică';

    protected static ?string $navigationLabel = 'Calendar';

    protected static ?string $title = 'Calendar programări';

    protected static ?int $navigationSort = 21;

    protected string $view = 'filament.pages.appointment-calendar';

    #[Url]
    public ?string $week = null;

    #[Url]
    public ?int $doctor = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(Permission::APPOINTMENTS_MANAGE);
    }

    public function shift(int $weeks): void
    {
        $this->week = $this->start()->addWeeks($weeks)->toDateString();
    }

    public function today(): void
    {
        $this->week = null;
    }

    private function start(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($this->week ?: 'now')->startOfWeek();
        } catch (\Throwable) {
            return CarbonImmutable::now()->startOfWeek();
        }
    }

    protected function getViewData(): array
    {
        $start = $this->start();
        $end = $start->addDays(7);
        $days = collect(range(0, 6))->map(fn ($i) => $start->addDays($i));

        $appointments = Appointment::query()->with(['doctor', 'service'])->whereBetween('starts_at', [$start, $end])
            ->whereIn('status', [AppointmentStatus::Requested, AppointmentStatus::Confirmed, AppointmentStatus::Completed])
            ->when($this->doctor, fn ($q) => $q->where('team_member_id', $this->doctor))->orderBy('starts_at')->get();
        $blocks = ScheduleBlock::query()->with('doctor')->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->when($this->doctor, fn ($q) => $q->where(fn ($w) => $w->whereNull('team_member_id')->orWhere('team_member_id', $this->doctor)))->get();

        $grid = [];
        foreach ($appointments as $a) {
            $grid[$a->starts_at->toDateString()][sprintf('%02d:%02d', $a->starts_at->hour, $a->starts_at->minute < 30 ? 0 : 30)][] = $a;
        }

        $slots = [];
        for ($m = 8 * 60; $m < 20 * 60; $m += 30) {
            $slots[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }

        return [
            'days' => $days, 'times' => $slots, 'cells' => $grid, 'blocks' => $blocks, 'start' => $start,
            'doctors' => TeamMember::query()->orderBy('sort')->pluck('name', 'id'),
            'createUrl' => AppointmentResource::getUrl('create'),
            'counts' => $appointments->groupBy(fn ($a) => $a->starts_at->toDateString())->map->count(),
        ];
    }
}

<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Enums\ContractStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\ContactMessage;
use App\Models\Contract;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Indicatorii din panoul medicilor (Wallet Core 3.0): clienti, abonamente, expirari, plus programari si mesaje. */
class ClinicStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can(Permission::CLIENTS_VIEW);
    }

    protected function getStats(): array
    {
        $active = Contract::query()->where('status', ContractStatus::Active)->where('expires_at', '>', now());

        return [
            Stat::make('Clienți', User::role(Role::Client->value)->count())->icon('heroicon-o-users'),
            Stat::make('Abonamente active', (clone $active)->count())->icon('heroicon-o-check-badge')->color('success'),
            Stat::make('Așteaptă plata la clinică', Contract::query()->where('status', ContractStatus::AwaitingClinic)->count())->icon('heroicon-o-clock')->color('warning')
                ->description('Contracte semnate online'),
            Stat::make('Expiră în 7 zile', (clone $active)->where('expires_at', '<=', now()->addDays(7))->count())->icon('heroicon-o-exclamation-triangle')->color('danger'),
            Stat::make('Programări azi', Appointment::query()->whereDate('starts_at', today())->whereIn('status', [AppointmentStatus::Requested, AppointmentStatus::Confirmed])->count())->icon('heroicon-o-calendar-days'),
            Stat::make('Mesaje noi', ContactMessage::query()->where('status', 'new')->count())->icon('heroicon-o-envelope'),
        ];
    }
}

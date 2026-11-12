<?php

namespace App\Filament\Resources\Appointments;

use App\Enums\AppointmentStatus;
use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Models\Appointment;
use App\Services\Booking\BookingService;
use BackedEnum;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Programarile generale ale clinicii (nu cele istorice din campania de sterilizare). */
class AppointmentResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = Appointment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Clinică';

    protected static ?string $navigationLabel = 'Programări';

    protected static ?string $modelLabel = 'programare';

    protected static ?string $pluralModelLabel = 'programări';

    protected static ?int $navigationSort = 20;

    public static function permission(): string
    {
        return Permission::APPOINTMENTS_MANAGE;
    }

    /** Programarile se creeaza si isi schimba starea prin BookingService; nu se editeaza si nu se sterg direct. */
    protected static function allows(string $action, ?Model $record = null): bool
    {
        return in_array($action, ['viewAny', 'view', 'create'], true) && (bool) auth()->user()?->can(static::permission());
    }

    public static function getNavigationBadge(): ?string
    {
        $requested = Appointment::query()->where('status', AppointmentStatus::Requested)->where('starts_at', '>=', now())->count();

        return $requested ? (string) $requested : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('team_member_id')->label('Medic')->relationship('doctor', 'name')->required()->preload(),
            Select::make('service_id')->label('Serviciu')->relationship('service', 'name')->searchable()->preload(),
            DateTimePicker::make('starts_at')->label('Data și ora')->seconds(false)->minutesStep(15)->required()->minDate(now()->startOfDay()),
            TextInput::make('duration_minutes')->label('Durată (minute)')->numeric()->minValue(15)->maxValue(240)->step(15)->default(30)->required(),
            TextInput::make('client_name')->label('Client')->required()->maxLength(120),
            TextInput::make('client_phone')->label('Telefon')->required()->maxLength(30),
            TextInput::make('client_email')->label('Email')->email()->maxLength(190)->helperText('Dacă este completat, clientul primește confirmarea pe email.'),
            TextInput::make('pet_name')->label('Animal')->maxLength(80),
            Select::make('pet_species')->label('Specie')->options(['Câine' => 'Câine', 'Pisică' => 'Pisică', 'Altă specie' => 'Altă specie']),
            Textarea::make('notes')->label('Observații')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('starts_at')->modifyQueryUsing(fn (Builder $query) => $query->with(['doctor', 'service']))->columns([
            TextColumn::make('starts_at')->label('Când')->dateTime('D, d.m.Y H:i')->sortable()->description(fn (Appointment $r) => 'până la '.$r->ends_at->format('H:i')),
            TextColumn::make('doctor.name')->label('Medic'),
            TextColumn::make('client_name')->label('Client')->searchable()->description(fn (Appointment $r) => $r->client_phone),
            TextColumn::make('pet_name')->label('Animal')->description(fn (Appointment $r) => $r->service?->name),
            TextColumn::make('status')->label('Stare')->badge()->formatStateUsing(fn (AppointmentStatus $state) => $state->label())->color(fn (AppointmentStatus $state) => $state->color()),
            TextColumn::make('code')->label('Cod')->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            Filter::make('upcoming')->label('De azi înainte')->default()->query(fn (Builder $query) => $query->where('starts_at', '>=', now()->startOfDay())),
            Filter::make('today')->label('Astăzi')->query(fn (Builder $query) => $query->whereDate('starts_at', today())),
            SelectFilter::make('status')->label('Stare')->options(collect(AppointmentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            SelectFilter::make('team_member_id')->label('Medic')->relationship('doctor', 'name'),
        ])->recordActions([
            Action::make('confirm')->label('Confirmă')->icon('heroicon-o-check')->color('success')->visible(fn (Appointment $r) => $r->status === AppointmentStatus::Requested)
                ->action(fn (Appointment $record) => static::setStatus($record, AppointmentStatus::Confirmed)),
            Action::make('complete')->label('Finalizată')->icon('heroicon-o-flag')->color('gray')->visible(fn (Appointment $r) => $r->status === AppointmentStatus::Confirmed && $r->starts_at->isPast())
                ->action(fn (Appointment $record) => static::setStatus($record, AppointmentStatus::Completed)),
            Action::make('noShow')->label('Neprezentat')->color('gray')->visible(fn (Appointment $r) => $r->status === AppointmentStatus::Confirmed && $r->starts_at->isPast())
                ->requiresConfirmation()->action(fn (Appointment $record) => static::setStatus($record, AppointmentStatus::NoShow)),
            Action::make('cancel')->label('Anulează')->icon('heroicon-o-x-mark')->color('danger')
                ->visible(fn (Appointment $r) => in_array($r->status, [AppointmentStatus::Requested, AppointmentStatus::Confirmed], true))
                ->schema([Textarea::make('reason')->label('Motiv')->rows(2)])
                ->action(fn (Appointment $record, array $data) => static::setStatus($record, AppointmentStatus::Cancelled, (string) ($data['reason'] ?? ''))),
        ]);
    }

    public static function setStatus(Appointment $appointment, AppointmentStatus $status, string $reason = ''): void
    {
        try {
            app(BookingService::class)->setStatus($appointment, $status, $reason);
            Notification::make()->title('Programarea este acum: '.$status->label())->success()->send();
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAppointments::route('/'), 'create' => Pages\CreateAppointment::route('/create')];
    }
}

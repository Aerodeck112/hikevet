<?php

namespace App\Filament\Resources\Consents;

use App\Enums\ConsentStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Concerns\GatedByPermission;
use App\Models\Consent;
use App\Models\User;
use App\Services\Consents\ConsentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ConsentResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = Consent::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static string|UnitEnum|null $navigationGroup = 'Clinică';

    protected static ?string $navigationLabel = 'Consimțăminte';

    protected static ?string $modelLabel = 'consimțământ';

    protected static ?string $pluralModelLabel = 'consimțăminte';

    protected static ?int $navigationSort = 30;

    public static function permission(): string
    {
        return Permission::CONSENTS_MANAGE;
    }

    /** Datele se pot corecta doar pana la semnarea proprietarului; consimtamintele nu se sterg niciodata. */
    protected static function allows(string $action, ?Model $record = null): bool
    {
        if (! auth()->user()?->can(Permission::CONSENTS_MANAGE)) {
            return false;
        }

        return match ($action) {
            'viewAny', 'view', 'create' => true,
            'update' => $record instanceof Consent && $record->status === ConsentStatus::Pending,
            default => false,
        };
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Proprietar')->columns(3)->schema([
                Select::make('client_user_id')->label('Client din sistem (opțional)')->searchable()->columnSpanFull()
                    ->getSearchResultsUsing(fn (string $search) => User::role(Role::Client->value)->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))->limit(20)->pluck('name', 'id')->all())
                    ->getOptionLabelUsing(fn ($value) => User::find($value)?->name)->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $user = $state ? User::with(['pets' => fn ($q) => $q->whereNull('archived_at')])->find($state) : null;
                        if (! $user) {
                            return;
                        }
                        $set('owner_name', $user->name);
                        $set('owner_phone', $user->phone);
                        $set('owner_email', $user->email);
                        $set('owner_address', trim(collect([$user->address, $user->city])->filter()->implode(', ')));
                        if ($pet = $user->pets->first()) {
                            $set('pet_name', $pet->name);
                            $set('pet_species_breed', trim($pet->speciesLabel().' / '.$pet->breed, ' /'));
                            $set('pet_age_sex', trim(($pet->date_of_birth ? $pet->date_of_birth->age.' ani' : '').' / '.['male' => 'mascul', 'female' => 'femela'][$pet->sex] ?? '', ' /'));
                            $set('pet_microchip', $pet->microchip);
                            $set('pet_weight', $pet->weight_kg ? rtrim(rtrim((string) $pet->weight_kg, '0'), '.').' kg' : '');
                        }
                    })
                    ->helperText('Precompletează datele; toate câmpurile rămân editabile.'),
                TextInput::make('owner_name')->label('Proprietar / reprezentant legal')->required()->maxLength(120),
                TextInput::make('owner_phone')->label('Telefon')->maxLength(30),
                TextInput::make('owner_email')->label('Email')->email()->maxLength(190),
                TextInput::make('owner_address')->label('Domiciliu')->maxLength(250)->columnSpan(2),
                TextInput::make('owner_ci')->label('CI seria / nr.')->maxLength(20),
                TextInput::make('owner_cnp')->label('CNP')->maxLength(13),
            ]),
            Section::make('Pacient')->columns(3)->schema([
                TextInput::make('pet_name')->label('Nume pacient')->maxLength(80)->helperText('Pentru o vizită cu mai mulți pacienți, folosește lista de mai jos.'),
                TextInput::make('pet_species_breed')->label('Specie / rasă')->maxLength(120),
                TextInput::make('pet_age_sex')->label('Vârstă / sex')->maxLength(80),
                TextInput::make('pet_microchip')->label('Microcip / pașaport')->maxLength(40),
                TextInput::make('pet_weight')->label('Greutate')->maxLength(20),
                DatePicker::make('consent_date')->label('Data')->default(now())->required(),
                Repeater::make('visit_patients')->label('Pacienți ai aceleiași vizite (consimțământ comun)')->columnSpanFull()->columns(6)->defaultItems(0)->addActionLabel('Adaugă pacient')
                    ->schema([
                        TextInput::make('pet_name')->label('Nume')->required(), TextInput::make('species')->label('Specie'), TextInput::make('sex')->label('Sex'),
                        TextInput::make('breed')->label('Rasă'), TextInput::make('weight')->label('Greutate (kg)'), TextInput::make('procedure')->label('Procedură'),
                    ]),
            ]),
            Section::make('Procedură')->columns(2)->schema([
                TextInput::make('procedure')->label('Procedura / intervenția propusă')->required()->maxLength(250),
                TextInput::make('vet_name')->label('Medicul veterinar curant / chirurg')->required()->maxLength(120)->default(fn () => auth()->user()?->name),
                CheckboxList::make('anesthesia')->label('Anestezie / sedare')->options(ConsentService::ANESTHESIA)->default(['anestezie_generala_injectabila']),
                TextInput::make('anesthesia_other')->label('Alt tip de anestezie')->maxLength(120),
                CheckboxList::make('investigations')->label('Investigații recomandate și acceptate')->options(ConsentService::INVESTIGATIONS),
                CheckboxList::make('refused_services')->label('Servicii recomandate pe care proprietarul le refuză')->options(ConsentService::INVESTIGATIONS)
                    ->helperText('Dacă există refuzuri, tableta cere o semnătură separată pentru refuz informat.'),
                TextInput::make('investigations_other')->label('Alte investigații acceptate')->maxLength(250),
                TextInput::make('refused_other')->label('Alte servicii refuzate')->maxLength(250),
                TextInput::make('cost_from')->label('Preț estimativ de la (lei)')->maxLength(20),
                TextInput::make('cost_to')->label('până la (lei)')->maxLength(20),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('consent_date')->label('Data')->date('d.m.Y')->sortable(),
            TextColumn::make('owner_name')->label('Proprietar')->searchable(),
            TextColumn::make('pet_name')->label('Pacient(i)')->searchable()->wrap(),
            TextColumn::make('procedure')->label('Procedură')->searchable()->wrap(),
            TextColumn::make('vet_name')->label('Medic')->toggleable(),
            TextColumn::make('status')->label('Stare')->badge()->formatStateUsing(fn (ConsentStatus $state) => $state->label())->color(fn (ConsentStatus $state) => $state->color()),
            TextColumn::make('signed_at')->label('Semnat la')->dateTime('d.m.Y H:i')->sortable()->placeholder('–'),
            TextColumn::make('origin')->label('Origine')->badge()->color('gray')->formatStateUsing(fn ($state) => ['manual' => 'Manual', 'sterilization' => 'Campanie', 'import' => 'Import'][$state] ?? $state)->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('status')->label('Stare')->options(collect(ConsentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            SelectFilter::make('origin')->label('Origine')->options(['manual' => 'Manual', 'sterilization' => 'Campanie', 'import' => 'Import']),
            Filter::make('today')->label('Astăzi')->query(fn (Builder $query) => $query->whereDate('consent_date', today())),
            Filter::make('month')->label('Luna aceasta')->query(fn (Builder $query) => $query->whereBetween('consent_date', [now()->startOfMonth(), now()->endOfMonth()])),
        ])->recordUrl(fn (Consent $record) => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                Action::make('open')->label('Deschide')->icon('heroicon-o-folder-open')->url(fn (Consent $record) => static::getUrl('view', ['record' => $record])),
                Action::make('print')->label('Tipărește')->icon('heroicon-o-printer')->color('gray')->url(fn (Consent $record) => route('admin.consents.print', $record))->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConsents::route('/'),
            'create' => Pages\CreateConsent::route('/create'),
            'view' => Pages\ViewConsent::route('/{record}'),
            'edit' => Pages\EditConsent::route('/{record}/edit'),
        ];
    }
}

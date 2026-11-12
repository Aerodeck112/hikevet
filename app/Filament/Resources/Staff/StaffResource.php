<?php

namespace App\Filament\Resources\Staff;

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Concerns\GatedByPermission;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

/** Personalul clinicii si rolurile lui. Conturile nu se sterg: se dezactiveaza, ca jurnalul sa ramana lizibil. */
class StaffResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = User::class;

    protected static ?string $slug = 'staff';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Personal și roluri';

    protected static ?string $modelLabel = 'utilizator';

    protected static ?string $pluralModelLabel = 'personal';

    protected static ?int $navigationSort = 10;

    public static function permission(): string
    {
        return Permission::USERS_MANAGE;
    }

    protected static function allows(string $action, ?Model $record = null): bool
    {
        $user = auth()->user();
        if (! $user?->can(Permission::USERS_MANAGE) || in_array($action, ['delete', 'deleteAny', 'forceDelete'], true)) {
            return false;
        }
        // Doar un Super Admin poate modifica un alt Super Admin.
        if ($record instanceof User && $record->hasRole(Role::SuperAdmin->value) && ! $user->hasRole(Role::SuperAdmin->value)) {
            return in_array($action, ['viewAny', 'view'], true);
        }

        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role(Role::staff());
    }

    public static function roleOptions(): array
    {
        $roles = collect(Role::cases())->reject(fn (Role $r) => $r === Role::Client);
        if (! auth()->user()?->hasRole(Role::SuperAdmin->value)) {
            $roles = $roles->reject(fn (Role $r) => $r === Role::SuperAdmin);
        }

        return $roles->mapWithKeys(fn (Role $r) => [$r->value => $r->label()])->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nume și prenume')->required()->maxLength(120),
            TextInput::make('email')->label('Email')->email()->required()->maxLength(190)->unique(ignoreRecord: true),
            TextInput::make('phone')->label('Telefon')->tel()->maxLength(30),
            Select::make('role')->label('Rol')->options(fn () => self::roleOptions())->required()
                ->helperText('Rolul stabilește ce poate vedea și face utilizatorul în admin.'),
            TextInput::make('new_password')->label(fn (string $operation) => $operation === 'create' ? 'Parolă inițială' : 'Parolă nouă (opțional)')->password()->revealable()
                ->required(fn (string $operation) => $operation === 'create')->rule(Password::defaults())->dehydrated(fn ($state) => filled($state))
                ->helperText('Minimum 10 caractere, cu litere și cifre. Utilizatorul își activează apoi verificarea în doi pași din profil.'),
            Toggle::make('is_active')->label('Cont activ')->default(true)->helperText('Un cont dezactivat nu mai poate intra în admin.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('name')->columns([
            TextColumn::make('name')->label('Nume')->searchable()->description(fn (User $r) => $r->email),
            TextColumn::make('roles.name')->label('Rol')->badge()->formatStateUsing(fn ($state) => Role::tryFrom((string) $state)?->label() ?? $state),
            IconColumn::make('two_factor')->label('2FA')->boolean()->getStateUsing(fn (User $r) => filled($r->app_authentication_secret)),
            IconColumn::make('is_active')->label('Activ')->boolean(),
            TextColumn::make('last_login_at')->label('Ultima autentificare')->since()->placeholder('niciodată'),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListStaff::route('/'), 'create' => Pages\CreateStaff::route('/create'), 'edit' => Pages\EditStaff::route('/{record}/edit')];
    }
}

<?php

namespace App\Filament\Resources\Clients;

use App\Enums\Permission;
use App\Enums\Role;
use App\Filament\Concerns\GatedByPermission;
use App\Models\User;
use App\Services\Clients\ClientAccounts;
use App\Support\Audit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Clientii clinicii: echivalentul paginii "Soft clinica HikeVet" din Wallet Core. */
class ClientResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = User::class;

    protected static ?string $slug = 'clients';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Clinică';

    protected static ?string $navigationLabel = 'Clienți și pacienți';

    protected static ?string $modelLabel = 'client';

    protected static ?string $pluralModelLabel = 'clienți';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 10;

    public static function permission(): string
    {
        return Permission::CLIENTS_VIEW;
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->can(Permission::CLIENTS_MANAGE);
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->can(Permission::CLIENTS_MANAGE);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role(Role::Client->value);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone', 'pets.name', 'pets.microchip'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ['Email' => $record->email, 'Animale' => $record->pets->pluck('name')->implode(', ')];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nume și prenume')->required()->maxLength(120),
            TextInput::make('email')->label('Email')->email()->required()->maxLength(190)->unique(ignoreRecord: true),
            TextInput::make('phone')->label('Telefon')->tel()->maxLength(30),
            TextInput::make('city')->label('Localitate')->maxLength(120),
            TextInput::make('address')->label('Adresă')->maxLength(250)->columnSpanFull(),
            Textarea::make('internal_notes')->label('Observații interne')->rows(3)->columnSpanFull()->helperText('Vizibile doar personalului.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['pets' => fn ($q) => $q->whereNull('archived_at')])->withCount(['contracts as active_contracts_count' => fn ($q) => $q->where('status', 'active')->where('expires_at', '>', now())]))
            ->searchPlaceholder('Proprietar, animal, telefon, email, microcip, rasă, ID')
            ->columns([
                TextColumn::make('name')->label('Proprietar')->sortable()->description(fn (User $record) => $record->email.($record->phone ? ' · '.$record->phone : ''))
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->where(function (Builder $q) use ($search) {
                            $like = '%'.$search.'%';
                            $q->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)
                                ->orWhereHas('pets', fn ($p) => $p->where('name', 'like', $like)->orWhere('microchip', 'like', $like)->orWhere('breed', 'like', $like));
                            if (ctype_digit($search)) {
                                $q->orWhere('id', (int) $search);
                            }
                        });
                    }),
                TextColumn::make('pets.name')->label('Animale')->badge()->color('gray'),
                TextColumn::make('active_contracts_count')->label('Abonament')->badge()
                    ->formatStateUsing(fn ($state) => $state > 0 ? 'Activ' : 'Inactiv')->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                TextColumn::make('created_at')->label('Client din')->date('d.m.Y')->sortable()->toggleable(),
            ])
            ->filters([
                Filter::make('active')->label('Cu abonament activ')->query(fn (Builder $q) => $q->whereHas('contracts', fn ($c) => $c->where('status', 'active')->where('expires_at', '>', now()))),
                Filter::make('awaiting')->label('Așteaptă plata la clinică')->query(fn (Builder $q) => $q->whereHas('contracts', fn ($c) => $c->where('status', 'awaiting_clinic'))),
                Filter::make('expiring')->label('Expiră în 7 zile')->query(fn (Builder $q) => $q->whereHas('contracts', fn ($c) => $c->where('status', 'active')->whereBetween('expires_at', [now(), now()->addDays(7)]))),
            ])
            ->recordUrl(fn (User $record) => static::getUrl('view', ['record' => $record]))
            ->recordActions([Action::make('open')->label('Fișă')->icon('heroicon-o-folder-open')->url(fn (User $record) => static::getUrl('view', ['record' => $record]))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'view' => Pages\ViewClient::route('/{record}'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }

    /** Creeaza clientul si trimite linkul de setare a parolei (handle_admin_create_client). */
    public static function createClient(array $data): User
    {
        [$user] = app(ClientAccounts::class)->findOrCreate($data['name'], $data['email'], $data['phone'] ?? null, $data['address'] ?? null);
        $user->forceFill(['city' => $data['city'] ?? null, 'internal_notes' => $data['internal_notes'] ?? null])->save();
        Audit::log('client.created', $user, 'Client creat din admin', null, null, $user->id);

        return $user;
    }
}

<?php

namespace App\Filament\Resources\Redirects;

use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Models\Redirect;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class RedirectResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-right';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Redirecturi 301';

    protected static ?string $modelLabel = 'redirect';

    protected static ?string $pluralModelLabel = 'redirecturi';

    protected static ?int $navigationSort = 20;

    public static function permission(): string
    {
        return Permission::SEO_MANAGE;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('from_path')->label('De la (adresa veche)')->required()->startsWith('/')->maxLength(250)->unique(ignoreRecord: true)->placeholder('/pagina-veche/'),
            TextInput::make('to_path')->label('Către')->required()->maxLength(250)->placeholder('/pagina-noua/')->helperText('O adresă din site (începe cu /) sau un URL complet.'),
            Select::make('status_code')->label('Tip')->options([301 => '301 – permanent', 302 => '302 – temporar'])->default(301)->required(),
            TextInput::make('note')->label('Notă')->maxLength(250),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('from_path')->label('De la')->searchable()->copyable(),
            TextColumn::make('to_path')->label('Către')->searchable(),
            TextColumn::make('status_code')->label('Tip')->badge(),
            TextColumn::make('hits')->label('Accesări')->numeric()->sortable(),
            TextColumn::make('note')->label('Notă')->wrap()->toggleable(),
        ])->recordActions([EditAction::make()])->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListRedirects::route('/'), 'create' => Pages\CreateRedirect::route('/create'), 'edit' => Pages\EditRedirect::route('/{record}/edit')];
    }

    /** Harta de redirecturi este tinuta in cache; orice modificare o goleste. */
    public static function flushCache(): void
    {
        Cache::forget('redirects.map');
    }
}

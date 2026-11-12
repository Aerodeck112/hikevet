<?php

namespace App\Filament\Resources\PriceLists;

use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Models\PriceList;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Listele de preturi sunt versionate si nu se editeaza: contractele semnate trimit la lista lor.
 * O schimbare de pret inseamna o lista noua (deocamdata prin seeder, cu aprobarea administratorului).
 */
class PriceListResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = PriceList::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Abonamente';

    protected static ?string $navigationLabel = 'Liste de prețuri';

    protected static ?string $modelLabel = 'listă de prețuri';

    protected static ?string $pluralModelLabel = 'liste de prețuri';

    protected static ?int $navigationSort = 20;

    public static function permission(): string
    {
        return Permission::CONTRACTS_VIEW;
    }

    protected static function writable(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('version')->label('Versiune'),
            TextColumn::make('source')->label('Sursă')->wrap(),
            TextColumn::make('effective_from')->label('În vigoare din')->date('d.m.Y'),
            IconColumn::make('is_active')->label('Activă')->boolean(),
            TextColumn::make('tiers_count')->label('Categorii')->counts('tiers'),
        ])->recordUrl(fn (PriceList $record) => static::getUrl('view', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPriceLists::route('/'), 'view' => Pages\ViewPriceList::route('/{record}')];
    }
}

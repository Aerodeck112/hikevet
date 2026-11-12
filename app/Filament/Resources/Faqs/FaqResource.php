<?php

namespace App\Filament\Resources\Faqs;

use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Filament\Support\Fields;
use App\Models\Faq;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class FaqResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static string|UnitEnum|null $navigationGroup = 'Conținut site';

    protected static ?string $navigationLabel = 'Întrebări frecvente';

    protected static ?string $modelLabel = 'întrebare';

    protected static ?string $pluralModelLabel = 'întrebări frecvente';

    protected static ?int $navigationSort = 50;

    public static function permission(): string
    {
        return Permission::CMS_MANAGE;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('question')->label('Întrebare')->required()->maxLength(500),
            Fields::rich('answer', 'Răspuns')->required(),
            Toggle::make('is_published')->label('Afișată pe site')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->reorderable('sort')->columns([
            TextColumn::make('question')->label('Întrebare')->searchable()->wrap(),
            IconColumn::make('is_published')->label('Pe site')->boolean(),
        ])->recordActions([EditAction::make()])->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFaqs::route('/'), 'create' => Pages\CreateFaq::route('/create'), 'edit' => Pages\EditFaq::route('/{record}/edit')];
    }
}

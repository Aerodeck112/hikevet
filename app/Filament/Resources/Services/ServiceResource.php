<?php

namespace App\Filament\Resources\Services;

use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Filament\Support\Fields;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ServiceResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static string|UnitEnum|null $navigationGroup = 'Conținut site';

    protected static ?string $navigationLabel = 'Servicii';

    protected static ?string $modelLabel = 'serviciu';

    protected static ?string $pluralModelLabel = 'servicii';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 20;

    public static function permission(): string
    {
        return Permission::CMS_MANAGE;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Serviciu')->columns(2)->schema([
                Fields::title(),
                Fields::slug(),
                Select::make('service_category_id')->label('Categorie')->relationship('category', 'name')->preload(),
                Select::make('icon')->label('Pictogramă')->options(array_combine($icons = ['paw', 'flask', 'scan', 'wave', 'stethoscope', 'mask', 'pulse', 'home', 'scalpel', 'bone', 'drop', 'bowl', 'eye', 'brain', 'tooth', 'syringe', 'shield', 'chip', 'passport', 'microscope', 'heart', 'ambulance'], $icons))->default('paw'),
                Textarea::make('excerpt')->label('Rezumat')->rows(2)->maxLength(500)->columnSpanFull()->helperText('Apare în lista de servicii și în Google.'),
                Fields::rich('body', 'Descriere'),
                Fields::image('image', 'Imagine'),
                Select::make('teamMembers')->label('Medici')->relationship('teamMembers', 'name')->multiple()->preload(),
            ]),
            Section::make('Programare')->columns(3)->schema([
                TextInput::make('duration_minutes')->label('Durată (minute)')->numeric()->minValue(15)->maxValue(240)->step(15)->default(30)->required(),
                TextInput::make('price_from')->label('Preț de la (lei, opțional)')->numeric()->minValue(0),
                TextInput::make('booking_url')->label('Link Digitail')->url()->maxLength(250),
                Toggle::make('is_bookable')->label('Poate fi programat online în sistemul propriu'),
                Toggle::make('is_published')->label('Publicat')->default(true),
                TextInput::make('sort')->label('Ordine')->numeric()->default(0),
            ]),
            Fields::seo(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->reorderable('sort')->columns([
            TextColumn::make('name')->label('Serviciu')->searchable()->sortable(),
            TextColumn::make('category.name')->label('Categorie')->badge(),
            TextColumn::make('duration_minutes')->label('Durată')->suffix(' min'),
            IconColumn::make('is_bookable')->label('Programabil')->boolean(),
            IconColumn::make('is_published')->label('Publicat')->boolean(),
        ])->filters([SelectFilter::make('service_category_id')->label('Categorie')->relationship('category', 'name')])
            ->recordActions([EditAction::make()])->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListServices::route('/'), 'create' => Pages\CreateService::route('/create'), 'edit' => Pages\EditService::route('/{record}/edit')];
    }
}

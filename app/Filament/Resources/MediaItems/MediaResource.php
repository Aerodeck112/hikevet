<?php

namespace App\Filament\Resources\MediaItems;

use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Filament\Support\Fields;
use App\Models\Media;
use App\Services\Media\MediaLibrary;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class MediaResource extends Resource
{
    use GatedByPermission;

    public const CATEGORIES = ['site' => 'Site', 'echipa' => 'Echipă', 'clinica' => 'Clinică', 'pacienti' => 'Pacienți', 'blog' => 'Blog', 'parteneri' => 'Parteneri'];

    protected static ?string $model = Media::class;

    protected static ?string $slug = 'media';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|UnitEnum|null $navigationGroup = 'Conținut site';

    protected static ?string $navigationLabel = 'Bibliotecă media';

    protected static ?string $modelLabel = 'imagine';

    protected static ?string $pluralModelLabel = 'imagini';

    protected static ?int $navigationSort = 60;

    public static function permission(): string
    {
        return Permission::MEDIA_MANAGE;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            FileUpload::make('upload')->label('Imagine')->disk('public')->directory('media/'.date('Y/m'))->image()->imageEditor()
                ->acceptedFileTypes(MediaLibrary::ALLOWED_MIME)->maxSize(8192)->required()->columnSpanFull()
                ->visibleOn('create')->storeFileNamesIn('original_name')
                ->helperText('JPEG, PNG sau WebP, maximum 8 MB. Originalul se păstrează; pe site se folosesc variante WebP optimizate.'),
            TextInput::make('alt')->label('Text alternativ')->maxLength(250)->columnSpanFull()
                ->helperText('Descrie imaginea pentru cititoarele de ecran și pentru Google. Lasă gol doar pentru imagini decorative.'),
            Select::make('category')->label('Categorie')->options(self::CATEGORIES)->default('site'),
            TextInput::make('license_note')->label('Licență / acord de utilizare')->maxLength(250),
            TextInput::make('focal_x')->label('Punct focal orizontal (%)')->numeric()->minValue(0)->maxValue(100)->default(50),
            TextInput::make('focal_y')->label('Punct focal vertical (%)')->numeric()->minValue(0)->maxValue(100)->default(50),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            ImageColumn::make('preview')->label('')->getStateUsing(fn (Media $record) => asset(Fields::mediaValue($record)))->imageSize(64)->square(),
            TextColumn::make('original_name')->label('Fișier')->searchable()->wrap(),
            TextColumn::make('alt')->label('Text alternativ')->searchable()->wrap()->placeholder('lipsește')->color(fn ($state) => $state ? null : 'warning'),
            TextColumn::make('category')->label('Categorie')->badge()->formatStateUsing(fn ($state) => self::CATEGORIES[$state] ?? $state),
            TextColumn::make('width')->label('Dimensiuni')->formatStateUsing(fn (Media $record) => $record->width.'×'.$record->height),
            TextColumn::make('license_note')->label('Licență')->wrap()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([SelectFilter::make('category')->label('Categorie')->options(self::CATEGORIES)])
            ->recordActions([
                Action::make('copy')->label('Adresă')->icon('heroicon-o-clipboard')->color('gray')
                    ->action(fn () => null)->modalHeading('Adresa imaginii')->modalSubmitAction(false)->modalCancelActionLabel('Închide')
                    ->modalDescription(fn (Media $record) => asset(Fields::mediaValue($record))),
                EditAction::make(),
                Action::make('remove')->label('Șterge')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->visible(fn (Media $record) => $record->disk === 'public')
                    ->action(fn (Media $record) => app(MediaLibrary::class)->delete($record)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListMedia::route('/'), 'create' => Pages\CreateMedia::route('/create'), 'edit' => Pages\EditMedia::route('/{record}/edit')];
    }
}

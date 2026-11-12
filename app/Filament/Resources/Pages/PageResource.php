<?php

namespace App\Filament\Resources\Pages;

use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Filament\Support\Fields;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PageResource extends Resource
{
    use GatedByPermission;

    public const TEMPLATES = ['default' => 'Pagină simplă', 'home' => 'Prima pagină', 'services' => 'Lista de servicii', 'team' => 'Echipa',
        'subscriptions' => 'Abonamente', 'contact' => 'Contact', 'faq' => 'Întrebări frecvente', 'blog' => 'Blog', 'video-vet' => 'Video Vet',
        'tour' => 'Tur 3D', 'legal' => 'Document legal'];

    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Conținut site';

    protected static ?string $navigationLabel = 'Pagini';

    protected static ?string $modelLabel = 'pagină';

    protected static ?string $pluralModelLabel = 'pagini';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 10;

    public static function permission(): string
    {
        return Permission::CMS_MANAGE;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pagină')->columns(2)->schema([
                Fields::title('title', 'Titlu'),
                Fields::slug()->disabled(fn (?Page $record) => $record?->slug === 'home'),
                Select::make('template')->label('Șablon')->options(self::TEMPLATES)->required()->default('default'),
                Select::make('status')->label('Stare')->options(['draft' => 'Ciornă', 'published' => 'Publicată'])->required()->default('draft')
                    ->helperText('Ciornele se pot previzualiza de personal la adresa paginii.'),
                Textarea::make('lead')->label('Introducere')->rows(2)->maxLength(500)->columnSpanFull(),
                Fields::rich('body', 'Conținut'),
                Fields::image('hero_image', 'Imagine principală'),
                TextInput::make('document_version')->label('Versiune document')->maxLength(40)
                    ->helperText('Pentru documentele legale. Versiunea și amprenta textului se salvează în fiecare contract semnat.'),
            ]),
            Section::make('Texte și setări ale șablonului')->collapsed()
                ->description('Câmpuri specifice șablonului (de exemplu titlul din prima pagină). Valorile complexe, precum listele, se păstrează neschimbate.')
                ->schema([
                    KeyValue::make('block_texts')->label('')->keyLabel('Câmp')->valueLabel('Text')->addable(false)->deletable(false)->editableKeys(false),
                ]),
            Fields::seo(),
            Section::make('Indexare')->collapsed()->schema([Toggle::make('noindex')->label('Ascunde pagina de motoarele de căutare')]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('title')->columns([
            TextColumn::make('title')->label('Titlu')->searchable()->sortable(),
            TextColumn::make('slug')->label('Adresă')->formatStateUsing(fn (Page $record) => $record->path())->copyable(),
            TextColumn::make('template')->label('Șablon')->formatStateUsing(fn ($state) => self::TEMPLATES[$state] ?? $state)->badge()->color('gray'),
            TextColumn::make('status')->label('Stare')->badge()->formatStateUsing(fn ($state) => $state === 'published' ? 'Publicată' : 'Ciornă')
                ->color(fn ($state) => $state === 'published' ? 'success' : 'warning'),
            TextColumn::make('updated_at')->label('Modificată')->since()->sortable(),
        ])->filters([SelectFilter::make('status')->label('Stare')->options(['draft' => 'Ciornă', 'published' => 'Publicată'])])
            ->recordActions([
                Action::make('view')->label('Vezi')->icon('heroicon-o-arrow-top-right-on-square')->url(fn (Page $record) => site_url($record->path()))->openUrlInNewTab(),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPages::route('/'), 'create' => Pages\CreatePage::route('/create'), 'edit' => Pages\EditPage::route('/{record}/edit')];
    }
}

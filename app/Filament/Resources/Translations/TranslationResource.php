<?php

namespace App\Filament\Resources\Translations;

use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TeamMember;
use App\Models\Translation;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/** Traducerile continutului in engleza si maghiara (servicii, pagini, articole, intrebari, echipa). */
class TranslationResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = Translation::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-language';

    protected static string|UnitEnum|null $navigationGroup = 'Conținut site';

    protected static ?string $navigationLabel = 'Traduceri (EN, HU)';

    protected static ?string $modelLabel = 'traducere';

    protected static ?string $pluralModelLabel = 'traduceri';

    protected static ?string $slug = 'traduceri';

    protected static ?int $navigationSort = 70;

    public const TYPES = [
        Service::class => 'Serviciu', Page::class => 'Pagină', Post::class => 'Articol', Faq::class => 'Întrebare frecventă',
        TeamMember::class => 'Echipa', ServiceCategory::class => 'Categorie de servicii',
    ];

    public const LOCALES = ['en' => 'Engleză', 'hu' => 'Maghiară'];

    public static function permission(): string
    {
        return Permission::CMS_MANAGE;
    }

    private static function recordTitle(?string $type, $id): string
    {
        if (! $type || ! $id || ! class_exists($type)) {
            return '–';
        }
        $model = $type::query()->find($id);

        return (string) ($model?->getRawOriginal('name') ?? $model?->getRawOriginal('title') ?? $model?->getRawOriginal('question') ?? '#'.$id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('translatable_type')->label('Ce traduci')->options(self::TYPES)->required()->live()->disabledOn('edit'),
            Select::make('translatable_id')->label('Elementul')->required()->searchable()->disabledOn('edit')
                ->options(function (Get $get) {
                    $type = $get('translatable_type');
                    if (! $type || ! class_exists($type)) {
                        return [];
                    }

                    return $type::query()->get()->mapWithKeys(fn ($m) => [$m->getKey() => (string) ($m->getRawOriginal('name') ?? $m->getRawOriginal('title') ?? $m->getRawOriginal('question'))])->all();
                }),
            Select::make('locale')->label('Limba')->options(self::LOCALES)->required()->disabledOn('edit'),
            Select::make('field')->label('Câmpul')->required()->disabledOn('edit')
                ->options(fn (Get $get) => collect(class_exists((string) $get('translatable_type')) ? (new ($get('translatable_type')))->translatableFields() : [])->mapWithKeys(fn ($f) => [$f => $f])->all()),
            Textarea::make('value')->label('Traducerea')->rows(14)->columnSpanFull()
                ->helperText('Textul poate conține HTML (paragrafe, liste, linkuri), la fel ca originalul în română. Câmpul „blocks” este în format JSON.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('updated_at', 'desc')->columns([
            TextColumn::make('translatable_type')->label('Tip')->formatStateUsing(fn ($state) => self::TYPES[$state] ?? class_basename($state))->sortable(),
            TextColumn::make('translatable_id')->label('Element')->formatStateUsing(fn ($state, Translation $r) => Str::limit(self::recordTitle($r->translatable_type, $state), 50)),
            TextColumn::make('field')->label('Câmp'),
            TextColumn::make('locale')->label('Limba')->badge()->formatStateUsing(fn ($state) => strtoupper($state)),
            TextColumn::make('value')->label('Text')->formatStateUsing(fn ($state) => Str::limit(strip_tags((string) $state), 90))->wrap()->searchable(),
            TextColumn::make('updated_at')->label('Modificată')->since(),
        ])->filters([
            SelectFilter::make('locale')->label('Limba')->options(self::LOCALES),
            SelectFilter::make('translatable_type')->label('Tip')->options(self::TYPES),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListTranslations::route('/'), 'create' => Pages\CreateTranslation::route('/create'), 'edit' => Pages\EditTranslation::route('/{record}/edit')];
    }
}

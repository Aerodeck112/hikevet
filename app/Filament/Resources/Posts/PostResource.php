<?php

namespace App\Filament\Resources\Posts;

use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Filament\Support\Fields;
use App\Models\Post;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PostResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static string|UnitEnum|null $navigationGroup = 'Conținut site';

    protected static ?string $navigationLabel = 'Blog';

    protected static ?string $modelLabel = 'articol';

    protected static ?string $pluralModelLabel = 'articole';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 40;

    public static function permission(): string
    {
        return Permission::CMS_MANAGE;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Articol')->columns(2)->schema([
                Fields::title('title', 'Titlu'),
                Fields::slug(),
                Textarea::make('excerpt')->label('Rezumat')->rows(2)->maxLength(500)->columnSpanFull(),
                Fields::rich('body', 'Conținut'),
                Fields::image('image', 'Imagine'),
                TextInput::make('author_name')->label('Autor afișat')->maxLength(120)->default('HiKeVet'),
                TextInput::make('category')->label('Categorie')->maxLength(80),
                TagsInput::make('tags')->label('Etichete'),
                Select::make('status')->label('Stare')->options(['draft' => 'Ciornă', 'published' => 'Publicat'])->required()->default('draft'),
                DateTimePicker::make('published_at')->label('Data publicării')->seconds(false)->default(now())
                    ->helperText('O dată din viitor programează publicarea.'),
            ]),
            Fields::seo(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('published_at', 'desc')->columns([
            TextColumn::make('title')->label('Titlu')->searchable()->wrap(),
            TextColumn::make('status')->label('Stare')->badge()->formatStateUsing(fn ($state) => $state === 'published' ? 'Publicat' : 'Ciornă')
                ->color(fn ($state) => $state === 'published' ? 'success' : 'warning'),
            TextColumn::make('published_at')->label('Publicat la')->dateTime('d.m.Y H:i')->sortable(),
        ])->recordActions([EditAction::make()])->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPosts::route('/'), 'create' => Pages\CreatePost::route('/create'), 'edit' => Pages\EditPost::route('/{record}/edit')];
    }
}

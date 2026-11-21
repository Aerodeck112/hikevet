<?php

namespace App\Filament\Resources\AssistantFacts;

use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Models\AssistantFact;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** „Antrenarea” asistentului: informatii scrise de clinica, folosite cu prioritate fata de restul site-ului. */
class AssistantFactResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = AssistantFact::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|UnitEnum|null $navigationGroup = 'Conținut site';

    protected static ?string $navigationLabel = 'Asistent AI: ce știe';

    protected static ?string $modelLabel = 'informație pentru asistent';

    protected static ?string $pluralModelLabel = 'informații pentru asistent';

    protected static ?string $slug = 'asistent-ai-cunostinte';

    protected static ?int $navigationSort = 61;

    public static function permission(): string
    {
        return Permission::CMS_MANAGE;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('title')->label('Despre ce este')->required()->maxLength(200)->placeholder('De exemplu: Programul de sărbători'),
            Textarea::make('content')->label('Ce trebuie să știe asistentul')->required()->rows(8)->maxLength(5000)
                ->helperText('Scrie simplu, ca pentru un coleg nou la recepție. Asistentul folosește aceste informații înaintea celorlalte de pe site.'),
            Toggle::make('is_active')->label('Folosită de asistent')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort')->reorderable('sort')->columns([
            TextColumn::make('title')->label('Despre')->searchable()->wrap()->description(fn (AssistantFact $r) => \Illuminate\Support\Str::limit($r->content, 120)),
            IconColumn::make('is_active')->label('Folosită')->boolean(),
            TextColumn::make('updated_at')->label('Modificată')->since(),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAssistantFacts::route('/'), 'create' => Pages\CreateAssistantFact::route('/create'), 'edit' => Pages\EditAssistantFact::route('/{record}/edit')];
    }
}

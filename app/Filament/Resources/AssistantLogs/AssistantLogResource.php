<?php

namespace App\Filament\Resources\AssistantLogs;

use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Models\AssistantFact;
use App\Models\AssistantLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/** Ce au intrebat vizitatorii. De aici clinica adauga raspunsuri care lipseau (le invata asistentul). */
class AssistantLogResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = AssistantLog::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'Asistent AI: întrebări';

    protected static ?string $modelLabel = 'întrebare';

    protected static ?string $pluralModelLabel = 'întrebări primite de asistent';

    protected static ?string $slug = 'asistent-ai-intrebari';

    protected static ?int $navigationSort = 102;

    public static function permission(): string
    {
        return Permission::CMS_MANAGE;
    }

    protected static function allows(string $action, ?Model $record = null): bool
    {
        return in_array($action, ['viewAny', 'view'], true) && (bool) auth()->user()?->can(static::permission());
    }

    public static function getNavigationBadge(): ?string
    {
        $n = AssistantLog::query()->where('reviewed', false)->where('created_at', '>=', now()->subDays(7))->count();

        return $n ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        $labels = ['answered' => 'Răspuns', 'refused' => 'Refuzată', 'limit' => 'Limită zilnică', 'busy' => 'Aglomerat', 'error' => 'Eroare', 'empty' => 'Fără răspuns'];

        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('created_at')->label('Când')->dateTime('d.m.Y H:i')->sortable(),
            TextColumn::make('question')->label('Întrebarea')->wrap()->searchable()->limit(160),
            TextColumn::make('answer')->label('Răspunsul dat')->wrap()->limit(220)->toggleable(),
            TextColumn::make('status')->label('Stare')->badge()->formatStateUsing(fn (string $state) => $labels[$state] ?? $state)
                ->color(fn (string $state) => $state === 'answered' ? 'success' : 'warning'),
            IconColumn::make('reviewed')->label('Văzută')->boolean(),
        ])->filters([
            Filter::make('unreviewed')->label('Nevăzute')->query(fn (Builder $query) => $query->where('reviewed', false)),
            SelectFilter::make('status')->label('Stare')->options($labels),
        ])->recordActions([
            Action::make('teach')->label('Învață-l răspunsul')->icon('heroicon-o-academic-cap')
                ->modalHeading('Adaugă o informație pentru asistent')
                ->modalDescription(fn (AssistantLog $r) => 'Întrebarea: „'.Str::limit($r->question, 300).'”')
                ->fillForm(fn (AssistantLog $r) => ['title' => Str::limit($r->question, 190, ''), 'content' => ''])
                ->schema([
                    TextInput::make('title')->label('Despre ce este')->required()->maxLength(200),
                    Textarea::make('content')->label('Răspunsul corect')->required()->rows(6)->maxLength(5000),
                ])
                ->action(function (AssistantLog $record, array $data) {
                    AssistantFact::create(['title' => $data['title'], 'content' => $data['content'], 'is_active' => true, 'created_by' => auth()->id()]);
                    $record->update(['reviewed' => true]);
                    Notification::make()->title('Asistentul a învățat răspunsul. Îl folosește de la următoarea întrebare.')->success()->send();
                }),
            Action::make('seen')->label('Văzută')->icon('heroicon-o-check')->color('gray')->visible(fn (AssistantLog $r) => ! $r->reviewed)
                ->action(fn (AssistantLog $record) => $record->update(['reviewed' => true])),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAssistantLogs::route('/')];
    }
}

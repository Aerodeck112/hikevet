<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\AssistantLog;
use App\Services\Assistant\Assistant;
use App\Services\Assistant\Knowledge;
use App\Support\Audit;
use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

/** Asistentul AI de pe site: cheia API, modelul, limitele si cate intrebari a primit. */
class AssistantSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|UnitEnum|null $navigationGroup = 'Site';

    protected static ?string $navigationLabel = 'Asistent AI: setări';

    protected static ?string $title = 'Asistentul AI (Hiko)';

    protected static ?string $slug = 'asistent-ai';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(Permission::SETTINGS_MANAGE);
    }

    public function mount(): void
    {
        $this->form->fill([
            'enabled' => (bool) Settings::get('assistant.enabled'),
            'model' => Settings::get('assistant.model'),
            'effort' => Settings::get('assistant.effort'),
            'daily_limit' => (int) Settings::get('assistant.daily_limit'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Stare')->columns(2)->schema([
                Placeholder::make('status')->label('Pe site')->content(fn () => Assistant::enabled() ? 'Activ: butonul „Întreabă-l pe Hiko” apare pe toate paginile.' : 'Oprit.'),
                Placeholder::make('usage')->label('Întrebări în ultimele 30 de zile')->content(function () {
                    $q = AssistantLog::query()->where('created_at', '>=', now()->subDays(30));

                    return number_format((clone $q)->count(), 0, ',', '.').' întrebări, '
                        .number_format((int) (clone $q)->sum('input_tokens') + (int) (clone $q)->sum('cache_read_tokens'), 0, ',', '.').' tokeni citiți, '
                        .number_format((int) (clone $q)->sum('output_tokens'), 0, ',', '.').' tokeni scriși';
                }),
            ]),
            Section::make('Conexiunea cu Claude (Anthropic)')->columns(2)
                ->description('Cheia API se creează în contul Anthropic (platform.claude.com → API keys) și se plătește după consum. Se salvează criptat și nu se mai afișează.')
                ->schema([
                    Toggle::make('enabled')->label('Asistentul apare pe site')->columnSpanFull(),
                    TextInput::make('api_key')->label('Cheia API Anthropic (sk-ant-…)')->password()->autocomplete('off')->columnSpanFull()
                        ->placeholder(fn () => filled(Settings::get('assistant.api_key')) ? 'Configurată. Lasă gol ca să o păstrezi.' : 'Nu este configurată')
                        ->rule('nullable')->rule('starts_with:sk-ant-'),
                    Select::make('model')->label('Model')->required()->options([
                        'claude-opus-5-5' => 'Claude Opus 5.5 (recomandat)',
                        'claude-sonnet-5-5' => 'Claude Sonnet 5.5 (mai ieftin)',
                        'claude-haiku-5-5' => 'Claude Haiku 5.5 (cel mai ieftin)',
                    ]),
                    Select::make('effort')->label('Cât de atent gândește')->required()->options(['low' => 'Rapid', 'medium' => 'Echilibrat (recomandat)', 'high' => 'Atent'])
                        ->helperText('„Atent” răspunde mai încet și consumă mai mult.'),
                    TextInput::make('daily_limit')->label('Întrebări pe zi de la același vizitator')->numeric()->minValue(3)->maxValue(500)->required(),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reload')->label('Reîncarcă ce știe asistentul')->icon('heroicon-o-arrow-path')->color('gray')
                ->action(function () {
                    Knowledge::forget();
                    Notification::make()->title('Asistentul va folosi conținutul actual al site-ului de la următoarea întrebare.')->success()->send();
                }),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $changed = [];
        foreach (['enabled' => (bool) $state['enabled'], 'model' => $state['model'], 'effort' => $state['effort'], 'daily_limit' => (int) $state['daily_limit']] as $field => $value) {
            if (Settings::get('assistant.'.$field) !== $value) {
                Settings::set('assistant.'.$field, $value);
                $changed[] = 'assistant.'.$field;
            }
        }
        if (filled($state['api_key'] ?? null)) {
            Settings::set('assistant.api_key', trim((string) $state['api_key']), true);
            $changed[] = 'assistant.api_key';
        }
        $this->data['api_key'] = null;
        if ($state['enabled'] && ! filled(Settings::get('assistant.api_key'))) {
            Notification::make()->title('Asistentul nu apare pe site până nu adaugi cheia API.')->warning()->send();
        }
        if ($changed) {
            Audit::log('settings.updated', null, 'Setări asistent AI modificate: '.implode(', ', $changed));
        }
        Notification::make()->title('Setările asistentului au fost salvate')->success()->send();
    }
}

<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Support\Audit;
use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

/** Datele clinicii, destinatarii emailurilor si modul de programare. */
class ClinicSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Setări clinică';

    protected static ?string $title = 'Setări clinică';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.settings-form';

    public ?array $data = [];

    /** cheie formular => cheie setare */
    protected const MAP = [
        'name' => 'clinic.name', 'legal_name' => 'clinic.legal_name', 'tagline' => 'clinic.tagline', 'phone' => 'clinic.phone', 'email' => 'clinic.email',
        'address' => 'clinic.address', 'city' => 'clinic.city', 'maps_query' => 'clinic.maps_query', 'review_url' => 'clinic.review_url',
        'instagram' => 'clinic.instagram', 'facebook' => 'clinic.facebook', 'tiktok' => 'clinic.tiktok', 'hours' => 'clinic.hours',
        'booking_mode' => 'booking.mode', 'digitail_url' => 'booking.digitail_url',
        'clinic_recipients' => 'mail.clinic_recipients', 'sterilization_recipients' => 'mail.sterilization_recipients',
        'expiry_notice_days' => 'subscriptions.expiry_notice_days', 'code_ttl_hours' => 'consent.code_ttl_hours', 'max_attempts' => 'consent.max_attempts',
    ];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(Permission::SETTINGS_MANAGE);
    }

    public function mount(): void
    {
        $this->form->fill(collect(static::MAP)->map(fn ($key) => Settings::get($key))->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Clinica')->columns(2)->schema([
                TextInput::make('name')->label('Nume afișat')->required()->maxLength(80),
                TextInput::make('legal_name')->label('Denumire juridică')->maxLength(120),
                TextInput::make('tagline')->label('Descriere scurtă')->maxLength(200)->columnSpanFull(),
                TextInput::make('phone')->label('Telefon')->required()->maxLength(30),
                TextInput::make('email')->label('Email public')->email()->required(),
                TextInput::make('address')->label('Adresă')->required()->maxLength(160),
                TextInput::make('city')->label('Localitate')->required()->maxLength(80),
                TextInput::make('maps_query')->label('Căutare pentru harta Google')->maxLength(200)->columnSpanFull(),
                TagsInput::make('hours')->label('Program')->placeholder('Mo-Fr 09:00-18:00')->columnSpanFull()
                    ->helperText('Format schema.org, câte un interval: Mo-Fr 09:00-18:00, Sa 09:00-13:00. Gol = programul nu este publicat în datele structurate.'),
            ]),
            Section::make('Rețele sociale')->columns(2)->collapsed()->schema([
                TextInput::make('instagram')->label('Instagram')->url(), TextInput::make('facebook')->label('Facebook')->url(),
                TextInput::make('tiktok')->label('TikTok')->url(), TextInput::make('review_url')->label('Link recenzii Google')->url(),
            ]),
            Section::make('Programări')->columns(2)
                ->description('Un singur sistem primește programări la un moment dat. Comută pe sistemul propriu doar după ce orarul medicilor este completat și fluxul a fost testat.')
                ->schema([
                    Select::make('booking_mode')->label('Butoanele „Programează-te” duc la')->required()
                        ->options(['digitail' => 'Digitail (ca pe site-ul actual)', 'internal' => 'Sistemul propriu de programări']),
                    TextInput::make('digitail_url')->label('Adresa Digitail')->url()->required(),
                ]),
            Section::make('Notificări')->columns(2)->schema([
                TagsInput::make('clinic_recipients')->label('Emailuri clinică (contracte, programări, mesaje)')->placeholder('adresa@exemplu.ro')->nestedRecursiveRules(['email']),
                TagsInput::make('sterilization_recipients')->label('Emailuri pentru campania de sterilizare')->placeholder('adresa@exemplu.ro')->nestedRecursiveRules(['email']),
                TextInput::make('expiry_notice_days')->label('Avertizare expirare abonament (zile înainte)')->numeric()->minValue(1)->maxValue(30)->required(),
            ]),
            Section::make('Consimțământ pe tabletă')->columns(2)->collapsed()->schema([
                TextInput::make('code_ttl_hours')->label('Valabilitatea codului (ore)')->numeric()->minValue(1)->maxValue(72)->required(),
                TextInput::make('max_attempts')->label('Încercări greșite permise')->numeric()->minValue(3)->maxValue(20)->required(),
            ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $changed = [];
        foreach (static::MAP as $field => $key) {
            $value = $state[$field] ?? null;
            if (in_array($field, ['expiry_notice_days', 'code_ttl_hours', 'max_attempts'], true)) {
                $value = (int) $value;
            }
            if (Settings::get($key) !== $value) {
                Settings::set($key, $value);
                $changed[] = $key;
            }
        }
        if ($changed) {
            Audit::log('settings.updated', null, 'Setări modificate: '.implode(', ', $changed));
        }
        Notification::make()->title('Setările au fost salvate')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Salvează setările')->submit('save')];
    }
}

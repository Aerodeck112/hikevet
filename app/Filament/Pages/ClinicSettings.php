<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Services\Campaign\CampaignMailer;
use App\Services\Campaign\CaseFields;
use App\Services\Payments\StripeCheckout;
use App\Support\Audit;
use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
        'campaign_active' => 'campaign.active', 'campaign_doctors' => 'campaign.doctori', 'campaign_assistants' => 'campaign.asistenti', 'campaign_copy' => 'campaign.copy_addresses',
    ];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(Permission::SETTINGS_MANAGE);
    }

    public function mount(): void
    {
        $state = collect(static::MAP)->map(fn ($key) => Settings::get($key))->all();
        // Campania: daca listele nu au fost inca setate, se afiseaza valorile din pluginul vechi (cele folosite oricum).
        $state['campaign_active'] = CampaignMailer::active();
        $state['stripe_enabled'] = (bool) Settings::get('payments.stripe_enabled', false);
        $state['campaign_doctors'] = CaseFields::staffList('doctori');
        $state['campaign_assistants'] = CaseFields::staffList('asistenti');
        $state['campaign_copy'] = CampaignMailer::copyAddresses();
        $this->form->fill($state);
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
            Section::make('Campanie sterilizare')->columns(1)->collapsed()->schema([
                Toggle::make('campaign_active')->label('Campania este activă')
                    ->helperText('Oprită: campania rămâne doar ca istoric. Nu se trimite niciun email al campaniei, nu se fac programări și nu se încarcă fotografii; dosarele și rapoartele pentru Primărie rămân disponibile.'),
                TagsInput::make('campaign_doctors')->label('Medici (medic operator, doctor responsabil, validat de)')->required(),
                TagsInput::make('campaign_assistants')->label('Asistenți'),
                TagsInput::make('campaign_copy')->label('Copie internă a emailurilor campaniei')->nestedRecursiveRules(['email'])->required(),
            ]),
            Section::make('Plăți online cu cardul (Stripe)')->columns(1)->collapsed()
                ->description('Clienții plătesc pe pagina securizată Stripe; datele cardului nu ajung pe acest server. Factura se emite manual: plățile încasate apar în Abonamente → Plăți online.')
                ->schema([
                    Placeholder::make('stripe_status')->label('Stare')->content(fn () => ! StripeCheckout::configured() ? 'Cheia Stripe nu este configurată.'
                        : (StripeCheckout::liveMode() ? 'Cheie reală: plățile încasează bani.' : 'Cheie de test: nu se încasează bani reali (carduri de test Stripe).')
                        .(StripeCheckout::webhookSecret() ? ' Notificările Stripe (webhook) sunt configurate.' : ' Notificările Stripe (webhook) nu sunt configurate: plățile se confirmă la întoarcerea clientului și prin verificarea orară.')),
                    Toggle::make('stripe_enabled')->label('Clienții pot plăti online cu cardul (Wallet și abonament)')
                        ->helperText('Se poate porni doar după ce cheia Stripe este salvată.'),
                    TextInput::make('stripe_secret')->label('Cheia secretă Stripe (sk_test_… sau sk_live_…)')->password()->autocomplete('off')
                        ->placeholder(fn () => StripeCheckout::configured() ? 'salvată (…'.substr(StripeCheckout::secret(), -4).'); completează doar pentru a o schimba' : '')
                        ->rule('nullable')->regex('/^(sk|rk)_(test|live)_[A-Za-z0-9]+$/')->dehydrated(fn ($state) => filled($state)),
                    TextInput::make('stripe_webhook_secret')->label('Secretul notificărilor Stripe (whsec_…)')->password()->autocomplete('off')
                        ->placeholder(fn () => StripeCheckout::webhookSecret() ? 'salvat; completează doar pentru a-l schimba' : '')
                        ->helperText(fn () => 'În Stripe, la Developers → Webhooks, adresa este '.url('/stripe/webhook').', cu evenimentele checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed și checkout.session.expired.')
                        ->regex('/^whsec_[A-Za-z0-9]+$/')->dehydrated(fn ($state) => filled($state)),
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
            if ($field === 'campaign_active') {
                $value = (bool) $value;
                if ($value === CampaignMailer::active()) {
                    continue;
                }
            }
            if (Settings::get($key) !== $value) {
                Settings::set($key, $value);
                $changed[] = $key;
            }
        }
        foreach (['stripe_secret' => 'payments.stripe_secret', 'stripe_webhook_secret' => 'payments.stripe_webhook_secret'] as $field => $key) {
            if (filled($state[$field] ?? null)) {
                Settings::set($key, trim((string) $state[$field]), true);
                $changed[] = $key;
            }
        }
        $enable = (bool) ($state['stripe_enabled'] ?? false);
        if ($enable && ! StripeCheckout::configured()) {
            $enable = false;
            Notification::make()->title('Plata online rămâne oprită: lipsește cheia Stripe.')->warning()->send();
        }
        if ($enable !== (bool) Settings::get('payments.stripe_enabled', false)) {
            Settings::set('payments.stripe_enabled', $enable);
            $changed[] = 'payments.stripe_enabled';
        }
        $this->data['stripe_secret'] = $this->data['stripe_webhook_secret'] = null;
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

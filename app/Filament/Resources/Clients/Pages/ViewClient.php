<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Enums\BalanceType;
use App\Enums\ContractStatus;
use App\Enums\Permission;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\DoctorNote;
use App\Models\Pet;
use App\Models\PriceList;
use App\Models\Treatment;
use App\Models\WalletTopupRequest;
use App\Services\Clients\ClientAccounts;
use App\Services\Subscriptions\PricingEngine;
use App\Services\Subscriptions\SubscriptionActivation;
use App\Services\Wallet\WalletLedger;
use App\Support\Audit;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

/** Fisa clientului: abonament, animale, tratamente, Wallet, notite si jurnal, cu operatiunile personalului. */
class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected string $view = 'filament.clients.view';

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    private function can(string $permission): bool
    {
        return (bool) auth()->user()?->can($permission);
    }

    /** Ruleaza o operatiune de business si afiseaza rezultatul; erorile de domeniu devin mesaje, nu ecrane de eroare. */
    private function run(callable $operation, string $success): void
    {
        try {
            $operation();
            Notification::make()->title($success)->success()->send();
        } catch (\DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->persistent()->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Operațiunea nu a putut fi salvată.')->body('Eroarea a fost înregistrată în jurnal.')->danger()->send();
        }
    }

    private function petOptions(): array
    {
        return $this->record->activePets()->get()->mapWithKeys(fn (Pet $p) => [$p->id => $p->name.' ('.$p->speciesLabel().($p->tier_label ? ', '.$p->tier_label : '').')'])->all();
    }

    protected function getHeaderActions(): array
    {
        $ledger = app(WalletLedger::class);

        return [
            Action::make('validatePayment')->label('Confirmă plata')->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn () => $this->can(Permission::CONTRACTS_VALIDATE_PAYMENT) && $this->record->contracts()->whereIn('status', ['awaiting_clinic', 'active', 'expired', 'offer_lapsed'])->exists())
                ->modalHeading('Confirmă plata abonamentului la clinică')
                ->modalDescription('Abonamentul devine activ, iar punctele se adaugă automat: preț lunar contractual × numărul de luni.')
                ->schema([
                    Select::make('contract_id')->label('Contract')->required()->live()
                        ->options(fn () => $this->record->contracts()->whereIn('status', ['awaiting_clinic', 'active', 'expired', 'offer_lapsed'])->get()
                            ->mapWithKeys(fn (Contract $c) => [$c->id => $c->number.' · '.$c->pet_name.' · '.$c->tier_label.' · '.$c->status->label()]))
                        ->default(fn () => $this->record->contracts()->where('status', 'awaiting_clinic')->value('id')),
                    Select::make('months')->label('Perioada plătită')->required()->live()
                        ->options([1 => 'Lunar', 6 => '6 luni', 12 => '12 luni'])
                        ->default(fn () => $this->record->contracts()->where('status', 'awaiting_clinic')->value('period_months') ?? 1),
                    TextInput::make('summary')->label('De încasat și puncte')->disabled()->dehydrated(false)
                        ->placeholder(function (callable $get) {
                            $contract = $get('contract_id') ? Contract::find($get('contract_id')) : null;
                            if (! $contract || ! $get('months')) {
                                return 'Alege contractul și perioada';
                            }
                            $activation = app(SubscriptionActivation::class);

                            return $activation->amountFor($contract, (int) $get('months')).' lei · '.$activation->pointsFor($contract, (int) $get('months')).' puncte';
                        }),
                ])
                ->action(fn (array $data) => $this->run(fn () => app(SubscriptionActivation::class)->validatePayment(
                    $this->record->contracts()->findOrFail($data['contract_id']), (int) $data['months'], auth()->user()), 'Plata a fost confirmată și abonamentul este activ')),

            Action::make('treatment')->label('Serviciu din abonament')->icon('heroicon-o-clipboard-document-check')
                ->visible(fn () => $this->can(Permission::TREATMENTS_MANAGE))
                ->modalDescription(fn () => 'Puncte disponibile: '.$ledger->points($this->record).'. Serviciile incluse consumă doar puncte de abonament.')
                ->schema([
                    Select::make('pet_id')->label('Animal')->options(fn () => $this->petOptions())->required()->live()
                        ->default(fn () => $this->record->activePets()->count() === 1 ? $this->record->activePets()->value('id') : null),
                    Select::make('service')->label('Serviciu din catalog')->live()
                        ->options(fn (callable $get) => $this->catalogFor($get('pet_id')))
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $row = $this->catalogRows($get('pet_id'))[$state] ?? null;
                            if ($row) {
                                $set('name', $row['name']);
                                $set('points', $row['points']);
                            }
                        })
                        ->helperText('Lista depinde de specia și categoria de greutate a animalului.'),
                    TextInput::make('name')->label('Denumire serviciu')->required()->maxLength(190),
                    TextInput::make('points')->label('Puncte consumate')->numeric()->minValue(1)->required(),
                    Textarea::make('notes')->label('Observații')->rows(2),
                ])
                ->action(fn (array $data) => $this->run(fn () => $ledger->consumeSubscriptionService($this->record, $data['name'], (int) $data['points'], auth()->user(),
                    $this->record->pets()->find($data['pet_id']), 0, (string) ($data['notes'] ?? '')), 'Serviciul a fost înregistrat')),

            ActionGroup::make([
                Action::make('deposit')->label('Alimentează Wallet')->icon('heroicon-o-banknotes')
                    ->visible(fn () => $this->can(Permission::WALLET_OPERATE))
                    ->modalDescription('La X lei depuși: X lei principal, X lei bonus servicii, X/2 lei bonus produse. Doar cu abonament activ.')
                    ->schema([
                        TextInput::make('amount')->label('Suma depusă la clinică (lei)')->numeric()->minValue(1)->required(),
                        Select::make('request_id')->label('Solicitare a clientului (opțional)')
                            ->options(fn () => WalletTopupRequest::query()->where('user_id', $this->record->id)->where('status', 'pending')->get()
                                ->mapWithKeys(fn ($r) => [$r->id => WalletLedger::money((float) $r->amount).' lei, cerută la '.$r->created_at->format('d.m.Y')])),
                        TextInput::make('note')->label('Notă')->maxLength(190),
                    ])
                    ->action(fn (array $data) => $this->run(function () use ($data, $ledger) {
                        $ledger->deposit($this->record, (float) $data['amount'], auth()->user(), (string) ($data['note'] ?? ''));
                        if (! empty($data['request_id'])) {
                            WalletTopupRequest::query()->where('user_id', $this->record->id)->whereKey($data['request_id'])
                                ->update(['status' => 'confirmed', 'handled_by' => auth()->id(), 'handled_at' => now()]);
                        }
                    }, 'Wallet-ul a fost alimentat')),

                Action::make('purchase')->label('Plată din Wallet')->icon('heroicon-o-shopping-bag')
                    ->visible(fn () => $this->can(Permission::WALLET_OPERATE))
                    ->schema([
                        Select::make('category')->label('Tip')->options(['service' => 'Serviciu (bonus până la 50%)', 'product' => 'Produs (bonus până la 33,33%)'])->default('service')->required()->live(),
                        TextInput::make('name')->label('Denumire')->required()->maxLength(190)->live(onBlur: true),
                        TextInput::make('amount')->label('Valoare (lei)')->numeric()->minValue(0.01)->required()->live(onBlur: true),
                        Select::make('pet_id')->label('Animal')->options(fn () => $this->petOptions()),
                        Toggle::make('bonus_blocked')->label('Fără bonus (cost extern sau marjă redusă)')->live(),
                        TextInput::make('estimate')->label('Împărțire estimată')->disabled()->dehydrated(false)
                            ->placeholder(function (callable $get) use ($ledger) {
                                if (! $get('amount') || ! $get('name')) {
                                    return 'Completează denumirea și valoarea';
                                }
                                $blocked = (bool) $get('bonus_blocked') || $ledger->bonusExclusionReason((string) $get('name')) !== '';
                                $calc = $ledger->calculatePurchase($this->record, (string) $get('category'), (float) $get('amount'), $blocked);

                                return 'Principal '.WalletLedger::money($calc['principal_used']).' lei · bonus '.WalletLedger::money($calc['bonus_used']).' lei'
                                    .($calc['sufficient'] ? '' : ' · SOLD INSUFICIENT').($blocked ? ' · bonus exclus' : '');
                            }),
                        Textarea::make('notes')->label('Observații')->rows(2),
                    ])
                    ->action(fn (array $data) => $this->run(fn () => $ledger->purchase($this->record, $data['category'], $data['name'], (float) $data['amount'], auth()->user(),
                        ! empty($data['pet_id']) ? $this->record->pets()->find($data['pet_id']) : null, (string) ($data['notes'] ?? ''), (bool) ($data['bonus_blocked'] ?? false)), 'Plata din Wallet a fost înregistrată')),

                Action::make('adjust')->label('Ajustare manuală de sold')->icon('heroicon-o-adjustments-horizontal')->color('warning')
                    ->visible(fn () => $this->can(Permission::WALLET_ADJUST))
                    ->modalDescription('Numele angajatului apare în istoricul clientului și în notificarea trimisă acestuia.')
                    ->schema([
                        Select::make('balance_type')->label('Sold')->required()->options(collect(BalanceType::adjustable())->mapWithKeys(fn ($b) => [$b->value => $b->label()])),
                        TextInput::make('delta')->label('Modificare (+ adaugă, − scade)')->numeric()->required(),
                        TextInput::make('employee_name')->label('Numele și prenumele angajatului')->required()->default(fn () => auth()->user()->name),
                        Textarea::make('reason')->label('Motiv')->rows(2)->required(),
                    ])
                    ->action(fn (array $data) => $this->run(fn () => $ledger->manualAdjust($this->record, BalanceType::from($data['balance_type']), (float) $data['delta'],
                        (string) $data['employee_name'], auth()->user(), (string) $data['reason']), 'Ajustarea a fost aplicată')),
            ])->label('Wallet')->icon('heroicon-o-wallet')->button()->color('gray'),

            ActionGroup::make([
                Action::make('addPet')->label('Adaugă animal')->icon('heroicon-o-plus')
                    ->visible(fn () => $this->can(Permission::CLIENTS_MANAGE))
                    ->schema($this->petForm())
                    ->action(fn (array $data) => $this->run(fn () => app(ClientAccounts::class)->upsertPet($this->record, $data), 'Animalul a fost adăugat')),
                Action::make('note')->label('Notiță internă')->icon('heroicon-o-pencil-square')
                    ->visible(fn () => $this->can(Permission::MEDICAL_NOTES))
                    ->schema([
                        Textarea::make('note_text')->label('Notiță')->rows(4)->required(),
                        Select::make('pet_id')->label('Animal')->options(fn () => $this->petOptions()),
                        Select::make('priority')->label('Prioritate')->options(['normal' => 'Normală', 'high' => 'Ridicată', 'urgent' => 'Urgentă'])->default('normal')->required(),
                    ])
                    ->action(function (array $data) {
                        $note = DoctorNote::create(['user_id' => $this->record->id, 'pet_id' => $data['pet_id'] ?? null, 'note_text' => $data['note_text'], 'priority' => $data['priority'], 'actor_id' => auth()->id()]);
                        Audit::log('note.created', $note, 'Notiță internă adăugată', null, null, $this->record->id);
                        Notification::make()->title('Notița a fost salvată')->success()->send();
                    }),
                Action::make('activation')->label('Trimite link de setare a parolei')->icon('heroicon-o-key')
                    ->visible(fn () => $this->can(Permission::CLIENTS_MANAGE))->requiresConfirmation()
                    ->action(fn () => $this->run(fn () => app(ClientAccounts::class)->sendActivation($this->record), 'Emailul a fost trimis')),
                EditAction::make()->label('Editează datele proprietarului'),
            ])->label('Mai mult')->icon('heroicon-o-ellipsis-horizontal')->button()->color('gray'),
        ];
    }

    private function petForm(): array
    {
        return [
            TextInput::make('name')->label('Nume')->required()->maxLength(80),
            Select::make('species')->label('Specie')->options(['dog' => 'Câine', 'cat' => 'Pisică'])->required(),
            Select::make('sex')->label('Sex')->options(['male' => 'Mascul', 'female' => 'Femelă']),
            TextInput::make('breed')->label('Rasă')->maxLength(80),
            DatePicker::make('date_of_birth')->label('Data nașterii')->maxDate(now()),
            TextInput::make('weight_kg')->label('Greutate (kg)')->numeric()->minValue(0.1)->maxValue(120)
                ->helperText('Categoria de greutate se calculează automat din specie și greutate.'),
            TextInput::make('microchip')->label('Microcip')->maxLength(40),
            Select::make('sterilized')->label('Sterilizat / castrat')->options(['da' => 'Da', 'nu' => 'Nu']),
        ];
    }

    /** @return array<string, array{name:string,points:int}> */
    private function catalogRows(mixed $petId): array
    {
        $pet = $petId ? $this->record->pets()->find($petId) : null;
        if (! $pet || ! $pet->tier_label) {
            return [];
        }
        $tier = PriceList::active()->tiers()->where('species', $pet->species)->where('label', $pet->tier_label)->with('services')->first();

        return $tier ? $tier->services->mapWithKeys(fn ($s) => ['s'.$s->id => ['name' => preg_replace('/ x[0-9].*$/', '', $s->name), 'points' => (int) $s->points_per_use]])->all() : [];
    }

    private function catalogFor(mixed $petId): array
    {
        return collect($this->catalogRows($petId))->map(fn ($row) => $row['name'].' · '.$row['points'].' puncte')->all();
    }

    // --- Actiuni din tabelele fisei (apelate din Blade prin wire:click) ---

    public function deleteTreatmentAction(): Action
    {
        return Action::make('deleteTreatment')->label('Șterge')->color('danger')->link()->requiresConfirmation()
            ->visible(fn () => $this->can(Permission::TREATMENTS_MANAGE))
            ->modalHeading('Șterge operațiunea')->modalDescription('Punctele sau soldurile folosite se restituie automat. Motivul rămâne în jurnal.')
            ->schema([Textarea::make('reason')->label('Motiv')->required()->rows(2)])
            ->action(fn (array $data, array $arguments) => $this->run(fn () => app(WalletLedger::class)->deleteTreatment(
                Treatment::query()->where('user_id', $this->record->id)->findOrFail($arguments['id']), (string) $data['reason'], auth()->user()), 'Operațiunea a fost ștearsă și soldul restituit'));
    }

    public function restoreTreatmentAction(): Action
    {
        return Action::make('restoreTreatment')->label('Restaurează')->link()->requiresConfirmation()
            ->visible(fn () => $this->can(Permission::TREATMENTS_MANAGE))
            ->action(fn (array $arguments) => $this->run(fn () => app(WalletLedger::class)->restoreTreatment(
                Treatment::query()->where('user_id', $this->record->id)->findOrFail($arguments['id']), auth()->user()), 'Operațiunea a fost restaurată'));
    }

    public function archivePetAction(): Action
    {
        return Action::make('archivePet')->label('Arhivează')->color('gray')->link()
            ->visible(fn () => $this->can(Permission::CLIENTS_MANAGE))
            ->schema([Textarea::make('reason')->label('Motiv')->required()->rows(2)])
            ->action(fn (array $data, array $arguments) => $this->run(fn () => app(ClientAccounts::class)->archivePet(
                $this->record->pets()->findOrFail($arguments['id']), auth()->user(), (string) $data['reason']), 'Animalul a fost arhivat'));
    }

    public function restorePetAction(): Action
    {
        return Action::make('restorePet')->label('Restaurează')->link()
            ->visible(fn () => $this->can(Permission::CLIENTS_MANAGE))
            ->action(fn (array $arguments) => $this->run(fn () => app(ClientAccounts::class)->restorePet($this->record->pets()->findOrFail($arguments['id'])), 'Animalul a fost restaurat'));
    }

    public function editPetAction(): Action
    {
        return Action::make('editPet')->label('Editează')->link()
            ->visible(fn () => $this->can(Permission::CLIENTS_MANAGE))
            ->fillForm(fn (array $arguments) => $this->record->pets()->findOrFail($arguments['id'])->only(['name', 'species', 'sex', 'breed', 'date_of_birth', 'weight_kg', 'microchip', 'sterilized']))
            ->schema($this->petForm())
            ->action(function (array $data, array $arguments) {
                $pet = $this->record->pets()->findOrFail($arguments['id']);
                $tier = isset($data['weight_kg']) ? app(PricingEngine::class)->tierFor($data['species'], (float) $data['weight_kg'])?->label : $pet->tier_label;
                $pet->update($data + ['tier_label' => $tier ?? $pet->tier_label]);
                Audit::log('pet.updated', $pet, 'Animal modificat din fișa clientului: '.$pet->name, null, null, $this->record->id);
                Notification::make()->title('Animalul a fost actualizat')->success()->send();
            });
    }

    public function toggleNoteAction(): Action
    {
        return Action::make('toggleNote')->label('Schimbă starea')->link()
            ->visible(fn () => $this->can(Permission::MEDICAL_NOTES))
            ->action(function (array $arguments) {
                $note = DoctorNote::query()->where('user_id', $this->record->id)->findOrFail($arguments['id']);
                $note->update(['status' => $note->status === 'open' ? 'resolved' : 'open']);
            });
    }

    protected function getViewData(): array
    {
        $client = $this->record;
        $ledger = app(WalletLedger::class);
        $canNotes = $this->can(Permission::MEDICAL_NOTES);

        return [
            'client' => $client,
            'balances' => $ledger->balances($client),
            'hasActive' => $ledger->hasActiveSubscription($client),
            'pets' => $client->pets()->orderBy('id')->get(),
            'contracts' => $client->contracts()->with(['pet', 'validator'])->get(),
            'treatments' => $client->treatments()->with('pet')->latest('id')->limit(40)->get(),
            'transactions' => $this->can(Permission::WALLET_VIEW) ? $client->walletTransactions()->with('actor')->latest('id')->limit(60)->get() : collect(),
            'notes' => $canNotes ? DoctorNote::query()->where('user_id', $client->id)->whereNull('deleted_at')->with(['pet', 'actor'])->latest('id')->get() : collect(),
            'audit' => $this->can(Permission::AUDIT_VIEW) ? AuditLog::query()->where('client_id', $client->id)->latest('id')->limit(40)->get() : collect(),
            'topups' => WalletTopupRequest::query()->where('user_id', $client->id)->where('status', 'pending')->get(),
            'alerts' => $this->alerts($ledger),
            'canWallet' => $this->can(Permission::WALLET_VIEW),
            'canContracts' => $this->can(Permission::CONTRACTS_VIEW),
        ];
    }

    /** Alerte din fisa pacientului (render_patient_alerts in Wallet Core 3.0). */
    private function alerts(WalletLedger $ledger): array
    {
        $alerts = [];
        $client = $this->record;

        if ($client->contracts()->where('status', ContractStatus::AwaitingClinic)->exists()) {
            $alerts[] = ['warn', 'Are un contract semnat care așteaptă plata la clinică.'];
        }
        $expiring = $client->contracts()->where('status', ContractStatus::Active)->whereBetween('expires_at', [now(), now()->addDays(7)])->first();
        if ($expiring) {
            $alerts[] = ['warn', 'Abonamentul pentru '.$expiring->pet_name.' expiră la '.$expiring->expires_at->format('d.m.Y').'.'];
        }
        if ($ledger->hasActiveSubscription($client) && $ledger->points($client) < 100) {
            $alerts[] = ['info', 'Mai puțin de 100 de puncte de abonament disponibile.'];
        }
        if ($client->must_set_password) {
            $alerts[] = ['info', 'Clientul nu și-a setat încă parola contului.'];
        }
        if (DoctorNote::query()->where('user_id', $client->id)->whereNull('deleted_at')->where('status', 'open')->whereIn('priority', ['high', 'urgent'])->exists()) {
            $alerts[] = ['bad', 'Există notițe interne nerezolvate cu prioritate ridicată.'];
        }

        return $alerts;
    }
}

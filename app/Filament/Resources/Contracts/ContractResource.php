<?php

namespace App\Filament\Resources\Contracts;

use App\Enums\ContractStatus;
use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Contract;
use App\Services\Subscriptions\ContractService;
use App\Services\Subscriptions\SubscriptionActivation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Contractele de abonament. Nu se creeaza si nu se editeaza din admin: se nasc doar din configurator. */
class ContractResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = Contract::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Abonamente';

    protected static ?string $navigationLabel = 'Contracte';

    protected static ?string $modelLabel = 'contract';

    protected static ?string $pluralModelLabel = 'contracte';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?int $navigationSort = 10;

    public static function permission(): string
    {
        return Permission::CONTRACTS_VIEW;
    }

    protected static function writable(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = Contract::query()->where('status', ContractStatus::AwaitingClinic)->count();

        return $waiting ? (string) $waiting : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Contracte care așteaptă plata la clinică';
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->modifyQueryUsing(fn ($query) => $query->with('client'))->columns([
            TextColumn::make('number')->label('Număr')->searchable()->sortable()->description(fn (Contract $r) => 'semnat '.$r->signed_at?->format('d.m.Y H:i')),
            TextColumn::make('client.name')->label('Client')->searchable()->url(fn (Contract $r) => ClientResource::getUrl('view', ['record' => $r->user_id])),
            TextColumn::make('pet_name')->label('Animal')->searchable()->description(fn (Contract $r) => $r->tier_label),
            TextColumn::make('price_monthly')->label('Lunar')->suffix(' lei')->description(fn (Contract $r) => $r->discount_percent ? 'reducere '.$r->discount_percent.'%' : null),
            TextColumn::make('payment_amount')->label('De plată / plătit')->formatStateUsing(fn (Contract $r) => number_format((float) $r->payment_amount, 0, ',', '.').' lei · '.($r->period_months === 1 ? 'lunar' : $r->period_months.' luni')),
            TextColumn::make('status')->label('Stare')->badge()->formatStateUsing(fn (ContractStatus $state) => $state->label())->color(fn (ContractStatus $state) => $state->color()),
            TextColumn::make('expires_at')->label('Expiră')->date('d.m.Y')->sortable()->placeholder('–'),
        ])->filters([
            SelectFilter::make('status')->label('Stare')->options(collect(ContractStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            SelectFilter::make('species')->label('Specie')->options(['dog' => 'Câine', 'cat' => 'Pisică']),
        ])->recordActions([
            Action::make('pdf')->label('PDF')->icon('heroicon-o-document-arrow-down')->url(fn (Contract $r) => route('admin.contracts.pdf', $r))->openUrlInNewTab()->visible(fn (Contract $r) => (bool) $r->pdf_path),
            Action::make('validate')->label('Confirmă plata')->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn (Contract $r) => auth()->user()->can(Permission::CONTRACTS_VALIDATE_PAYMENT) && $r->status->canTransitionTo(ContractStatus::Active))
                ->schema([Select::make('months')->label('Perioada plătită')->options([1 => 'Lunar', 6 => '6 luni', 12 => '12 luni'])->default(fn (Contract $r) => $r->period_months)->required()])
                ->action(function (Contract $record, array $data) {
                    try {
                        app(SubscriptionActivation::class)->validatePayment($record, (int) $data['months'], auth()->user());
                        Notification::make()->title('Plata a fost confirmată')->success()->send();
                    } catch (\DomainException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('transition')->label('Schimbă starea')->icon('heroicon-o-arrows-right-left')->color('gray')
                ->visible(fn (Contract $r) => auth()->user()->can(Permission::CONTRACTS_VALIDATE_PAYMENT) && collect($r->status->allowedTransitions())->reject(fn ($s) => $s === ContractStatus::Active)->isNotEmpty())
                ->schema(fn (Contract $record) => [
                    Select::make('to')->label('Stare nouă')->required()->options(collect($record->status->allowedTransitions())
                        ->reject(fn ($s) => $s === ContractStatus::Active)->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                    Textarea::make('note')->label('Motiv')->required()->rows(2),
                ])
                ->action(function (Contract $record, array $data) {
                    try {
                        app(ContractService::class)->transition($record, ContractStatus::from($data['to']), auth()->user(), (string) $data['note']);
                        Notification::make()->title('Starea contractului a fost schimbată')->success()->send();
                    } catch (\DomainException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListContracts::route('/')];
    }
}

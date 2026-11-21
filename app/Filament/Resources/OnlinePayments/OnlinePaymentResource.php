<?php

namespace App\Filament\Resources\OnlinePayments;

use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\OnlinePayment;
use App\Services\Payments\StripeCheckout;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Platile online cu cardul si evidenta facturilor emise manual. Platile nu se creeaza si nu se modifica de aici;
 * se completeaza doar numarul si data facturii. Rambursarile se fac din contul Stripe.
 */
class OnlinePaymentResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = OnlinePayment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Plăți online';

    protected static ?string $modelLabel = 'plată online';

    protected static ?string $pluralModelLabel = 'plăți online';

    protected static ?int $navigationSort = 30;

    public static function permission(): string
    {
        return Permission::CONTRACTS_VALIDATE_PAYMENT;
    }

    protected static function writable(): bool
    {
        return false;
    }

    private static function toInvoice(): Builder
    {
        return OnlinePayment::query()->whereIn('status', ['paid', 'paid_needs_review'])->whereNull('invoice_number');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::toInvoice()->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return OnlinePayment::query()->where('status', 'paid_needs_review')->exists() ? 'danger' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Plăți online încasate pentru care factura nu a fost încă trecută';
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->modifyQueryUsing(fn ($query) => $query->with(['user', 'contract', 'invoiceRecorder']))
            ->description(StripeCheckout::configured()
                ? (StripeCheckout::liveMode() ? 'Stripe: mod real (se încasează bani).' : 'Stripe: mod de test (nu se încasează bani reali).').(StripeCheckout::enabled() ? '' : ' Plata online este oprită în Setări clinică.')
                : 'Stripe nu este configurat: plata online cu cardul nu apare clienților.')
            ->columns([
                TextColumn::make('paid_at')->label('Încasată')->dateTime('d.m.Y H:i')->sortable()->placeholder('–')->description(fn (OnlinePayment $r) => 'pornită '.$r->created_at->format('d.m.Y H:i')),
                TextColumn::make('user.name')->label('Client')->searchable()->url(fn (OnlinePayment $r) => ClientResource::getUrl('view', ['record' => $r->user_id]))->description(fn (OnlinePayment $r) => $r->user?->email),
                TextColumn::make('purpose')->label('Pentru')->formatStateUsing(fn (OnlinePayment $r) => $r->description()),
                TextColumn::make('amount')->label('Sumă')->formatStateUsing(fn (OnlinePayment $r) => number_format((float) $r->amount, 2, ',', '.').' lei')->description(fn (OnlinePayment $r) => $r->live_mode ? null : 'test'),
                TextColumn::make('status')->label('Stare')->badge()->formatStateUsing(fn (OnlinePayment $r) => $r->statusLabel())
                    ->color(fn (string $state) => ['paid' => 'success', 'paid_needs_review' => 'danger', 'pending' => 'warning'][$state] ?? 'gray')
                    ->description(fn (OnlinePayment $r) => $r->review_reason),
                TextColumn::make('invoice_number')->label('Factură')->placeholder('de emis')->color(fn (OnlinePayment $r) => $r->needsInvoice() ? 'warning' : null)
                    ->description(fn (OnlinePayment $r) => $r->invoice_number ? $r->invoice_date?->format('d.m.Y').' · '.$r->invoiceRecorder?->name : null)->searchable(),
                TextColumn::make('stripe_payment_intent')->label('Referință Stripe')->toggleable(isToggledHiddenByDefault: true)->copyable(),
            ])
            ->filters([
                Filter::make('to_invoice')->label('Factură de emis')->query(fn (Builder $q) => $q->whereIn('status', ['paid', 'paid_needs_review'])->whereNull('invoice_number'))->default(),
                SelectFilter::make('status')->label('Stare')->options(['paid' => 'Plătită', 'paid_needs_review' => 'Plătită, de verificat', 'pending' => 'În curs', 'expired' => 'Abandonată', 'failed' => 'Eșuată']),
                SelectFilter::make('purpose')->label('Pentru')->options([OnlinePayment::WALLET => 'Alimentare Wallet', OnlinePayment::SUBSCRIPTION => 'Abonament']),
            ])
            ->recordActions([
                Action::make('invoice')->label(fn (OnlinePayment $r) => $r->invoice_number ? 'Corectează factura' : 'Factură emisă')->icon('heroicon-o-document-text')->color('success')
                    ->visible(fn (OnlinePayment $r) => $r->isPaid())
                    ->modalHeading('Factura emisă pentru plata online')
                    ->modalDescription(fn (OnlinePayment $r) => $r->user?->name.' · '.$r->description().' · '.number_format((float) $r->amount, 2, ',', '.').' lei, încasată '.$r->paid_at?->format('d.m.Y'))
                    ->fillForm(fn (OnlinePayment $r) => ['number' => $r->invoice_number, 'date' => $r->invoice_date ?? now()])
                    ->schema([
                        TextInput::make('number')->label('Seria și numărul facturii')->required()->maxLength(60),
                        DatePicker::make('date')->label('Data facturii')->required()->native(false)->displayFormat('d.m.Y'),
                    ])
                    ->action(function (OnlinePayment $record, array $data) {
                        app(StripeCheckout::class)->recordInvoice($record, (string) $data['number'], (string) $data['date'], auth()->user());
                        Notification::make()->title('Factura a fost trecută')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOnlinePayments::route('/')];
    }
}

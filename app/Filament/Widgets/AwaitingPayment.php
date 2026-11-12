<?php

namespace App\Filament\Widgets;

use App\Enums\ContractStatus;
use App\Enums\Permission;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Contract;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AwaitingPayment extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Contracte care așteaptă plata la clinică';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can(Permission::CONTRACTS_VIEW);
    }

    public function table(Table $table): Table
    {
        return $table->query(Contract::query()->with('client')->where('status', ContractStatus::AwaitingClinic)->latest('id'))->paginated([5])
            ->emptyStateHeading('Niciun contract în așteptare')
            ->columns([
                TextColumn::make('number')->label('Contract'),
                TextColumn::make('client.name')->label('Client'),
                TextColumn::make('pet_name')->label('Animal')->description(fn (Contract $r) => $r->tier_label),
                TextColumn::make('payment_amount')->label('De încasat')->formatStateUsing(fn (Contract $r) => number_format((float) $r->payment_amount, 0, ',', '.').' lei · '.($r->period_months === 1 ? 'lunar' : $r->period_months.' luni')),
                TextColumn::make('valid_until')->label('Ofertă valabilă până la')->date('d.m.Y'),
            ])
            ->recordUrl(fn (Contract $record) => ClientResource::getUrl('view', ['record' => $record->user_id]));
    }
}

<?php

namespace App\Filament\Resources\AuditLogs;

use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AuditLogResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = AuditLog::class;

    protected static ?string $slug = 'audit-log';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Jurnal de audit';

    protected static ?string $modelLabel = 'intrare';

    protected static ?string $pluralModelLabel = 'jurnal de audit';

    protected static ?int $navigationSort = 20;

    public static function permission(): string
    {
        return Permission::AUDIT_VIEW;
    }

    protected static function writable(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('created_at')->label('Când')->dateTime('d.m.Y H:i:s')->sortable(),
            TextColumn::make('actor_name')->label('Cine')->searchable()->placeholder('sistem / vizitator'),
            TextColumn::make('action')->label('Acțiune')->badge()->color('gray')->searchable(),
            TextColumn::make('summary')->label('Detalii')->wrap()->searchable(),
            TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('action')->label('Acțiune')->options(fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all())->searchable(),
            Filter::make('period')->label('Perioadă')->columns(2)->schema([DatePicker::make('from')->label('De la'), DatePicker::make('until')->label('Până la')])
                ->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
                    ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d))),
            Filter::make('sensitive')->label('Acces la documente și exporturi')
                ->query(fn (Builder $query) => $query->whereIn('action', ['contract.viewed', 'contract.downloaded', 'consent.viewed', 'sterilization.viewed', 'sterilization.exported'])),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAuditLogs::route('/')];
    }
}

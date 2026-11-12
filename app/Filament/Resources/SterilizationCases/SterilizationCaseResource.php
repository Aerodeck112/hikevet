<?php

namespace App\Filament\Resources\SterilizationCases;

use App\Enums\Permission;
use App\Filament\Concerns\GatedByPermission;
use App\Models\SterilizationCase;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Arhiva istorica a campaniei de sterilizare: doar citire, doar pentru personalul autorizat. */
class SterilizationCaseResource extends Resource
{
    use GatedByPermission;

    protected static ?string $model = SterilizationCase::class;

    protected static ?string $slug = 'sterilization-archive';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|UnitEnum|null $navigationGroup = 'Clinică';

    protected static ?string $navigationLabel = 'Arhivă sterilizări';

    protected static ?string $modelLabel = 'dosar';

    protected static ?string $pluralModelLabel = 'arhivă sterilizări';

    protected static ?string $recordTitleAttribute = 'cod_caz';

    protected static ?int $navigationSort = 40;

    public static function permission(): string
    {
        return Permission::STERILIZATION_VIEW;
    }

    protected static function writable(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('source_id', 'desc')
            ->searchPlaceholder('Cod dosar, nr. pacient, proprietar, pacient, telefon, microcip')
            ->columns([
                TextColumn::make('cod_caz')->label('Cod dosar')->searchable()->sortable()->description(fn (SterilizationCase $r) => $r->numar_pacient ? 'nr. pacient '.$r->numar_pacient : 'nr. pacient: lipsă în sursă'),
                TextColumn::make('nume_proprietar')->label('Proprietar')->searchable()->description(fn (SterilizationCase $r) => $r->localitate),
                TextColumn::make('nume_pacient')->label('Pacient')->searchable()->description(fn (SterilizationCase $r) => trim(($r->specie ?? '').' '.($r->sex ?? ''))),
                TextColumn::make('data_interventie')->label('Intervenție')->date('d.m.Y')->sortable()->placeholder('lipsă în sursă'),
                TextColumn::make('medic_operator')->label('Medic')->placeholder('lipsă în sursă')->toggleable(),
                TextColumn::make('status_interventie')->label('Stare intervenție')->badge()->color(fn ($state) => in_array($state, ['Finalizat', 'Operat'], true) ? 'success' : 'gray')->placeholder('–'),
                TextColumn::make('reported_month')->label('Raportat în')->placeholder('–')->toggleable(),
                TextColumn::make('missing_in_source')->label('Lipsuri')->badge()->color('warning')->getStateUsing(fn (SterilizationCase $r) => $r->missing_in_source ? count($r->missing_in_source).' câmpuri' : null)->placeholder('complet'),
                TextColumn::make('numar_pacient')->label('Nr. pacient')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('telefon')->label('Telefon')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('microcip')->label('Microcip')->searchable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('period')->label('Perioada intervenției')->columns(2)
                    ->schema([DatePicker::make('from')->label('De la'), DatePicker::make('until')->label('Până la')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('data_interventie', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('data_interventie', '<=', $d))),
                SelectFilter::make('medic_operator')->label('Medic')->options(fn () => SterilizationCase::query()->whereNotNull('medic_operator')->distinct()->orderBy('medic_operator')->pluck('medic_operator', 'medic_operator')->all()),
                SelectFilter::make('localitate')->label('Localitate')->options(fn () => SterilizationCase::query()->whereNotNull('localitate')->distinct()->orderBy('localitate')->pluck('localitate', 'localitate')->all()),
                SelectFilter::make('specie')->label('Specie')->options(fn () => SterilizationCase::query()->whereNotNull('specie')->distinct()->pluck('specie', 'specie')->all()),
                SelectFilter::make('status_interventie')->label('Stare intervenție')->options(fn () => SterilizationCase::query()->whereNotNull('status_interventie')->distinct()->pluck('status_interventie', 'status_interventie')->all()),
                SelectFilter::make('status_caz')->label('Stare dosar')->options(fn () => SterilizationCase::query()->whereNotNull('status_caz')->distinct()->pluck('status_caz', 'status_caz')->all()),
                TernaryFilter::make('missing')->label('Date lipsă în sursă')->queries(true: fn (Builder $query) => $query->whereNotNull('missing_in_source'), false: fn (Builder $query) => $query->whereNull('missing_in_source')),
            ])
            ->recordUrl(fn (SterilizationCase $record) => static::getUrl('view', ['record' => $record]))
            ->recordActions([Action::make('open')->label('Dosar')->icon('heroicon-o-folder-open')->url(fn (SterilizationCase $record) => static::getUrl('view', ['record' => $record]))]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSterilizationCases::route('/'), 'view' => Pages\ViewSterilizationCase::route('/{record}')];
    }
}

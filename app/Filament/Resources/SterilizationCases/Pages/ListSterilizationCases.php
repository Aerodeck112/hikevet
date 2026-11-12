<?php

namespace App\Filament\Resources\SterilizationCases\Pages;

use App\Enums\Permission;
use App\Filament\Pages\SterilizationReports;
use App\Filament\Resources\SterilizationCases\SterilizationCaseResource;
use App\Services\Sterilization\ArchiveExporter;
use App\Support\Audit;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListSterilizationCases extends ListRecords
{
    protected static string $resource = SterilizationCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reports')->label('Raportări lunare')->icon('heroicon-o-chart-bar')->color('gray')->url(SterilizationReports::getUrl()),
            Action::make('export')->label('Exportă lista filtrată (CSV)')->icon('heroicon-o-arrow-down-tray')
                ->visible(fn () => auth()->user()->can(Permission::STERILIZATION_EXPORT))
                ->requiresConfirmation()->modalDescription('Exportul conține date personale. Operațiunea se trece în jurnalul de audit.')
                ->action(function () {
                    $query = $this->getFilteredSortedTableQuery();
                    Audit::log('sterilization.exported', null, 'Export CSV din arhiva de sterilizări: '.$query->count().' dosare');

                    return app(ArchiveExporter::class)->csv($query, 'arhiva-sterilizari-'.now()->format('Ymd-His').'.csv');
                }),
        ];
    }
}

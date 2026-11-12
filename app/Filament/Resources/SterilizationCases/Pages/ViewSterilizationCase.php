<?php

namespace App\Filament\Resources\SterilizationCases\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Consents\ConsentResource;
use App\Filament\Resources\SterilizationCases\SterilizationCaseResource;
use App\Models\SterilizationCase;
use App\Services\Sterilization\ArchiveExporter;
use App\Support\Audit;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewSterilizationCase extends ViewRecord
{
    protected static string $resource = SterilizationCaseResource::class;

    protected string $view = 'filament.sterilization.view';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        Audit::log('sterilization.viewed', $this->record, 'Dosar '.$this->record->cod_caz.' deschis');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Dosar '.$this->record->cod_caz;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('consent')->label('Consimțământ')->icon('heroicon-o-pencil-square')->color('gray')
                ->visible(fn () => $this->record->consent_id && auth()->user()->can(Permission::CONSENTS_MANAGE))
                ->url(fn () => ConsentResource::getUrl('view', ['record' => $this->record->consent_id])),
            Action::make('archive')->label('Descarcă arhiva dosarului (ZIP)')->icon('heroicon-o-archive-box-arrow-down')
                ->visible(fn () => auth()->user()->can(Permission::STERILIZATION_EXPORT))
                ->action(function () {
                    Audit::log('sterilization.exported', $this->record, 'Arhivă ZIP descărcată pentru dosarul '.$this->record->cod_caz);

                    return app(ArchiveExporter::class)->caseZip($this->record);
                }),
        ];
    }

    protected function getViewData(): array
    {
        /** @var SterilizationCase $case */
        $case = $this->record;

        return [
            'case' => $case,
            'sections' => ArchiveExporter::sections($case),
            'documents' => $case->documents()->get(),
            'appointments' => $case->appointments()->get(),
            'visit' => $case->visit_group_id ? SterilizationCase::query()->where('visit_group_id', $case->visit_group_id)->whereKeyNot($case->id)->get(['id', 'cod_caz', 'nume_pacient']) : collect(),
        ];
    }
}

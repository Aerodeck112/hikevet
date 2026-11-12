<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Services\Sterilization\ArchiveExporter;
use App\Support\Audit;
use BackedEnum;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use UnitEnum;

/** Raportari istorice pe luna: centralizator (pe specie si sex) si desfasurator, din valorile originale. */
class SterilizationReports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Clinică';

    protected static ?string $navigationLabel = 'Raportări sterilizări';

    protected static ?string $title = 'Raportări istorice sterilizări';

    protected static ?int $navigationSort = 41;

    protected string $view = 'filament.pages.sterilization-reports';

    #[Url]
    public ?string $month = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(Permission::STERILIZATION_VIEW);
    }

    public function exportCsv()
    {
        abort_unless(auth()->user()->can(Permission::STERILIZATION_EXPORT) && $this->validMonth(), 403);
        $report = app(ArchiveExporter::class)->monthlyReport($this->month);
        Audit::log('sterilization.exported', null, 'Desfășurător CSV pentru luna '.$this->month.': '.$report['total'].' intervenții');

        return app(ArchiveExporter::class)->csv(\App\Models\SterilizationCase::query()->whereKey($report['cases']->modelKeys()), 'desfasurator-'.$this->month.'.csv');
    }

    private function validMonth(): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $this->month);
    }

    protected function getViewData(): array
    {
        $exporter = app(ArchiveExporter::class);

        return [
            'months' => $exporter->finalizedByMonth(),
            'report' => $this->validMonth() ? $exporter->monthlyReport($this->month) : null,
            'canExport' => auth()->user()->can(Permission::STERILIZATION_EXPORT),
        ];
    }
}

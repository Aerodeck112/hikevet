<?php

namespace App\Filament\Resources\PriceLists\Pages;

use App\Filament\Resources\PriceLists\PriceListResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewPriceList extends ViewRecord
{
    protected static string $resource = PriceListResource::class;

    protected string $view = 'filament.pricing.view';

    public function getTitle(): string|Htmlable
    {
        return 'Lista de prețuri '.$this->record->version;
    }

    protected function getViewData(): array
    {
        return ['list' => $this->record->load('tiers.services')];
    }
}

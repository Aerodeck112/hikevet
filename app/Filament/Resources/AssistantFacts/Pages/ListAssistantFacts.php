<?php

namespace App\Filament\Resources\AssistantFacts\Pages;

use App\Filament\Resources\AssistantFacts\AssistantFactResource;
use Filament\Resources\Pages\ListRecords;

class ListAssistantFacts extends ListRecords
{
    protected static string $resource = AssistantFactResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}

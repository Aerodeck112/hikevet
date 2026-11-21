<?php

namespace App\Filament\Resources\AssistantFacts\Pages;

use App\Filament\Resources\AssistantFacts\AssistantFactResource;
use Filament\Resources\Pages\EditRecord;

class EditAssistantFact extends EditRecord
{
    protected static string $resource = AssistantFactResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

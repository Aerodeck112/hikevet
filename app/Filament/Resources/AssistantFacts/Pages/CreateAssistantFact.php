<?php

namespace App\Filament\Resources\AssistantFacts\Pages;

use App\Filament\Resources\AssistantFacts\AssistantFactResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssistantFact extends CreateRecord
{
    protected static string $resource = AssistantFactResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['created_by' => auth()->id()];
    }
}

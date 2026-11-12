<?php

namespace App\Filament\Resources\Consents\Pages;

use App\Filament\Resources\Consents\ConsentResource;
use App\Services\Consents\ConsentService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditConsent extends EditRecord
{
    protected static string $resource = ConsentResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->record->payload;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(ConsentService::class)->update($record, $data + ['client_user_id' => $record->client_id]);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return ConsentResource::getUrl('view', ['record' => $this->record]);
    }
}

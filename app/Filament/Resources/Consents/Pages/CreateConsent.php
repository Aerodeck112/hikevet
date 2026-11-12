<?php

namespace App\Filament\Resources\Consents\Pages;

use App\Filament\Resources\Consents\ConsentResource;
use App\Services\Consents\ConsentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateConsent extends CreateRecord
{
    protected static string $resource = ConsentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        if (isset($data['consent_date']) && $data['consent_date'] instanceof \DateTimeInterface) {
            $data['consent_date'] = $data['consent_date']->format('Y-m-d');
        }
        [$consent, $code] = app(ConsentService::class)->create($data, auth()->user());
        // Codul in clar se arata o singura data, pe ecranul urmator; in baza de date ramane doar amprenta lui.
        session()->flash('consent_code_'.$consent->id, $code);

        return $consent;
    }

    protected function getRedirectUrl(): string
    {
        return ConsentResource::getUrl('view', ['record' => $this->record]);
    }
}

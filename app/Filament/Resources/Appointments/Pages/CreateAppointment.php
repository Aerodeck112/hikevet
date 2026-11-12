<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Services\Booking\BookingService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;

    public function mount(): void
    {
        parent::mount();
        // Precompletare din calendar: ?doctor=ID&at=YYYY-MM-DD HH:MM
        $this->form->fill(array_filter(['team_member_id' => request()->integer('doctor') ?: null, 'starts_at' => request()->query('at')]) + ['duration_minutes' => 30]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(BookingService::class)->book($data, auth()->id(), byStaff: true);
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return AppointmentResource::getUrl('index');
    }
}

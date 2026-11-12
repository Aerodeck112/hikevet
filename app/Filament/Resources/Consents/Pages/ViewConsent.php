<?php

namespace App\Filament\Resources\Consents\Pages;

use App\Enums\ConsentStatus;
use App\Enums\Permission;
use App\Filament\Resources\Consents\ConsentResource;
use App\Services\Consents\ConsentService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewConsent extends ViewRecord
{
    protected static string $resource = ConsentResource::class;

    protected string $view = 'filament.consents.view';

    public ?string $plainCode = null;

    public string $doctorSignature = '';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->plainCode = session('consent_code_'.$this->record->id);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Consimțământ #'.$this->record->id.' · '.$this->record->owner_name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newCode')->label('Cod nou pentru tabletă')->icon('heroicon-o-key')
                ->visible(fn () => $this->record->status === ConsentStatus::Pending)->requiresConfirmation()
                ->modalDescription('Codul vechi nu va mai funcționa.')
                ->action(function () {
                    $this->plainCode = app(ConsentService::class)->regenerateCode($this->record);
                    $this->record->refresh();
                }),
            Action::make('print')->label('Tipărește / PDF')->icon('heroicon-o-printer')->color('gray')->url(fn () => route('admin.consents.print', $this->record))->openUrlInNewTab(),
            EditAction::make()->label('Corectează datele')->visible(fn () => $this->record->status === ConsentStatus::Pending),
            Action::make('supersede')->label('Emite versiune corectată')->icon('heroicon-o-document-duplicate')->color('warning')
                ->visible(fn () => $this->record->status === ConsentStatus::Signed)->requiresConfirmation()
                ->modalDescription('Documentul semnat rămâne neschimbat, pentru audit. Se creează un document nou, cu aceleași date, care trebuie corectat și semnat din nou.')
                ->action(function () {
                    [$new, $code] = app(ConsentService::class)->supersede($this->record, $this->record->payload, auth()->user());
                    session()->flash('consent_code_'.$new->id, $code);
                    $this->redirect(ConsentResource::getUrl('edit', ['record' => $new]));
                }),
            Action::make('cancel')->label('Anulează')->icon('heroicon-o-x-mark')->color('danger')
                ->visible(fn () => $this->record->status === ConsentStatus::Pending)->requiresConfirmation()
                ->action(function () {
                    app(ConsentService::class)->cancel($this->record, auth()->user());
                    $this->record->refresh();
                }),
        ];
    }

    public function saveDoctorSignature(): void
    {
        abort_unless(auth()->user()->can(Permission::CONSENTS_SIGN_AS_DOCTOR), 403);

        try {
            app(ConsentService::class)->doctorSign($this->record, $this->doctorSignature, auth()->user());
            $this->record->refresh();
            $this->doctorSignature = '';
            Notification::make()->title('Semnătura medicului a fost salvată')->success()->send();
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    protected function getViewData(): array
    {
        return [
            'consent' => $this->record,
            'p' => $this->record->payload,
            'intact' => $this->record->isSigned() ? app(ConsentService::class)->verifyIntegrity($this->record) : null,
            'canDoctorSign' => auth()->user()->can(Permission::CONSENTS_SIGN_AS_DOCTOR) && $this->record->status !== ConsentStatus::Cancelled,
            'tabletUrl' => site_url('/consimtamant-tableta/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffResource;
use App\Support\Audit;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = $this->record->roles->first()?->name;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless(array_key_exists($data['role'], StaffResource::roleOptions()), 403);
        $before = ['role' => $record->roles->first()?->name, 'is_active' => $record->is_active];

        // Un utilizator nu isi poate dezactiva propriul cont si nu isi poate schimba singur rolul.
        if ($record->is(auth()->user())) {
            $data['is_active'] = true;
            $data['role'] = $before['role'];
        }

        $record->forceFill(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'phone' => $data['phone'] ?? null, 'is_active' => (bool) $data['is_active']]);
        if (! empty($data['new_password'])) {
            $record->password = $data['new_password'];
        }
        $record->save();
        $record->syncRoles([$data['role']]);
        Audit::log('staff.updated', $record, 'Utilizator modificat', $before, ['role' => $data['role'], 'is_active' => (bool) $data['is_active']]);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return StaffResource::getUrl('index');
    }
}

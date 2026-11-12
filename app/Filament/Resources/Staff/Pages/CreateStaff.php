<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffResource;
use App\Models\User;
use App\Support\Audit;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStaff extends CreateRecord
{
    protected static string $resource = StaffResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        abort_unless(array_key_exists($data['role'], StaffResource::roleOptions()), 403);

        $user = new User(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'phone' => $data['phone'] ?? null, 'is_active' => $data['is_active'] ?? true]);
        $user->password = $data['new_password'];
        $user->email_verified_at = now();
        $user->save();
        $user->syncRoles([$data['role']]);
        Audit::log('staff.created', $user, 'Utilizator creat cu rolul '.$data['role']);

        return $user;
    }

    protected function getRedirectUrl(): string
    {
        return StaffResource::getUrl('index');
    }
}

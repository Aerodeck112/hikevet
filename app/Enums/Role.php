<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Reception = 'reception';
    case Doctor = 'doctor';
    case Editor = 'editor';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Administrator',
            self::Reception => 'Recepție',
            self::Doctor => 'Medic',
            self::Editor => 'Marketing / Editor',
            self::Client => 'Client',
        };
    }

    /** @return list<string> */
    public static function staff(): array
    {
        return [self::SuperAdmin->value, self::Admin->value, self::Reception->value, self::Doctor->value, self::Editor->value];
    }

    /** Permisiunile fiecarui rol. Super Admin primeste tot prin Gate::before. */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => Permission::all(),
            self::Admin => array_values(array_diff(Permission::all(), [Permission::PLATFORM_UPDATE])),
            self::Reception => [
                Permission::CLIENTS_VIEW, Permission::CLIENTS_MANAGE, Permission::CONTRACTS_VIEW,
                Permission::CONTRACTS_VALIDATE_PAYMENT, Permission::WALLET_VIEW, Permission::WALLET_OPERATE,
                Permission::CONSENTS_MANAGE, Permission::APPOINTMENTS_MANAGE, Permission::MESSAGES_MANAGE,
            ],
            self::Doctor => [
                Permission::CLIENTS_VIEW, Permission::CLIENTS_MANAGE, Permission::CONTRACTS_VIEW,
                Permission::CONTRACTS_VALIDATE_PAYMENT, Permission::WALLET_VIEW, Permission::WALLET_OPERATE,
                Permission::WALLET_ADJUST, Permission::TREATMENTS_MANAGE, Permission::MEDICAL_NOTES,
                Permission::CONSENTS_MANAGE, Permission::CONSENTS_SIGN_AS_DOCTOR, Permission::APPOINTMENTS_MANAGE,
                Permission::STERILIZATION_VIEW,
            ],
            self::Editor => [Permission::CMS_MANAGE, Permission::MEDIA_MANAGE, Permission::SEO_MANAGE, Permission::MESSAGES_MANAGE],
            self::Client => [],
        };
    }
}

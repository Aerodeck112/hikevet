<?php

namespace App\Enums;

/** Permisiuni granulare. Sunt constante (nu enum) pentru a putea fi folosite direct in Gate / can(). */
final class Permission
{
    public const CMS_MANAGE = 'cms.manage';
    public const MEDIA_MANAGE = 'media.manage';
    public const SEO_MANAGE = 'seo.manage';
    public const MESSAGES_MANAGE = 'messages.manage';
    public const CLIENTS_VIEW = 'clients.view';
    public const CLIENTS_MANAGE = 'clients.manage';
    public const CLIENTS_VIEW_IDENTITY = 'clients.view_identity';
    public const CONTRACTS_VIEW = 'contracts.view';
    public const CONTRACTS_VALIDATE_PAYMENT = 'contracts.validate_payment';
    public const PRICING_MANAGE = 'pricing.manage';
    public const WALLET_VIEW = 'wallet.view';
    public const WALLET_OPERATE = 'wallet.operate';
    public const WALLET_ADJUST = 'wallet.adjust';
    public const TREATMENTS_MANAGE = 'treatments.manage';
    public const MEDICAL_NOTES = 'medical.notes';
    public const CONSENTS_MANAGE = 'consents.manage';
    public const CONSENTS_SIGN_AS_DOCTOR = 'consents.sign_as_doctor';
    public const STERILIZATION_VIEW = 'sterilization.view';
    public const STERILIZATION_EXPORT = 'sterilization.export';
    public const APPOINTMENTS_MANAGE = 'appointments.manage';
    public const USERS_MANAGE = 'users.manage';
    public const SETTINGS_MANAGE = 'settings.manage';
    public const AUDIT_VIEW = 'audit.view';
    public const IMPORT_RUN = 'import.run';
    public const PLATFORM_UPDATE = 'platform.update';

    /** @return list<string> */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }

    public static function label(string $permission): string
    {
        return [
            self::CMS_MANAGE => 'Pagini, servicii, echipă, blog, întrebări frecvente',
            self::MEDIA_MANAGE => 'Bibliotecă media',
            self::SEO_MANAGE => 'SEO, redirecturi, marketing',
            self::MESSAGES_MANAGE => 'Mesaje din formularul de contact',
            self::CLIENTS_VIEW => 'Vede clienți și animale',
            self::CLIENTS_MANAGE => 'Creează și editează clienți și animale',
            self::CLIENTS_VIEW_IDENTITY => 'Vede CNP și CI nemascate',
            self::CONTRACTS_VIEW => 'Vede contracte și descarcă PDF',
            self::CONTRACTS_VALIDATE_PAYMENT => 'Confirmă plata și activează abonamente',
            self::PRICING_MANAGE => 'Liste de prețuri și șabloane de contract',
            self::WALLET_VIEW => 'Vede Wallet și puncte',
            self::WALLET_OPERATE => 'Alimentează Wallet și înregistrează plăți',
            self::WALLET_ADJUST => 'Ajustări manuale de sold',
            self::TREATMENTS_MANAGE => 'Înregistrează servicii din abonament',
            self::MEDICAL_NOTES => 'Notițe medicale interne',
            self::CONSENTS_MANAGE => 'Creează consimțăminte',
            self::CONSENTS_SIGN_AS_DOCTOR => 'Semnează ca medic',
            self::STERILIZATION_VIEW => 'Vede arhiva de sterilizări',
            self::STERILIZATION_EXPORT => 'Exportă din arhiva de sterilizări',
            self::APPOINTMENTS_MANAGE => 'Programări și orar',
            self::USERS_MANAGE => 'Utilizatori și roluri',
            self::SETTINGS_MANAGE => 'Setări clinică și integrări',
            self::AUDIT_VIEW => 'Jurnal de audit',
            self::IMPORT_RUN => 'Import date WordPress',
            self::PLATFORM_UPDATE => 'Actualizări platformă',
        ][$permission] ?? $permission;
    }
}

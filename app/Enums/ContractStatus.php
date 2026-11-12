<?php

namespace App\Enums;

enum ContractStatus: string
{
    // In codul WordPress exista doar awaiting_clinic si paid (hv_payment_status). Celelalte stari
    // sunt explicite aici pentru ca vechiul sistem le deducea din date (expirare) sau nu le avea deloc.
    case AwaitingClinic = 'awaiting_clinic';
    case Active = 'active';
    case Expired = 'expired';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case OfferLapsed = 'offer_lapsed';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingClinic => 'Așteaptă plata la clinică',
            self::Active => 'Activ',
            self::Expired => 'Expirat',
            self::Suspended => 'Suspendat',
            self::Cancelled => 'Anulat',
            self::OfferLapsed => 'Ofertă expirată',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AwaitingClinic => 'warning',
            self::Active => 'success',
            self::Expired, self::OfferLapsed => 'gray',
            self::Suspended => 'info',
            self::Cancelled => 'danger',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::AwaitingClinic => [self::Active, self::Cancelled, self::OfferLapsed],
            self::Active => [self::Active, self::Expired, self::Suspended, self::Cancelled],
            self::Expired => [self::Active],
            self::Suspended => [self::Active, self::Cancelled],
            self::OfferLapsed => [self::Active, self::Cancelled],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}

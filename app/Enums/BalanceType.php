<?php

namespace App\Enums;

/** Tipurile de sold din hv_wallet_tx.balance_type (Wallet Core 3.1.0). */
enum BalanceType: string
{
    case Subscription = 'subscription';
    case WalletPrincipal = 'wallet_principal';
    case WalletServiceBonus = 'wallet_service_bonus';
    case WalletProductBonus = 'wallet_product_bonus';
    case LegacyWallet = 'wallet';

    public function label(): string
    {
        return match ($this) {
            self::Subscription => 'Puncte abonament',
            self::WalletPrincipal => 'Sold principal Wallet',
            self::WalletServiceBonus => 'Bonus servicii',
            self::WalletProductBonus => 'Bonus produse',
            self::LegacyWallet => 'Wallet vechi (închis)',
        };
    }

    public function isPoints(): bool
    {
        return $this === self::Subscription;
    }

    /** @return list<self> */
    public static function adjustable(): array
    {
        return [self::Subscription, self::WalletPrincipal, self::WalletServiceBonus, self::WalletProductBonus];
    }
}

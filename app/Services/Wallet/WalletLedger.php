<?php

namespace App\Services\Wallet;

use App\Enums\BalanceType;
use App\Models\Pet;
use App\Models\Treatment;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Notifications\Notifier;
use App\Support\Audit;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Registrul Wallet: portare a regulilor din hikevet-client-wallet-core 3.1.0.
 *
 * Soldurile nu sunt stocate nicaieri: se calculeaza mereu ca suma a tranzactiilor.
 * Punctele de abonament si banii din Wallet sunt solduri separate; bonusurile nu sunt bani.
 */
class WalletLedger
{
    /** Cuvinte din denumire care exclud automat bonusul (wallet_bonus_exclusion_reason, WAL:858). */
    public const BONUS_EXCLUSION_KEYWORDS = ['deparazit', 'antiparazitar', 'medicament', 'laborator extern', 'analiza externa',
        'implant', 'ct', 'rmn', 'reactiv', 'material chirurgical'];

    public function __construct(private readonly Notifier $notifier) {}

    public function amount(User $user, BalanceType $type): float
    {
        return round((float) WalletTransaction::query()->where('user_id', $user->id)->where('balance_type', $type->value)->sum('amount_delta'), 2);
    }

    public function points(User $user, BalanceType $type = BalanceType::Subscription): int
    {
        return (int) WalletTransaction::query()->where('user_id', $user->id)->where('balance_type', $type->value)->sum('points_delta');
    }

    /** @return array{subscription_points:int,principal:float,service_bonus:float,product_bonus:float,service_buying_power:float,product_buying_power:float} */
    public function balances(User $user): array
    {
        $p = max(0, $this->amount($user, BalanceType::WalletPrincipal));
        $s = max(0, $this->amount($user, BalanceType::WalletServiceBonus));
        $r = max(0, $this->amount($user, BalanceType::WalletProductBonus));

        return [
            'subscription_points' => $this->points($user),
            'principal' => round($p, 2),
            'service_bonus' => round($s, 2),
            'product_bonus' => round($r, 2),
            'service_buying_power' => round($p + min($s, $p), 2),
            'product_buying_power' => round($p + min($r, $p / 2), 2),
        ];
    }

    public function hasActiveSubscription(User $user): bool
    {
        return $user->contracts()->where('status', 'active')->where('expires_at', '>', now())->exists();
    }

    public function record(User $user, string $type, BalanceType $balance, float $amountDelta = 0, int $pointsDelta = 0, string $note = '',
        string $relatedType = '', int $relatedId = 0, ?User $actor = null, ?Pet $pet = null, ?string $idempotencyKey = null, ?int $reversesId = null, ?string $actorName = null): WalletTransaction
    {
        return WalletTransaction::create([
            'user_id' => $user->id, 'type' => $type, 'balance_type' => $balance->value,
            'amount_delta' => round($amountDelta, 2), 'points_delta' => $pointsDelta, 'note' => $note ?: null,
            'related_type' => $relatedType ?: null, 'related_id' => $relatedId ?: null,
            'actor_id' => $actor?->id, 'actor_name' => $actorName ?? $actor?->name, 'pet_id' => $pet?->id,
            'idempotency_key' => $idempotencyKey, 'reverses_id' => $reversesId,
        ]);
    }

    /** Alimentare la clinica: X lei principal + X bonus servicii + X/2 bonus produse (handle_wallet_credit). */
    public function deposit(User $user, float $amount, User $actor, string $note = ''): WalletTransaction
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new DomainException('Suma depusă trebuie să fie mai mare decât zero.');
        }
        if (! $this->hasActiveSubscription($user)) {
            throw new DomainException('Wallet-ul poate fi alimentat doar cu abonament activ.');
        }

        return DB::transaction(function () use ($user, $amount, $actor, $note) {
            $productBonus = round($amount * 0.5, 2);
            $main = $this->record($user, 'wallet_deposit_principal', BalanceType::WalletPrincipal, $amount, 0,
                'Depunere client'.($note ? ' | '.$note : ''), 'wallet_credit', 0, $actor);
            $this->record($user, 'wallet_bonus_services', BalanceType::WalletServiceBonus, $amount, 0, 'Bonus 100% servicii', 'wallet_credit', $main->id, $actor);
            $this->record($user, 'wallet_bonus_products', BalanceType::WalletProductBonus, $productBonus, 0, 'Bonus 50% produse', 'wallet_credit', $main->id, $actor);

            Audit::log('wallet.deposit', $main, "Alimentare Wallet {$amount} lei", null, ['amount' => $amount], $user->id);
            $this->notifier->client($user, 'Wallet promoțional alimentat', sprintf(
                'Principal: <strong>%s lei</strong>.<br>Bonus servicii: <strong>%s lei</strong>.<br>Bonus produse: <strong>%s lei</strong>.',
                self::money($amount), self::money($amount), self::money($productBonus)), 'wallet_credit', $main->id);

            return $main;
        });
    }

    public function bonusExclusionReason(string $name): string
    {
        $n = mb_strtolower(\Illuminate\Support\Str::ascii(trim($name)));
        foreach (self::BONUS_EXCLUSION_KEYWORDS as $keyword) {
            if (str_contains($n, $keyword)) {
                return 'Categorie cu medicamente, consumabile sau cost extern';
            }
        }

        return '';
    }

    /** @return array{principal_used:float,bonus_used:float,bonus_type:BalanceType,sufficient:bool} */
    public function calculatePurchase(User $user, string $category, float $amount, bool $bonusBlocked = false): array
    {
        $category = $category === 'product' ? 'product' : 'service';
        $amount = max(0, round($amount, 2));
        $b = $this->balances($user);
        $bonusType = $category === 'product' ? BalanceType::WalletProductBonus : BalanceType::WalletServiceBonus;
        $available = $category === 'product' ? $b['product_bonus'] : $b['service_bonus'];
        $cap = $bonusBlocked ? 0 : ($category === 'product' ? round($amount / 3, 2) : round($amount / 2, 2));
        $bonus = min($available, $cap);
        $principal = round($amount - $bonus, 2);

        return ['principal_used' => $principal, 'bonus_used' => round($bonus, 2), 'bonus_type' => $bonusType,
            'sufficient' => $b['principal'] + 0.0001 >= $principal];
    }

    /** Plata unui serviciu sau produs din Wallet (handle_wallet_purchase). */
    public function purchase(User $user, string $category, string $name, float $amount, User $actor, ?Pet $pet = null, string $notes = '', bool $manualBonusBlock = false): Treatment
    {
        $name = trim($name);
        if (! in_array($category, ['service', 'product'], true) || $name === '' || $amount <= 0) {
            throw new DomainException('Date tranzacție incomplete.');
        }
        if (! $this->hasActiveSubscription($user)) {
            throw new DomainException('Este necesar abonament activ.');
        }

        return DB::transaction(function () use ($user, $category, $name, $amount, $actor, $pet, $notes, $manualBonusBlock) {
            $reason = $this->bonusExclusionReason($name);
            $blocked = $manualBonusBlock || $reason !== '';
            $calc = $this->calculatePurchase($user, $category, $amount, $blocked);

            if (! $calc['sufficient']) {
                throw new DomainException('Sold principal insuficient. Necesar: '.self::money($calc['principal_used']).' lei.');
            }

            $treatment = Treatment::create([
                'user_id' => $user->id, 'pet_id' => $pet?->id, 'pet_name' => $pet?->name, 'name' => $name,
                'price_standard' => $amount, 'points_used' => 0, 'payment_category' => $category,
                'principal_used' => $calc['principal_used'], 'bonus_used' => $calc['bonus_used'],
                'bonus_balance_type' => $calc['bonus_type']->value, 'bonus_blocked' => $blocked,
                'bonus_block_reason' => $reason ?: ($manualBonusBlock ? 'Exclus manual' : null),
                'notes' => $notes ?: null, 'actor_id' => $actor->id,
            ]);

            $note = ($category === 'product' ? 'Produs: ' : 'Serviciu: ').$name
                .($reason ? ' | Bonus exclus automat' : ($manualBonusBlock ? ' | Bonus exclus manual' : ''));

            $this->record($user, 'wallet_purchase_principal', BalanceType::WalletPrincipal, -$calc['principal_used'], 0, $note, 'wallet_purchase', $treatment->id, $actor, $pet);
            if ($calc['bonus_used'] > 0) {
                $this->record($user, $category === 'product' ? 'wallet_product_bonus_spend' : 'wallet_service_bonus_spend',
                    $calc['bonus_type'], -$calc['bonus_used'], 0, $note, 'wallet_purchase', $treatment->id, $actor, $pet);
            }

            Audit::log('wallet.purchase', $treatment, $note, null, ['amount' => $amount, 'principal' => $calc['principal_used'], 'bonus' => $calc['bonus_used']], $user->id);
            $this->notifier->client($user, 'Plată din Wallet promoțional', sprintf(
                'Valoare: <strong>%s lei</strong>.<br>Principal: <strong>%s lei</strong>.<br>Bonus: <strong>%s lei</strong>.',
                self::money($amount), self::money($calc['principal_used']), self::money($calc['bonus_used'])), 'wallet_purchase', $treatment->id);

            return $treatment;
        });
    }

    /** Serviciu inclus in abonament: consuma doar puncte de abonament (handle_add_treatment). */
    public function consumeSubscriptionService(User $user, string $name, int $points, User $actor, ?Pet $pet = null, float $unitPrice = 0, string $notes = ''): Treatment
    {
        $name = trim($name);
        if ($name === '' || $points <= 0) {
            throw new DomainException('Date tratament incomplete.');
        }
        if (str_contains(mb_strtolower(\Illuminate\Support\Str::ascii($name)), 'deparazit')) {
            throw new DomainException('Deparazitările sunt contra cost și nu sunt incluse în abonament.');
        }

        return DB::transaction(function () use ($user, $name, $points, $actor, $pet, $unitPrice, $notes) {
            if ($this->points($user) < $points) {
                throw new DomainException('Puncte abonament insuficiente.');
            }

            $treatment = Treatment::create([
                'user_id' => $user->id, 'pet_id' => $pet?->id, 'pet_name' => $pet?->name, 'name' => $name,
                'price_standard' => $unitPrice, 'points_used' => $points, 'payment_category' => 'subscription',
                'notes' => $notes ?: null, 'actor_id' => $actor->id,
            ]);

            $this->record($user, 'treatment_consume', BalanceType::Subscription, 0, -$points,
                trim(($pet ? 'Animal: '.$pet->name.' | ' : '').$name), 'treatment', $treatment->id, $actor, $pet);

            Audit::log('treatment.created', $treatment, 'Serviciu inclus în abonament: '.$name, null, ['points' => $points], $user->id);
            $this->notifier->client($user, 'Serviciu utilizat din abonament',
                'Serviciu: <strong>'.e($name).'</strong>.<br>Puncte: <strong>'.$points.'</strong>.', 'treatment', $treatment->id);

            return $treatment;
        });
    }

    /** Ajustare manuala: numele complet al angajatului este obligatoriu, soldul nu poate deveni negativ. */
    public function manualAdjust(User $user, BalanceType $balance, float $delta, string $employeeName, User $actor, string $reason = ''): WalletTransaction
    {
        if (! in_array($balance, BalanceType::adjustable(), true)) {
            throw new DomainException('Tip de sold invalid.');
        }
        if (! self::validEmployeeName($employeeName)) {
            throw new DomainException('Scrie numele și prenumele angajatului care aplică ajustarea.');
        }

        $delta = $balance->isPoints() ? (int) $delta : round($delta, 2);
        if ((float) $delta === 0.0) {
            throw new DomainException('Ajustarea nu poate fi zero.');
        }

        return DB::transaction(function () use ($user, $balance, $delta, $employeeName, $actor, $reason) {
            $available = $balance->isPoints() ? $this->points($user, $balance) : $this->amount($user, $balance);
            if ($delta < 0 && abs($delta) > $available + 0.0001) {
                throw new DomainException('Sold insuficient pentru această ajustare.');
            }

            $employeeName = trim(preg_replace('/\s+/', ' ', $employeeName));
            $note = 'Ajustare manuală '.mb_strtolower($balance->label()).' | Aplicat de: '.$employeeName.($reason ? ' | Motiv: '.$reason : '');
            $tx = $this->record($user, 'manual_balance_adjust', $balance, $balance->isPoints() ? 0 : $delta,
                $balance->isPoints() ? (int) $delta : 0, $note, 'manual_adjust', 0, $actor, null, null, null, $employeeName);

            Audit::log('wallet.manual_adjust', $tx, $note, null, ['balance' => $balance->value, 'delta' => $delta], $user->id);
            $this->notifier->client($user, 'Ajustare manuală sold', sprintf(
                'Tip sold: <strong>%s</strong>.<br>Modificare: <strong>%s %s</strong>.<br>Ajustare aplicată de: <strong>%s</strong>.%s',
                e($balance->label()), $balance->isPoints() ? (int) $delta : self::money($delta), $balance->isPoints() ? 'puncte' : 'lei',
                e($employeeName), $reason ? '<br>Motiv: '.e($reason) : ''), 'manual_adjust', $tx->id);

            return $tx;
        });
    }

    /** Stergere controlata: motiv obligatoriu, soldurile se restituie prin tranzactii inverse. */
    public function deleteTreatment(Treatment $treatment, string $reason, User $actor): void
    {
        if (trim($reason) === '') {
            throw new DomainException('Motivul ștergerii este obligatoriu.');
        }
        if ($treatment->isDeleted()) {
            throw new DomainException('Operațiunea este deja ștearsă.');
        }

        DB::transaction(function () use ($treatment, $reason, $actor) {
            $user = $treatment->client;
            $note = 'Restituire după ștergere: '.$treatment->name.' | Motiv: '.$reason;

            if ($treatment->payment_category === 'subscription') {
                $this->record($user, 'treatment_reversal', BalanceType::Subscription, 0, (int) $treatment->points_used, $note, 'treatment_delete', $treatment->id, $actor);
            } else {
                $this->record($user, 'wallet_purchase_reversal', BalanceType::WalletPrincipal, (float) $treatment->principal_used, 0, $note, 'treatment_delete', $treatment->id, $actor);
                if ((float) $treatment->bonus_used > 0) {
                    $this->record($user, 'wallet_bonus_reversal', BalanceType::from($treatment->bonus_balance_type), (float) $treatment->bonus_used, 0, $note, 'treatment_delete', $treatment->id, $actor);
                }
            }

            $treatment->forceFill(['deleted_at' => now(), 'deleted_by' => $actor->id, 'delete_reason' => $reason, 'restored_at' => null, 'restored_by' => null])->save();
            Audit::log('treatment.deleted', $treatment, $note, null, null, $user->id);
        });
    }

    /** Restaurare: operatiunea se aplica din nou doar daca exista sold suficient. */
    public function restoreTreatment(Treatment $treatment, User $actor): void
    {
        if (! $treatment->isDeleted()) {
            throw new DomainException('Operațiunea nu este ștearsă.');
        }

        DB::transaction(function () use ($treatment, $actor) {
            $user = $treatment->client;
            $note = 'Restaurare operațiune: '.$treatment->name;

            if ($treatment->payment_category === 'subscription') {
                if ($this->points($user) < (int) $treatment->points_used) {
                    throw new DomainException('Puncte insuficiente pentru restaurare.');
                }
                $this->record($user, 'treatment_restore_consume', BalanceType::Subscription, 0, -(int) $treatment->points_used, $note, 'treatment_restore', $treatment->id, $actor);
            } else {
                $bonusType = BalanceType::tryFrom((string) $treatment->bonus_balance_type) ?? BalanceType::WalletServiceBonus;
                if ($this->amount($user, BalanceType::WalletPrincipal) + 0.0001 < (float) $treatment->principal_used
                    || ((float) $treatment->bonus_used > 0 && $this->amount($user, $bonusType) + 0.0001 < (float) $treatment->bonus_used)) {
                    throw new DomainException('Sold insuficient pentru restaurare.');
                }
                $this->record($user, 'wallet_restore_consume', BalanceType::WalletPrincipal, -(float) $treatment->principal_used, 0, $note, 'treatment_restore', $treatment->id, $actor);
                if ((float) $treatment->bonus_used > 0) {
                    $this->record($user, 'wallet_bonus_restore_consume', $bonusType, -(float) $treatment->bonus_used, 0, $note, 'treatment_restore', $treatment->id, $actor);
                }
            }

            $treatment->forceFill(['deleted_at' => null, 'restored_at' => now(), 'restored_by' => $actor->id])->save();
            Audit::log('treatment.restored', $treatment, $note, null, null, $user->id);
        });
    }

    public static function validEmployeeName(string $name): bool
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        if ($name === '') {
            return false;
        }

        return count(array_filter(preg_split('/\s+/u', $name))) >= 2 && mb_strlen($name) >= 5;
    }

    public static function money(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }
}

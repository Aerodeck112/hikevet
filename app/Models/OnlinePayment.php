<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** O plata online cu cardul (Stripe Checkout) si evidenta facturii emise manual pentru ea. */
class OnlinePayment extends Model
{
    public const WALLET = 'wallet_topup';

    public const SUBSCRIPTION = 'subscription';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'live_mode' => 'boolean', 'paid_at' => 'datetime', 'invoice_date' => 'date', 'invoice_recorded_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function invoiceRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invoice_recorded_by');
    }

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'paid_needs_review'], true);
    }

    public function needsInvoice(): bool
    {
        return $this->isPaid() && ! $this->invoice_number;
    }

    public function purposeLabel(): string
    {
        return $this->purpose === self::WALLET ? 'Alimentare Wallet' : 'Abonament';
    }

    public function statusLabel(): string
    {
        return ['pending' => 'În curs', 'paid' => 'Plătită', 'paid_needs_review' => 'Plătită, de verificat', 'expired' => 'Abandonată', 'failed' => 'Eșuată'][$this->status] ?? $this->status;
    }

    public function description(): string
    {
        if ($this->purpose === self::WALLET) {
            return 'Alimentare Wallet HiKeVet';
        }
        $period = $this->months === 1 ? '1 lună' : $this->months.' luni';

        return 'Abonament HiKeVet '.($this->contract?->number ?? '').' · '.($this->contract?->pet_name ?? '').' · '.$period;
    }
}

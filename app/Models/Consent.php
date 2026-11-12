<?php

namespace App\Models;

use App\Enums\ConsentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consent extends Model
{
    protected $guarded = ["id"];

    protected $hidden = ["payload", "token", "code_hash"];

    protected function casts(): array
    {
        return [
            "status" => ConsentStatus::class,
            "payload" => "encrypted:array",
            "owner_signature" => "encrypted", "refusal_signature" => "encrypted", "doctor_signature" => "encrypted",
            "consent_date" => "date", "signed_at" => "datetime", "doctor_signed_at" => "datetime", "code_expires_at" => "datetime",
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, "client_id");
    }

    public function doctorSigner(): BelongsTo
    {
        return $this->belongsTo(User::class, "doctor_signed_by");
    }

    public function isSigned(): bool
    {
        return $this->status === ConsentStatus::Signed;
    }

    public function isMultiPatient(): bool
    {
        return count($this->payload["visit_patients"] ?? []) > 1;
    }
}

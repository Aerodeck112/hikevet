<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    protected $guarded = ["id"];

    protected $hidden = ["manage_token"];

    protected function casts(): array
    {
        return ["status" => AppointmentStatus::class, "starts_at" => "datetime", "ends_at" => "datetime",
            "cancelled_at" => "datetime", "consent_processing" => "boolean"];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, "team_member_id");
    }

    public function slots(): HasMany
    {
        return $this->hasMany(AppointmentSlot::class);
    }
}

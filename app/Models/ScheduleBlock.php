<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleBlock extends Model
{
    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["starts_at" => "datetime", "ends_at" => "datetime"];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, "team_member_id");
    }
}

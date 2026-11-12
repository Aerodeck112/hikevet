<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorNote extends Model
{
    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["deleted_at" => "datetime"];
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, "actor_id");
    }
}

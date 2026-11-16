<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegacyTable extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['columns' => 'array', 'snapshotted_at' => 'datetime'];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(LegacyRow::class);
    }
}

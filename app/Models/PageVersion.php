<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageVersion extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["snapshot" => "array", "created_at" => "datetime"];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, "created_by");
    }
}

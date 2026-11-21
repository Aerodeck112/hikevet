<?php

namespace App\Models;

use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/** O intrebare primita de asistent si raspunsul dat. Se sterg automat dupa 60 de zile. */
class AssistantLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reviewed' => 'boolean', 'created_at' => 'datetime'];
    }

    public function prunable()
    {
        return static::where('created_at', '<', now()->subDays(60));
    }
}

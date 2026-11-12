<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractTemplate extends Model
{
    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["lines" => "array", "is_active" => "boolean"];
    }

    public static function active(): self
    {
        return static::query()->where("is_active", true)->latest("id")->firstOrFail();
    }
}

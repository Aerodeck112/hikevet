<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SterilizationEmailLog extends Model
{
    public $timestamps = false;

    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["source_row" => "array", "sent_at" => "datetime"];
    }
}

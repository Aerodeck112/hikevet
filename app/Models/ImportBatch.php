<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["dry_run" => "boolean", "counts" => "array", "errors" => "array", "missing" => "array",
            "started_at" => "datetime", "finished_at" => "datetime", "rolled_back_at" => "datetime"];
    }
}

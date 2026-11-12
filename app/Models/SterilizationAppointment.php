<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SterilizationAppointment extends Model
{
    public $timestamps = false;

    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["source_row" => "array", "appointment_date" => "date"];
    }
}

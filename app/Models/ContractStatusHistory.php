<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = "contract_status_history";

    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["created_at" => "datetime"];
    }
}

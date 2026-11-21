<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use \App\Models\Concerns\Translatable;

    /** Campuri afisate in limba vizitatorului (vezi tabelul translations). */
    protected array $translatable = ['question', 'answer'];

    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["is_published" => "boolean"];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    use \App\Models\Concerns\Translatable;

    /** Campuri afisate in limba vizitatorului (vezi tabelul translations). */
    protected array $translatable = ['name'];

    protected $guarded = ["id"];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class)->orderBy("sort");
    }
}

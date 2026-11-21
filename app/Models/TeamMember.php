<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamMember extends Model
{
    use \App\Models\Concerns\Translatable;

    /** Campuri afisate in limba vizitatorului (vezi tabelul translations). */
    protected array $translatable = ['role_title', 'specializations', 'bio'];

    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["is_published" => "boolean"];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class);
    }
}

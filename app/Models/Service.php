<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["highlights" => "array", "is_published" => "boolean", "is_bookable" => "boolean"];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where("is_published", true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, "service_category_id");
    }

    public function teamMembers(): BelongsToMany
    {
        return $this->belongsToMany(TeamMember::class);
    }

    public function path(): string
    {
        return "/".$this->slug."/";
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    use \App\Models\Concerns\Translatable;

    /** Campuri afisate in limba vizitatorului (vezi tabelul translations). */
    protected array $translatable = ['title', 'lead', 'body', 'blocks', 'seo_title', 'seo_description'];

    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["blocks" => "array", "published_at" => "datetime", "noindex" => "boolean"];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where("status", "published");
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PageVersion::class)->orderByDesc("version");
    }

    public function isPublished(): bool
    {
        return $this->status === "published";
    }

    public function path(): string
    {
        return $this->slug === "home" ? "/" : "/".$this->slug."/";
    }
}

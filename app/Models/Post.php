<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use \App\Models\Concerns\Translatable;

    /** Campuri afisate in limba vizitatorului (vezi tabelul translations). */
    protected array $translatable = ['title', 'excerpt', 'body', 'seo_title', 'seo_description'];

    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["tags" => "array", "published_at" => "datetime"];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where("status", "published")->where("published_at", "<=", now());
    }

    public function path(): string
    {
        return "/".$this->slug."/";
    }
}

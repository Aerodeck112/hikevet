<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
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

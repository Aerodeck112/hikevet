<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = "media";

    protected $guarded = ["id"];

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pet extends Model
{
    use HasFactory;

    protected $guarded = ["id"];

    protected function casts(): array
    {
        return ["date_of_birth" => "date", "weight_kg" => "decimal:2", "archived_at" => "datetime"];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function speciesLabel(): string
    {
        return ["dog" => "Câine", "cat" => "Pisică"][$this->species] ?? (string) $this->species;
    }
}

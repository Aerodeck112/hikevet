<?php

namespace App\Models;

use App\Enums\Role;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'phone', 'address', 'city', 'internal_notes', 'is_active', 'must_set_password'];

    protected $hidden = ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes', 'wp_password_hash'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'marketing_opt_in_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_set_password' => 'boolean',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(Role::staff());
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->isStaff();
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class);
    }

    /** Animalele active, in ordinea adaugarii: ordinea stabileste reducerea multi-animal. */
    public function activePets(): HasMany
    {
        return $this->pets()->whereNull('archived_at')->orderBy('id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class)->latest('id');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class);
    }

    public function clientNotifications(): HasMany
    {
        return $this->hasMany(ClientNotification::class)->latest('id');
    }

    public function doctorNotes(): HasMany
    {
        return $this->hasMany(DoctorNote::class);
    }
}

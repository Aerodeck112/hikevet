<?php

namespace Tests;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\PriceListSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    /** Date de baza pentru testele de business: roluri, lista de preturi reala, disc privat fals. */
    protected function seedBusiness(): void
    {
        $this->seed([RolesSeeder::class, PriceListSeeder::class]);
        Storage::fake('private');
    }

    protected function staff(Role $role = Role::Doctor): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    protected function client(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::Client->value);

        return $user;
    }

    /** PNG valid, 1x1, folosit ca semnatura in teste. */
    protected function signature(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    }

    /** Date sintetice pentru un contract. CNP-ul este fictiv. */
    protected function contractData(array $overrides = []): array
    {
        return array_merge([
            'pet_name' => 'Rex', 'agree' => true, 'species' => 'dog', 'sex' => 'male', 'dob' => '2022-05-10', 'breed' => 'Metis',
            'breed_other' => '', 'weight' => 12.5, 'sterilized' => 'nu', 'chronic' => 'nu', 'chronic_text' => '', 'emergency' => 'nu',
            'routine' => ['anual'], 'owner_name' => 'Popescu Test', 'owner_email' => 'popescu.test@example.com', 'owner_phone' => '0700000000',
            'owner_addr' => 'Str. Exemplu nr. 1, Târgu Mureș', 'owner_cnp' => '1900101000000', 'owner_ci_serie' => 'ZZ', 'owner_ci_nr' => '000000',
            'pet_microchip' => '', 'legal' => ['contract' => true, 'annex' => true, 'terms' => true, 'guide' => true, 'privacy' => true, 'not_insurance' => true],
            'signature' => $this->signature(), 'payment_period_months' => 1, 'submission_token' => 'test-token-'.uniqid(),
        ], $overrides);
    }
}

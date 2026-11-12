<?php

namespace App\Services\Clients;

use App\Enums\Role;
use App\Models\Pet;
use App\Models\User;
use App\Services\Notifications\Notifier;
use App\Services\Subscriptions\PricingEngine;
use App\Support\Audit;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/** Conturi de client si animale: echivalentul ensure_client_user() si upsert_pet_for_user() din Wallet Core. */
class ClientAccounts
{
    public function __construct(private readonly PricingEngine $pricing, private readonly Notifier $notifier) {}

    /** @return array{0: User, 1: bool} utilizatorul si daca a fost creat acum */
    public function findOrCreate(string $name, string $email, ?string $phone = null, ?string $address = null, bool $sendActivation = true): array
    {
        $email = mb_strtolower(trim($email));
        $user = User::query()->where('email', $email)->first();
        $created = false;

        if (! $user) {
            $user = new User(['name' => $name ?: Str::before($email, '@'), 'email' => $email, 'phone' => $phone, 'address' => $address]);
            $user->password = Str::password(32);
            $user->must_set_password = true;
            $user->save();
            $created = true;
        }

        if (! $user->hasRole(Role::Client->value)) {
            $user->assignRole(Role::Client->value);
        }

        if ($created && $sendActivation) {
            $this->sendActivation($user);
        }

        return [$user, $created];
    }

    /** Link de setare a parolei. Nu se trimite si nu se stocheaza nicio parola in clar. */
    public function sendActivation(User $user): string
    {
        $token = Password::broker()->createToken($user);
        $url = route('password.reset', ['token' => $token, 'email' => $user->email]);

        $this->notifier->client($user, 'Activează contul HiKeVet',
            'Contul tău HiKeVet a fost creat.<br>Setează parola aici: <a href="'.e($url).'">'.e($url).'</a><br>Linkul este valabil 60 de minute.',
            'activation', $user->id);

        return $url;
    }

    /**
     * Adauga sau actualizeaza un animal. Cheia de potrivire este cea din sursa:
     * microcipul, iar in lipsa lui numele + specia (fara majuscule).
     */
    public function upsertPet(User $user, array $data): Pet
    {
        $microchip = trim((string) ($data['microchip'] ?? ''));
        $key = fn (?string $chip, ?string $name, ?string $species) => $chip !== null && trim($chip) !== ''
            ? trim($chip)
            : mb_strtolower(trim((string) $name)).'|'.mb_strtolower(trim((string) $species));

        $wanted = $key($microchip, $data['name'] ?? '', $data['species'] ?? '');
        $pet = $user->activePets()->get()->first(fn (Pet $p) => $key($p->microchip, $p->name, $p->species) === $wanted) ?? new Pet(['user_id' => $user->id]);

        $tier = $data['tier_label'] ?? null;
        if (! $tier && isset($data['species'], $data['weight_kg'])) {
            $tier = $this->pricing->tierFor($data['species'], (float) $data['weight_kg'])?->label;
        }

        $pet->fill([
            'name' => $data['name'], 'species' => $data['species'], 'sex' => $data['sex'] ?? $pet->sex,
            'date_of_birth' => $data['date_of_birth'] ?? $pet->date_of_birth, 'breed' => $data['breed'] ?? $pet->breed,
            'weight_kg' => $data['weight_kg'] ?? $pet->weight_kg, 'microchip' => $microchip ?: $pet->microchip,
            'sterilized' => $data['sterilized'] ?? $pet->sterilized, 'tier_label' => $tier ?? $pet->tier_label,
        ]);
        $isNew = ! $pet->exists;
        $pet->save();

        Audit::log($isNew ? 'pet.created' : 'pet.updated', $pet, ($isNew ? 'Animal adăugat: ' : 'Animal actualizat: ').$pet->name, null, null, $user->id);

        return $pet;
    }

    public function archivePet(Pet $pet, User $actor, string $reason): void
    {
        $pet->forceFill(['archived_at' => now(), 'archived_by' => $actor->id, 'archive_reason' => $reason])->save();
        Audit::log('pet.archived', $pet, 'Animal arhivat: '.$pet->name.' | Motiv: '.$reason, null, null, $pet->user_id);
    }

    public function restorePet(Pet $pet): void
    {
        $pet->forceFill(['archived_at' => null, 'archived_by' => null, 'archive_reason' => null])->save();
        Audit::log('pet.restored', $pet, 'Animal restaurat: '.$pet->name, null, null, $pet->user_id);
    }
}

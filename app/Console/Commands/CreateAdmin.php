<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'hikevet:create-admin {email} {name} {--role=super_admin}';

    protected $description = 'Creează un cont de personal din linia de comandă (alternativă la instalatorul web). Parola se cere interactiv.';

    public function handle(): int
    {
        $role = Role::tryFrom((string) $this->option('role'));
        if (! $role || $role === Role::Client) {
            $this->error('Rol necunoscut.');

            return self::FAILURE;
        }
        $password = (string) $this->secret('Parolă (minimum 12 caractere, litere mari și mici, cifre, simbol)');
        $validator = Validator::make(['email' => $this->argument('email'), 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()]]);
        if ($validator->fails()) {
            $this->error(implode(' ', $validator->errors()->all()));

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => mb_strtolower($this->argument('email'))]);
        $user->forceFill(['name' => $this->argument('name'), 'password' => $password, 'is_active' => true, 'email_verified_at' => now()])->save();
        $user->syncRoles([$role->value]);
        $this->info('Cont creat: '.$user->email.' ('.$role->label().')');

        return self::SUCCESS;
    }
}

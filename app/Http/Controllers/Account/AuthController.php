<?php

namespace App\Http\Controllers\Account;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\WordPressPasswordVerifier;
use App\Services\Seo\Seo;
use App\Support\Audit;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function access(Seo $seo)
    {
        if (Auth::check()) {
            return redirect()->away(site_url('/panou-cont/'));
        }
        $seo->page('Acces cont');
        $seo->noindex = true;

        return view('account.access', ['seo' => $seo]);
    }

    public function login(Request $request, WordPressPasswordVerifier $wp)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']], [], ['email' => 'emailul', 'password' => 'parola']);
        $user = User::query()->where('email', mb_strtolower(trim($data['email'])))->first();
        $ok = false;

        if ($user && $user->is_active) {
            if (Hash::check($data['password'], $user->password)) {
                $ok = true;
            } elseif ($user->wp_password_hash && $wp->check($data['password'], $user->wp_password_hash)) {
                // Cont migrat din WordPress: prima autentificare reusita trece parola pe algoritmul nou.
                $user->forceFill(['password' => $data['password'], 'wp_password_hash' => null, 'must_set_password' => false])->save();
                Audit::log('auth.wp_rehash', $user, 'Parolă WordPress convertită la primul login', null, null, $user->id);
                $ok = true;
            }
        }

        if (! $ok) {
            throw ValidationException::withMessages(['email' => 'Emailul sau parola nu sunt corecte.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended($user->isStaff() ? site_url('/admin') : site_url('/panou-cont/'));
    }

    public function register(Request $request)
    {
        if ($request->filled('website')) {
            return redirect()->away(site_url('/acces-cont/'));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'reg_email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'reg_password' => ['required', 'confirmed', PasswordRule::defaults()],
            'terms' => ['accepted'],
        ], ['terms.accepted' => 'Acceptă termenii și politica de confidențialitate.'], ['name' => 'numele', 'reg_email' => 'emailul', 'reg_password' => 'parola']);

        $email = mb_strtolower(trim($data['reg_email']));

        // Acelasi raspuns indiferent daca emailul exista deja, ca sa nu poata fi enumerate conturile.
        if (User::query()->where('email', $email)->exists()) {
            Password::broker()->sendResetLink(['email' => $email]);

            return redirect()->away(site_url('/acces-cont/'))->with('status', 'Dacă emailul nu avea deja un cont, contul a fost creat. Verifică emailul pentru pașii următori.');
        }

        $user = new User(['name' => $data['name'], 'email' => $email, 'phone' => $data['phone'] ?? null]);
        $user->password = $data['reg_password'];
        $user->save();
        $user->assignRole(Role::Client->value);
        Audit::log('client.registered', $user, 'Cont creat din pagina Acces cont', null, null, $user->id);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->away(site_url('/panou-cont/'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->away(site_url('/'));
    }

    public function forgot(Seo $seo)
    {
        $seo->page('Parolă uitată');
        $seo->noindex = true;

        return view('account.forgot', ['seo' => $seo]);
    }

    public function sendReset(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::broker()->sendResetLink(['email' => mb_strtolower(trim($request->input('email')))]);

        return back()->with('status', 'Dacă există un cont cu acest email, am trimis linkul pentru setarea parolei.');
    }

    public function reset(Request $request, Seo $seo, string $token)
    {
        $seo->page('Setează parola');
        $seo->noindex = true;

        return view('account.reset', ['seo' => $seo, 'token' => $token, 'email' => $request->query('email')]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'token' => ['required'], 'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ], [], ['password' => 'parola']);

        $status = Password::broker()->reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60), 'must_set_password' => false, 'wp_password_hash' => null,
                'email_verified_at' => $user->email_verified_at ?? now()])->save();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Linkul a expirat sau nu este valid. Cere un link nou.']);
        }

        return redirect()->away(site_url('/acces-cont/'))->with('status', 'Parola a fost setată. Te poți autentifica.');
    }
}

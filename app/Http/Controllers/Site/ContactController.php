<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\Notifications\Notifier;
use App\Support\Settings;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request, Notifier $notifier)
    {
        // Camp capcana si timp minim de completare: robotii primesc acelasi raspuns ca oamenii.
        $isBot = $request->filled('website') || (time() - (int) $request->input('ts', 0)) < 3;

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Avem nevoie de acordul tău pentru a-ți putea răspunde.'], [
            'name' => 'numele', 'email' => 'emailul', 'phone' => 'telefonul', 'message' => 'mesajul',
        ]);

        if (! $isBot) {
            $message = ContactMessage::create([
                'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
                'message' => $data['message'], 'consent_processing' => true,
            ]);

            $notifier->send(Settings::emails('mail.clinic_recipients'), 'Mesaj nou din formularul de contact',
                '<p><strong>'.e($data['name']).'</strong> ('.e($data['email']).($data['phone'] ?? '' ? ', '.e($data['phone']) : '').')</p><p>'.nl2br(e($data['message'])).'</p>',
                'contact', 'contact_message', $message->id);
            $notifier->send([$data['email']], 'Am primit mesajul tău',
                '<p>Bună, '.e($data['name']).'!</p><p>Am primit mesajul tău și îți răspundem cât mai curând. Pentru urgențe, sună la '.e(Settings::get('clinic.phone')).'.</p>',
                'contact_confirmation', 'contact_message', $message->id);
        }

        return redirect()->away(site_url('/hikevet-contact/#formular'))->with('contact_sent', true);
    }
}

<?php

namespace App\Services\Notifications;

use App\Mail\GenericNotification;
use App\Models\ClientNotification;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Notificari catre client (istoric in cont + email) si catre clinica, cu jurnal de livrare.
 * Echivalentul add_notification() si send_logged_mail() din Wallet Core. Un email esuat nu
 * opreste niciodata operatiunea care l-a declansat.
 */
class Notifier
{
    public function client(User $user, string $subject, string $html, string $relatedType = '', int $relatedId = 0, bool $sendEmail = true, array $attachments = []): ClientNotification
    {
        $notification = ClientNotification::create([
            'user_id' => $user->id, 'subject' => $subject, 'message' => $html,
            'channel' => $sendEmail ? 'email' : 'account', 'related_type' => $relatedType ?: null, 'related_id' => $relatedId ?: null,
        ]);

        if ($sendEmail) {
            $this->send([$user->email], $subject, $html, $relatedType ?: 'client', $relatedType, $relatedId, $user->id, $attachments);
        }

        return $notification;
    }

    /** @param list<string> $recipients */
    public function send(array $recipients, string $subject, string $html, string $context, string $relatedType = '', int $relatedId = 0, ?int $userId = null, array $attachments = []): bool
    {
        $recipients = array_values(array_unique(array_filter($recipients)));
        if ($recipients === []) {
            return false;
        }

        $status = 'sent';
        $error = null;

        try {
            Mail::to($recipients)->send(new GenericNotification($subject, $html, $attachments));
        } catch (Throwable $e) {
            $status = 'failed';
            $error = mb_substr($e->getMessage(), 0, 1000);
            report($e);
        }

        EmailLog::create([
            'user_id' => $userId, 'to_email' => implode(', ', $recipients), 'subject' => mb_substr($subject, 0, 250),
            'context' => $context, 'status' => $status, 'error_message' => $error,
            'related_type' => $relatedType ?: null, 'related_id' => $relatedId ?: null,
        ]);

        return $status === 'sent';
    }
}

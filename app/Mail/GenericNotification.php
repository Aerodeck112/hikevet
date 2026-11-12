<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GenericNotification extends Mailable
{
    use Queueable, SerializesModels;

    /** @param list<array{path:string,name:string,mime:string}> $files */
    public function __construct(public string $subjectLine, public string $bodyHtml, public array $files = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.notification', with: ['subjectLine' => $this->subjectLine, 'html' => $this->bodyHtml]);
    }

    public function attachments(): array
    {
        return array_map(fn ($f) => Attachment::fromPath($f['path'])->as($f['name'])->withMime($f['mime']), $this->files);
    }
}

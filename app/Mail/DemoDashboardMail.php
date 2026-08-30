<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A throwaway mailable with no real content, meant to be fired a few times
 * from `php artisan demo:traffic` so the Mail tab has something to show
 * while testing the dashboard -- not used anywhere in the real app flow.
 */
class DemoDashboardMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(private readonly int $sequence = 1) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Demo mail #{$this->sequence}",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "<p>Demo mail #{$this->sequence}, sent to populate the dashboard's Mail tab.</p>",
        );
    }
}

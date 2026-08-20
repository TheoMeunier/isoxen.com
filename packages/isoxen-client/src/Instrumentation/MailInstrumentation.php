<?php

namespace Isoxen\Client\Instrumentation;

use Illuminate\Mail\Events\MessageSent;
use Isoxen\Client\Facades\Tracer;
use Isoxen\Client\SpanType;
use Symfony\Component\Mime\Address;

/**
 * Ported from isoxen's original bespoke sensor — the package this client
 * was forked from has no mail instrumentation at all.
 *
 * Only `MessageSent` is listened to, not `MessageSending`: a message that
 * never leaves is reported as an exception by the mailer anyway, and
 * recording both would double every mail in the timeline.
 */
class MailInstrumentation implements Instrumentation
{
    public function register(array $options): void
    {
        app('events')->listen(MessageSent::class, [$this, 'record']);
    }

    public function record(MessageSent $event): void
    {
        $message = $event->message;

        Tracer::newSpan($message->getSubject() ?: 'mail sent')
            ->setAttribute(SpanType::ATTRIBUTE, SpanType::Mail->value)
            ->setAttribute('mail.subject', $message->getSubject())
            ->setAttribute('mail.to', $this->addresses($message->getTo()))
            ->setAttribute('mail.cc', $this->addresses($message->getCc()))
            ->setAttribute('mail.bcc', $this->addresses($message->getBcc()))
            ->setAttribute('mail.from', $this->addresses($message->getFrom()))
            ->setAttribute('mail.mailer', $event->data['mailer'] ?? null)
            ->start()
            ->end();
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, string>|null
     */
    private function addresses(array $addresses): ?array
    {
        if ($addresses === []) {
            return null;
        }

        return array_map(fn (Address $address): string => $address->getAddress(), $addresses);
    }
}

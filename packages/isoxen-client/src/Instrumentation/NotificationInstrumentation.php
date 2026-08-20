<?php

namespace Isoxen\Client\Instrumentation;

use Illuminate\Notifications\Events\NotificationSent;
use Isoxen\Client\Facades\Tracer;
use Isoxen\Client\SpanType;

/**
 * Ported from isoxen's original bespoke sensor — the package this client
 * was forked from has no notification instrumentation at all.
 */
class NotificationInstrumentation implements Instrumentation
{
    public function register(array $options): void
    {
        app('events')->listen(NotificationSent::class, [$this, 'record']);
    }

    public function record(NotificationSent $event): void
    {
        Tracer::newSpan(class_basename($event->notification))
            ->setAttribute(SpanType::ATTRIBUTE, SpanType::Notification->value)
            ->setAttribute('notification.class', $event->notification::class)
            ->setAttribute('notification.channel', $event->channel)
            ->setAttribute('notification.notifiable', $event->notifiable::class)
            ->setAttribute('notification.notifiable_id', $this->notifiableId($event->notifiable))
            ->start()
            ->end();
    }

    private function notifiableId(object $notifiable): int|string|null
    {
        return method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null;
    }
}

<?php

namespace Isoxen\Client\Instrumentation;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Isoxen\Client\Facades\Tracer;
use Isoxen\Client\SpanType;

/**
 * Ported from isoxen's original bespoke sensor, kept alongside (not instead
 * of) {@see \Isoxen\Client\Support\UserContextResolver}: the resolver tags
 * every span/log with the authenticated user's id automatically, while this
 * records explicit login/logout events as their own spans, which is what
 * the Users tab actually reads from.
 *
 * Only the identifier is sent by default: names and email addresses are
 * personal data, and the monitored application shouldn't leak them to
 * isoxen without deciding to.
 */
class UserInstrumentation implements Instrumentation
{
    public function register(array $options): void
    {
        app('events')->listen(Authenticated::class, [$this, 'authenticated']);
        app('events')->listen(Login::class, [$this, 'login']);
        app('events')->listen(Logout::class, [$this, 'logout']);
    }

    public function authenticated(Authenticated $event): void
    {
        Tracer::activeSpan()->setAttribute('enduser.id', (string) $event->user->getAuthIdentifier());
    }

    public function login(Login $event): void
    {
        $this->record('login', (string) $event->user->getAuthIdentifier(), $event->guard);
    }

    public function logout(Logout $event): void
    {
        $this->record('logout', $event->user === null ? null : (string) $event->user->getAuthIdentifier(), $event->guard);
    }

    private function record(string $operation, ?string $userId, ?string $guard): void
    {
        Tracer::newSpan("user {$operation}")
            ->setAttribute(SpanType::ATTRIBUTE, SpanType::User->value)
            ->setAttribute('isoxen.user.operation', $operation)
            ->setAttribute('enduser.id', $userId)
            ->setAttribute('isoxen.user.guard', $guard)
            ->start()
            ->end();
    }
}

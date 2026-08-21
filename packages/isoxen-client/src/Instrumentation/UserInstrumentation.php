<?php

namespace Isoxen\Client\Instrumentation;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Isoxen\Client\Facades\OpenTelemetry;
use Isoxen\Client\Facades\Tracer;
use Isoxen\Client\SpanType;

/**
 * Ported from isoxen's original bespoke sensor, kept alongside (not instead
 * of) {@see \Isoxen\Client\Support\UserContextResolver}: the resolver tags
 * every span/log with the authenticated user's id automatically, while this
 * records explicit login/logout events as their own spans, which is what
 * the Users tab actually reads from.
 *
 * `enduser.id` is always set directly -- it's the identifier the Users tab
 * groups by, and it doesn't depend on the resolver being configured. On top
 * of that, when `isoxen.user_context` is enabled, the span also picks up
 * whatever {@see \Isoxen\Client\Support\UserContextResolver} resolves for
 * this user -- nothing beyond the id by default, but the application can
 * widen that (name, email, ...) via
 * `Isoxen\Client\Facades\OpenTelemetry::user(fn ($user) => [...])`.
 * Same gate, same resolver as request spans and logs: a login event isn't a
 * loophole around the "no PII unless you opt in" rule those already follow.
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
        $this->record('login', $event->user, $event->guard);
    }

    public function logout(Logout $event): void
    {
        $this->record('logout', $event->user, $event->guard);
    }

    private function record(string $operation, ?Authenticatable $user, ?string $guard): void
    {
        $span = Tracer::newSpan("user {$operation}")
            ->setAttribute(SpanType::ATTRIBUTE, SpanType::User->value)
            ->setAttribute('isoxen.user.operation', $operation)
            ->setAttribute('enduser.id', $user === null ? null : (string) $user->getAuthIdentifier())
            ->setAttribute('isoxen.user.guard', $guard);

        if ($user !== null && config('isoxen.user_context') === true) {
            $span->setAttributes(OpenTelemetry::collectUserContext($user));
        }

        $span->start()->end();
    }
}

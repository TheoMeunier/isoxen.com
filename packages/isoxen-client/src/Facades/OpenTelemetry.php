<?php

namespace Isoxen\Client\Facades;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Isoxen\Client\Tracer tracer()
 * @method static \Isoxen\Client\Meter meter()
 * @method static \Isoxen\Client\Logger logger()
 * @method static void user(\Closure $resolver)
 * @method static array<non-empty-string, bool|int|float|string|array|null> collectUserContext(Authenticatable $user)
 */
class OpenTelemetry extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Isoxen\Client\OpenTelemetry::class;
    }
}

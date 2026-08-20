<?php

namespace Isoxen\Client;

use Illuminate\Support\ServiceProvider;
use Isoxen\Client\Instrumentation\Instrumentation;

class InstrumentationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (config('isoxen.instrumentation') as $key => $options) {
            if ($options === false) {
                continue;
            }

            if (is_array($options) && ! ($options['enabled'] ?? true)) {
                continue;
            }

            $instrumentation = $this->app->make($key);

            if ($instrumentation instanceof Instrumentation) {
                $instrumentation->register(is_array($options) ? $options : []);
            }
        }
    }
}

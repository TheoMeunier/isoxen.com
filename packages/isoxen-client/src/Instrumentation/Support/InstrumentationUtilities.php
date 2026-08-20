<?php

namespace Isoxen\Client\Instrumentation\Support;

trait InstrumentationUtilities
{
    /**
     * @template  T
     *
     * @param  class-string<T>|string  $name
     * @param  \Closure(T): void  $callback
     */
    protected function callAfterResolving(string $name, \Closure $callback): void
    {
        if (app()->resolved($name)) {
            $callback(app()->make($name));
        } else {
            app()->afterResolving($name, $callback);
        }
    }
}

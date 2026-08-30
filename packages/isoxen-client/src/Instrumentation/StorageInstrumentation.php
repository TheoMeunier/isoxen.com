<?php

namespace Isoxen\Client\Instrumentation;

use Isoxen\Client\Instrumentation\Support\Storage\TracingFilesystemManager;

/**
 * Counts filesystem reads/writes for the active request by replacing the
 * `filesystem` container binding with {@see TracingFilesystemManager} — a
 * subclass of Laravel's own `FilesystemManager` that wraps every disk it
 * resolves, rather than reaching into it with reflection.
 *
 * Off by default (see `sensors.storage` in config/isoxen.php): this is the
 * one sensor that sits on the same interface the monitored application
 * uses to read and write its own files, so it is opt-in until you've
 * verified it against your own disks and drivers.
 */
class StorageInstrumentation implements Instrumentation
{
    public function register(array $options): void
    {
        app()->extend('filesystem', fn () => new TracingFilesystemManager(app()));
    }
}

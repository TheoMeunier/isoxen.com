<?php

declare(strict_types=1);

namespace Isoxen\Client\Instrumentation\Support\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;

/**
 * A drop-in replacement for the `filesystem` container binding that wraps
 * every disk it hands out in a {@see TracingFilesystemDecorator}.
 *
 * Deliberately does not touch {@see FilesystemManager}'s internals (no
 * reflection, no reaching into its protected `$disks` cache or driver
 * creators) — it only re-wraps what the parent class already returns from
 * `disk()`/`build()`/`cloud()`, so every existing driver (local, s3, ftp,
 * sftp, custom `Storage::extend()` drivers) keeps working unmodified.
 *
 * Wrapping happens on the manager's own cache boundary rather than every
 * call: `disk($name)`/`cloud()` keep one decorator per disk name for the
 * life of the manager, and `build()` — already documented as "on-demand",
 * uncached even upstream — gets a fresh one each time, matching upstream's
 * own behaviour for that method.
 */
class TracingFilesystemManager extends FilesystemManager
{
    /** @var array<string, TracingFilesystemDecorator> */
    private array $wrappedDisks = [];

    public function disk($name = null)
    {
        $key = $name ? (string) $name : $this->getDefaultDriver();

        return $this->wrappedDisks[$key] ??= $this->wrap(parent::disk($name));
    }

    public function build($config)
    {
        return $this->wrap(parent::build($config));
    }

    public function cloud()
    {
        $key = $this->getDefaultCloudDriver();

        return $this->wrappedDisks[$key] ??= $this->wrap(parent::cloud());
    }

    private function wrap(Filesystem $disk): Filesystem
    {
        return $disk instanceof TracingFilesystemDecorator
            ? $disk
            : new TracingFilesystemDecorator($disk);
    }
}

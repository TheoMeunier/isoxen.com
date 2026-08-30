<?php

declare(strict_types=1);

namespace Isoxen\Client\Instrumentation\Support\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Isoxen\Client\Facades\Tracer;
use Isoxen\Client\Support\RequestActivityCounters;

/**
 * Wraps a resolved disk and counts reads/writes against the active
 * request, without touching how the disk itself works.
 *
 * Only the {@see Filesystem} contract's own methods are counted — every
 * other call (temporaryUrl(), url(), driver-specific extras, macros) is
 * forwarded untouched via __call so wrapping a disk never removes
 * functionality the application already relies on; those calls simply
 * aren't reflected in the "Storage" count.
 */
class TracingFilesystemDecorator implements Filesystem
{
    public function __construct(private readonly Filesystem $inner) {}

    private function recordRead(): void
    {
        if (Tracer::traceStarted()) {
            RequestActivityCounters::increment('storage_reads');
        }
    }

    private function recordWrite(): void
    {
        if (Tracer::traceStarted()) {
            RequestActivityCounters::increment('storage_writes');
        }
    }

    public function path($path)
    {
        return $this->inner->path($path);
    }

    public function exists($path)
    {
        $this->recordRead();

        return $this->inner->exists($path);
    }

    public function get($path)
    {
        $this->recordRead();

        return $this->inner->get($path);
    }

    public function readStream($path)
    {
        $this->recordRead();

        return $this->inner->readStream($path);
    }

    public function put($path, $contents, $options = [])
    {
        $this->recordWrite();

        return $this->inner->put($path, $contents, $options);
    }

    public function putFile($path, $file = null, $options = [])
    {
        $this->recordWrite();

        return $this->inner->putFile($path, $file, $options);
    }

    public function putFileAs($path, $file, $name = null, $options = [])
    {
        $this->recordWrite();

        return $this->inner->putFileAs($path, $file, $name, $options);
    }

    public function writeStream($path, $resource, array $options = [])
    {
        $this->recordWrite();

        return $this->inner->writeStream($path, $resource, $options);
    }

    public function getVisibility($path)
    {
        $this->recordRead();

        return $this->inner->getVisibility($path);
    }

    public function setVisibility($path, $visibility)
    {
        $this->recordWrite();

        return $this->inner->setVisibility($path, $visibility);
    }

    public function prepend($path, $data)
    {
        $this->recordWrite();

        return $this->inner->prepend($path, $data);
    }

    public function append($path, $data)
    {
        $this->recordWrite();

        return $this->inner->append($path, $data);
    }

    public function delete($paths)
    {
        $this->recordWrite();

        return $this->inner->delete($paths);
    }

    public function copy($from, $to)
    {
        $this->recordWrite();

        return $this->inner->copy($from, $to);
    }

    public function move($from, $to)
    {
        $this->recordWrite();

        return $this->inner->move($from, $to);
    }

    public function size($path)
    {
        $this->recordRead();

        return $this->inner->size($path);
    }

    public function lastModified($path)
    {
        $this->recordRead();

        return $this->inner->lastModified($path);
    }

    public function files($directory = null, $recursive = false)
    {
        $this->recordRead();

        return $this->inner->files($directory, $recursive);
    }

    public function allFiles($directory = null)
    {
        $this->recordRead();

        return $this->inner->allFiles($directory);
    }

    public function directories($directory = null, $recursive = false)
    {
        $this->recordRead();

        return $this->inner->directories($directory, $recursive);
    }

    public function allDirectories($directory = null)
    {
        $this->recordRead();

        return $this->inner->allDirectories($directory);
    }

    public function makeDirectory($path)
    {
        $this->recordWrite();

        return $this->inner->makeDirectory($path);
    }

    public function deleteDirectory($directory)
    {
        $this->recordWrite();

        return $this->inner->deleteDirectory($directory);
    }

    /**
     * Forwards anything outside the Filesystem contract straight to the
     * wrapped disk — temporaryUrl(), url(), and driver-specific extras
     * that LocalFilesystemAdapter/AwsS3Adapter expose via their own
     * __call to Flysystem. Not counted, since it's not part of the
     * read/write contract this decorator tracks.
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->inner->{$method}(...$arguments);
    }
}

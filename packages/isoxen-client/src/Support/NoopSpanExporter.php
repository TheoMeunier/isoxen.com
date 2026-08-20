<?php

namespace Isoxen\Client\Support;

use OpenTelemetry\SDK\Trace\Behavior\SpanExporterTrait;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;

class NoopSpanExporter implements SpanExporterInterface
{
    use SpanExporterTrait;

    #[\Override]
    protected function doExport(iterable $spans): bool
    {
        return true;
    }
}

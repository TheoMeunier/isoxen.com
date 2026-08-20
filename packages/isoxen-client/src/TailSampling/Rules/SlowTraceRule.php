<?php

namespace Isoxen\Client\TailSampling\Rules;

use Isoxen\Client\TailSampling\SamplingResult;
use Isoxen\Client\TailSampling\TailSamplingRuleInterface;
use Isoxen\Client\TailSampling\TraceBuffer;

final class SlowTraceRule implements TailSamplingRuleInterface
{
    private int $thresholdMs = 2000;

    public function initialize(array $options): void
    {
        $this->thresholdMs = $options['threshold_ms'] ?? 2000;
    }

    public function evaluate(TraceBuffer $trace): SamplingResult
    {
        if ($trace->getTraceDurationMs() >= $this->thresholdMs) {
            return SamplingResult::Keep;
        }

        return SamplingResult::Forward;
    }
}

<?php

namespace Isoxen\Client\TailSampling\Rules;

use Isoxen\Client\TailSampling\SamplingResult;
use Isoxen\Client\TailSampling\TailSamplingRuleInterface;
use Isoxen\Client\TailSampling\TraceBuffer;
use OpenTelemetry\API\Trace\StatusCode as ApiStatusCode;

final class ErrorsRule implements TailSamplingRuleInterface
{
    public function initialize(array $options): void {}

    public function evaluate(TraceBuffer $trace): SamplingResult
    {
        foreach ($trace->getSpans() as $span) {
            $spanData = $span->toSpanData();

            if ($spanData->getStatus()->getCode() === ApiStatusCode::STATUS_ERROR) {
                return SamplingResult::Keep;
            }
        }

        return SamplingResult::Forward;
    }
}

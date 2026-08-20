<?php

namespace Isoxen\Client\Support;

use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SemConv\Attributes\ServiceAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\ServiceIncubatingAttributes;

/**
 * @internal
 */
class ResourceBuilder
{
    public static function build(): ResourceInfo
    {
        return ResourceInfoFactory::defaultResource()->merge(
            ResourceInfo::create(Attributes::create([
                ServiceAttributes::SERVICE_NAME => config('isoxen.service_name'),
                ServiceIncubatingAttributes::SERVICE_INSTANCE_ID => config('isoxen.service_instance_id'),
                ...config('isoxen.resource_attributes', []),
            ]))
        );
    }
}

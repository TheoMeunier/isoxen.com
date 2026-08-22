<?php

declare(strict_types=1);

namespace Isoxen\Client;

/**
 * The categories isoxen groups incoming spans by, exposed on each span as
 * the `isoxen.type` attribute.
 *
 * These values are the contract between this client and the isoxen server:
 * they must match `App\Watch\Ingestion\Support\ObservabilityCategories` on
 * the server side, which uses them to drive a project's sidebar.
 *
 * Redis, View, Livewire and Scout are new categories this client can now
 * produce (keepsuit's instrumentation for them, adopted during the fork)
 * that the server doesn't have a sidebar tab for yet — spans still carry
 * the type and are stored, just uncategorized in the UI until
 * ObservabilityCategories is extended to know about them.
 */
enum SpanType: string
{
    case Request         = 'request';
    case Query           = 'query';
    case Job             = 'job';
    case Command         = 'command';
    case ScheduledTask   = 'scheduled_task';
    case Exception       = 'exception';
    case Notification    = 'notification';
    case Mail            = 'mail';
    case Cache           = 'cache';
    case OutgoingRequest = 'outgoing_request';
    case User            = 'user';
    case Redis           = 'redis';
    case View            = 'view';
    case Livewire        = 'livewire';
    case Scout           = 'scout';

    public const ATTRIBUTE = 'isoxen.type';
}

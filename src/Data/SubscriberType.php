<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingAnalyser\Data;

enum SubscriberType: string
{
    case Processor = 'processor';
    case Projector = 'projector';
    case Subscriber = 'subscriber';
}

<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingAnalyser\Data;

class Subscriber
{
    /**
     * @param class-string $class
     * @param list<class-string> $events
     * @param list<class-string> $commands
     */
    public function __construct(
        public string $name,
        public string $class,
        public SubscriberType $type,
        public array $events = [],
        public array $commands = [],
    ) {
    }
}
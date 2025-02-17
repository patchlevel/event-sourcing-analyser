<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingAnalyser\Data;

final class BoundedContext
{
    /**
     * @param list<class-string> $aggregates
     * @param list<class-string> $events
     * @param list<class-string> $commands
     * @param list<class-string> $subscribers
     * @param list<class-string> $userInterfaces
     */
    public function __construct(
        public string $name,
        public array $aggregates = [],
        public array $events = [],
        public array $commands = [],
        public array $subscribers = [],
        public array $userInterfaces = [],
    ) {
    }
}

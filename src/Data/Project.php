<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingAnalyser\Data;

final class Project
{
    /**
     * @param array<string, BoundedContext> $boundedContexts
     * @param array<class-string, Aggregate> $aggregates
     * @param array<class-string, Event> $events
     * @param array<class-string, Command> $commands
     * @param array<class-string, Subscriber> $subscribers
     * @param array<class-string, UserInterface> $userInterfaces
     */
    public function __construct(
        public array $boundedContexts = [],
        public array $aggregates = [],
        public array $events = [],
        public array $commands = [],
        public array $subscribers = [],
        public array $userInterfaces = [],
    ) {
    }
}
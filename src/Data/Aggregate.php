<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingAnalyser\Data;

class Aggregate
{
    /**
     * @param class-string       $class
     * @param list<class-string> $events
     * @param list<class-string> $commands
     */
    public function __construct(
        public string $name,
        public string $class,
        public array $events = [],
        public array $commands = [],
    ) {
    }
}

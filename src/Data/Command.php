<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingAnalyser\Data;

class Command
{
    /**
     * @param class-string $class
     * @param list<class-string> $events
     */
    public function __construct(
        public string $name,
        public string $class,
        public array $events = [],
    ) {
    }
}
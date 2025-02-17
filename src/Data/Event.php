<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingAnalyser\Data;

class Event
{
    /**
     * @param class-string $class
     */
    public function __construct(
        public string $name,
        public string $class,
    ) {
    }
}
<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingAnalyser\Data;

final class UserInterface
{
    /**
     * @param class-string $class
     * @param list<class-string> $commands
     * @param list<class-string> $subscribers
     */
    public function __construct(
        public string $name,
        public string $class,
        public array $commands = [],
        public array $subscribers = [],
    ) {
    }
}
<?php

namespace Patchlevel\EventSourcingAnalyser\Tests\Fixture;

use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\CommandBus\CommandBus;

#[Projector('profile')]
class ProfileProjection
{
    public function __construct(
        private readonly CommandBus $commandBus
    ) {
    }

    #[Subscribe(ProfileUpdated::class)]
    public function update(): void
    {
        $this->commandBus->dispatch(new CreateProfile());

        $this->foo();
    }

    private function foo(): void
    {
        $this->commandBus->dispatch(new CreateProfile());
    }
}
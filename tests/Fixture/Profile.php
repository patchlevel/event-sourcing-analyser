<?php

namespace Patchlevel\EventSourcingAnalyser\Tests\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Handle;

#[Aggregate('profile')]
class Profile extends BasicAggregateRoot
{
    #[Handle]
    public static function create(CreateProfile $command): self
    {
        $self = new self();
        $self->recordThat(new ProfileCreated());

        return $self;
    }


    #[Handle(UpdateProfile::class)]
    public function update(): void
    {
        $this->recordThat(new ProfileUpdated());

        $this->foo();
    }

    private function foo(): void
    {
        $this->recordThat(new ProfileUpdated());
    }
}
<?php

namespace Patchlevel\EventSourcingAnalyser\Tests\Fixture;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('profile.created', ['alias1', 'alias2'])]
class ProfileCreated
{
}
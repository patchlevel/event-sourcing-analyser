<?php

namespace Patchlevel\EventSourcingAnalyser\Tests\Fixture;

use Patchlevel\EventSourcing\Attribute\Event;

#[Event('profile.updated')]
class ProfileUpdated
{
}
# Getting Started

In this guide you analyse a small profile domain and render it as an Event Storming diagram. You start from an empty
PHPStan setup, add the analyser, write a handful of event sourcing classes and end up with a PNG of your domain.

## Installation

The analyser is a PHPStan extension, so you install it as a dev dependency next to PHPStan and your event sourcing
code:

```bash
composer require --dev patchlevel/event-sourcing-analyser
```
## Register the extension

The package ships an `extension.neon` that registers the [collectors](event-storming.md) and the output formatters.
Include it from your `phpstan.neon`:

```neon
includes:
    - vendor/patchlevel/event-sourcing-analyser/extension.neon

parameters:
    level: max
    paths:
        - src
```
:::note
If you use [phpstan/extension-installer](https://github.com/phpstan/extension-installer) the file is included
automatically and you can drop the `includes` block.
:::

## Define some events

Events describe what happened in your domain. The analyser finds them by their `#[Event]` attribute and uses the
event name as the label in the diagram.

```php
use Patchlevel\EventSourcing\Attribute\Event;

#[Event('profile.created')]
final class ProfileCreated
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}

#[Event('profile.renamed')]
final class ProfileRenamed
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
```
## Define the aggregate

The aggregate is the heart of your domain. The `#[Aggregate]` attribute marks the class, and every
`$this->recordThat(...)` call tells the analyser which event a method records. A method annotated with `#[Handle]`
links the recorded events back to the command that triggered them.

```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Handle;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
    #[Handle]
    public static function create(CreateProfile $command): self
    {
        $self = new self();
        $self->recordThat(new ProfileCreated($command->name));

        return $self;
    }

    #[Handle(RenameProfile::class)]
    public function rename(string $name): void
    {
        $this->recordThat(new ProfileRenamed($name));
    }
}
```
:::note
The analyser reads the command from the typed parameter of `#[Handle]` or from its explicit `RenameProfile::class`
argument. Both styles are described on the [Event Storming](event-storming.md) page.
:::

## React with a subscriber

A projector, processor or subscriber reacts to events. Mark the class with `#[Projector]` (or `#[Subscriber]` /
`#[Processor]`) and each handler with `#[Subscribe]`. A processor that dispatches a command via the command bus adds
another edge to the diagram.

```php
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;

#[Projector('profile')]
final class ProfileProjector
{
    #[Subscribe(ProfileCreated::class)]
    public function onCreated(ProfileCreated $event): void
    {
        // write to your read model
    }

    #[Subscribe(ProfileRenamed::class)]
    public function onRenamed(ProfileRenamed $event): void
    {
        // update your read model
    }
}
```
## Render the diagram

Run PHPStan with the Graphviz formatter and pipe the result into the `dot` binary to produce an image:

```bash
vendor/bin/phpstan analyse --error-format=eventSourcingGraphviz ./src | dot -Tpng > profile.png
```
You now have a `profile.png` showing the `CreateProfile` and `RenameProfile` commands flowing into the `Profile`
aggregate, the events it records and the projector that reacts to them.

## Export as JSON

If you would rather feed the model into your own tooling, switch the formatter to JSON:

```bash
vendor/bin/phpstan analyse --error-format=eventSourcingJson ./src > profile.json
```
## Result

With a single static analysis run you turned your attributes and method calls into a living diagram of your domain.
As your code changes, the diagram changes with it.

## Learn more

* [How the analyser maps your code to Event Storming notation](event-storming.md)
* [How bounded contexts are derived from namespaces](event-storming.md#bounded-contexts)
* [How to render the Graphviz output](output.md#graphviz)
* [How to consume the JSON output](output.md#json)

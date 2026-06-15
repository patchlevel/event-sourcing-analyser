# How It Works

The analyser reads the attributes and method calls in your code and turns them into a model of your domain: which
commands are handled, which events they record, and which subscribers react to them. This page explains how each
element is detected, how they are grouped into bounded contexts and how Symfony controllers join the picture. The
result is what you see in the [Graphviz and JSON output](output.md).

:::note
The diagram is inspired by [Event Storming](https://agiledojo.de/2023-04-14-event-storming-notation-explained/), a
workshop format that describes a domain as a flow of commands, events and reactions. It is not a strict Event Storming
diagram, but each building block below borrows the color of its Event Storming element, so the picture stays familiar.
:::

## Aggregates

An aggregate is the consistency boundary that records events. The analyser finds every class carrying the
`#[Aggregate]` attribute and uses its name as the label.

```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
}
```
The events and commands that belong to the aggregate are collected from its method bodies, so an aggregate node groups
its own events and commands in the diagram.

## Events

Events describe what happened. They are detected by the `#[Event]` attribute, and the event name becomes the node
label.

```php
use Patchlevel\EventSourcing\Attribute\Event;

#[Event('profile.created')]
final class ProfileCreated
{
}
```
An event is linked to an aggregate when a method of that aggregate records it through `recordThat`:

```php
$this->recordThat(new ProfileCreated($name));
```
:::note
The analyser follows private helper methods as well. If a `#[Handle]` method calls another method that calls
`recordThat`, the recorded event is still attributed to the command.
:::

## Commands

A command expresses an intent to change an aggregate. Commands do not need an attribute of their own. Instead, the
analyser looks at the aggregate methods marked with `#[Handle]` and reads the command type from there. You can give
the command type implicitly through the first parameter or explicitly as an argument:

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
        // command resolved from the CreateProfile parameter type
    }

    #[Handle(RenameProfile::class)]
    public function rename(string $name): void
    {
        // command resolved from the explicit argument
    }
}
```
Each command is connected to the events that its handler records, which is what draws the command to event edges in
the diagram.

## Subscribers

Subscribers react to events. The analyser recognises three flavours, each marked with its own attribute and rendered
in its own color:

* `#[Projector]` builds a read model from events
* `#[Processor]` runs side effects, and may dispatch follow up commands
* `#[Subscriber]` is the generic form

The events a subscriber listens to come from its `#[Subscribe]` handlers:

```php
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;

#[Projector('profile')]
final class ProfileProjector
{
    #[Subscribe(ProfileCreated::class)]
    public function onCreated(ProfileCreated $event): void
    {
    }
}
```
A processor that dispatches a command through the command bus adds an edge back to that command:

```php
use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\CommandBus\CommandBus;

#[Processor('welcome-mail')]
final class WelcomeProcessor
{
    public function __construct(
        private readonly CommandBus $commandBus,
    ) {
    }

    #[Subscribe(ProfileCreated::class)]
    public function onCreated(ProfileCreated $event): void
    {
        $this->commandBus->dispatch(new SendWelcomeMail($event->name));
    }
}
```
:::tip
`#[Subscribe('*')]` subscribes to every event. The analyser keeps the `*` wildcard so you can see catch all
subscribers in the model.
:::

## Symfony controllers

Your domain rarely lives on its own: something from the outside triggers it. The analyser models that outside world by
reading your Symfony controllers and drawing them as user interface nodes, which closes the loop from the request that
dispatches a command to the projection that a controller reads back. A controller is recognised by Symfony's
`#[AsController]` attribute.

When a controller dispatches a command through the command bus, the analyser draws an edge from the controller to that
command:

```php
use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class CreateProfileController
{
    public function __construct(
        private readonly CommandBus $commandBus,
    ) {
    }

    public function __invoke(): void
    {
        $this->commandBus->dispatch(new CreateProfile('patchlevel'));
    }
}
```
When a controller instead calls a method that belongs to a projector, processor or subscriber, the analyser draws an
edge from that subscriber to the controller, showing which read models a user interface depends on.

:::note
The read access is detected through the called method's declaring class. If that class carries a `#[Projector]`,
`#[Processor]` or `#[Subscriber]` attribute, the controller is linked to it.
:::

## Bounded contexts

A bounded context is a self contained part of your domain with its own language. The analyser groups aggregates,
events, commands, subscribers and controllers into bounded contexts so the diagram stays readable even for large
applications.

It does not need an attribute to find a context. It reads the namespace of each class and looks for the layer segment
that a typical layered application uses: `Domain`, `Infrastructure` or `Application`. The segment right before that
layer becomes the context name.

```php
namespace App\Profile\Domain;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
}
```
Here the namespace `App\Profile\Domain` matches the pattern, so the `Profile` aggregate is placed in the `Profile`
context, while a class in `App\Billing\Domain` lands in `Billing`. A class whose namespace contains none of the layer
segments stays in the model but is not grouped into a context.

:::tip
Adopt a consistent `App\<Context>\<Layer>\...` namespace layout across your application and every element lands in
the right cluster automatically.
:::

## Notation

The diagram uses the colors below. They follow the usual Event Storming palette so the picture stays familiar.

| Element | Color | Detected from |
| --- | --- | --- |
| Aggregate | yellow | `#[Aggregate]` |
| Event | orange | `#[Event]` recorded via `recordThat` |
| Command | blue | `#[Handle]` |
| Subscriber | red | `#[Subscriber]` |
| Processor | purple | `#[Processor]` |
| Projector | green | `#[Projector]` |
| User interface | gray | `#[AsController]` |

## Learn more

* [How to render the model as a diagram or JSON](output.md)
* [How to analyse a domain from scratch](getting-started.md)

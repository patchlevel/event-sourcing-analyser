# Event Sourcing Analyser

The event sourcing analyser turns a [patchlevel/event-sourcing](https://patchlevel.dev/docs/event-sourcing/latest)
codebase into a picture. It is a [PHPStan](https://phpstan.org/) extension that statically reads your aggregates,
events, commands and subscribers and renders them as an [Event Storming](event-storming.md) diagram or as a JSON
model, without ever running your code.

Because the analysis is static, you get an always up to date overview of your domain straight from the source: every
command that is handled, every event that is recorded and every subscriber that reacts to it.

## Features

* Detects [aggregates, events, commands and subscribers](event-storming.md) from your attributes
* Groups everything into [bounded contexts](event-storming.md#bounded-contexts) based on your namespaces
* Picks up [Symfony controllers](event-storming.md#symfony-controllers) that dispatch commands or read from projections
* Renders an Event Storming diagram with [Graphviz](output.md#graphviz)
* Exports the whole model as [JSON](output.md#json) for your own tooling

## Installation

```bash
composer require --dev patchlevel/event-sourcing-analyser
```
:::tip
New here? The [getting started guide](getting-started.md) walks you through analysing a small profile domain from
installation to a rendered diagram.
:::

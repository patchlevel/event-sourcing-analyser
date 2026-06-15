# Event Sourcing Analyser

The event sourcing analyser turns a [patchlevel/event-sourcing](https://patchlevel.dev/docs/event-sourcing/latest)
codebase into a picture. It is a [PHPStan](https://phpstan.org/) extension that statically reads your aggregates,
events, commands and subscribers and renders them as a diagram inspired by [Event Storming](how-it-works.md) or as a
JSON model, without ever running your code.

Because the analysis is static, you get an always up to date overview of your domain straight from the source: every
command that is handled, every event that is recorded and every subscriber that reacts to it.

:::warning
This package is still work in progress. It is usable today, but no API is stable yet: class names, attributes and the
output format may change in any release until a stable version is tagged.
:::

## Features

* Detects [aggregates, events, commands and subscribers](how-it-works.md) from your attributes
* Groups everything into [bounded contexts](how-it-works.md#bounded-contexts) based on your namespaces
* Picks up [Symfony controllers](how-it-works.md#symfony-controllers) that dispatch commands or read from projections
* Renders a diagram inspired by Event Storming with [Graphviz](output.md#graphviz)
* Exports the whole model as [JSON](output.md#json) for your own tooling

## Installation

```bash
composer require --dev patchlevel/event-sourcing-analyser
```
:::tip
New here? The [getting started guide](getting-started.md) walks you through analysing a small profile domain from
installation to a rendered diagram.
:::

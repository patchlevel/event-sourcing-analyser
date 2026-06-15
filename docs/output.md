# Output

Once the analyser has read your domain, it renders the model through a PHPStan error formatter. Two formatters ship
with the package: `eventSourcingGraphviz` draws an [Event Storming](event-storming.md) diagram, and
`eventSourcingJson` exports the same model as data for your own tooling. You pick one with the `--error-format` option
of `phpstan analyse`.

## Graphviz

The Graphviz formatter prints the model in the [DOT](https://graphviz.org/doc/info/lang.html) language, which you turn
into an image with the `dot` binary:

```bash
vendor/bin/phpstan analyse --error-format=eventSourcingGraphviz ./src | dot -Tpng > graph.png
```
`dot` is part of the Graphviz toolkit. Install it with your package manager, for example `brew install graphviz` on
macOS or `apt-get install graphviz` on Debian based systems. Swap `-Tpng` for `-Tsvg` to get a scalable vector image
instead.

Every [bounded context](event-storming.md) becomes a dotted cluster. Inside it, each aggregate is its own subgraph
that groups the commands and events belonging to it, while subscribers and controllers sit next to the aggregates in
the same context. The edges follow the flow of your domain:

* a command points to the events it records
* an event points to the subscribers that react to it
* a processor points to the commands it dispatches
* a controller points to the commands it dispatches
* a subscriber points to the controller that reads from it

The node colors match the [Event Storming notation](event-storming.md), so commands are blue, events orange,
aggregates yellow, and so on.

:::tip
The DOT output is plain text. Pipe it to a file and inspect it, or feed it into any Graphviz compatible viewer rather
than the `dot` command line tool.
:::

## JSON

The JSON formatter exposes the analysed model as data, so you can build your own renderer, documentation page or
pipeline check on top of it. It prints the whole project as pretty printed JSON:

```bash
vendor/bin/phpstan analyse --error-format=eventSourcingJson ./src > event-sourcing.json
```
The top level object mirrors the analysed project. Every collection is keyed by the fully qualified class name of its
element:

```json
{
    "boundedContexts": {
        "Profile": {
            "name": "Profile",
            "aggregates": ["App\\Profile\\Domain\\Profile"],
            "events": ["App\\Profile\\Domain\\ProfileCreated"],
            "commands": ["App\\Profile\\Domain\\CreateProfile"],
            "subscribers": ["App\\Profile\\Domain\\ProfileProjector"],
            "userInterfaces": []
        }
    },
    "aggregates": {
        "App\\Profile\\Domain\\Profile": {
            "name": "profile",
            "class": "App\\Profile\\Domain\\Profile",
            "events": ["App\\Profile\\Domain\\ProfileCreated"],
            "commands": ["App\\Profile\\Domain\\CreateProfile"]
        }
    },
    "events": {
        "App\\Profile\\Domain\\ProfileCreated": {
            "name": "profile.created",
            "class": "App\\Profile\\Domain\\ProfileCreated"
        }
    },
    "commands": {
        "App\\Profile\\Domain\\CreateProfile": {
            "name": "CreateProfile",
            "class": "App\\Profile\\Domain\\CreateProfile",
            "events": ["App\\Profile\\Domain\\ProfileCreated"]
        }
    },
    "subscribers": {
        "App\\Profile\\Domain\\ProfileProjector": {
            "name": "profile",
            "class": "App\\Profile\\Domain\\ProfileProjector",
            "type": "projector",
            "events": ["App\\Profile\\Domain\\ProfileCreated"],
            "commands": []
        }
    },
    "userInterfaces": []
}
```
The `boundedContexts` entries hold only class names and reference the full elements in the other collections. The
`type` of a subscriber is one of `subscriber`, `processor` or `projector`, matching the three
[subscriber flavours](event-storming.md). Names come straight from your attributes: an aggregate or event uses the
name you passed to `#[Aggregate]` or `#[Event]`, while a command or controller falls back to its short class name.

## Learn more

* [How each element and its name is detected](event-storming.md)
* [How to analyse a domain from scratch](getting-started.md)

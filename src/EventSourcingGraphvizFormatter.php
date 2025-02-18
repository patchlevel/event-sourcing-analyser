<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingAnalyser;

use Graphviz\Digraph;
use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;

use function array_key_exists;

final class EventSourcingGraphvizFormatter implements ErrorFormatter
{
    public const FONT_COLOR = '#343a40';
    public const EDGE_COLOR = '#343a40';
    public const AGGREGATE_COLOR = '#ffec99';
    public const EVENT_COLOR = '#ffc078';
    public const COMMAND_COLOR = '#74c0fc';
    public const SUBSCRIBER_COLOR = '#ffa8a8';
    public const PROCESSOR_COLOR = '#e599f7';
    public const PROJECTOR_COLOR = '#8ce99a';
    public const USER_INTERFACE_COLOR = '#dee2e6';
    public const DEFAULT_STYLE = [
        'shape' => 'Mrecord',
        'style' => 'filled',
        'width' => 3,
        'height' => 1,
        'fixedsize' => true,
        'fontcolor' => self::FONT_COLOR,
    ];

    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        $data = $analysisResult->getCollectedData();
        $project = (new ProjectFactory())($data);

        $graph = new Digraph();
        $graph->set('rankdir', 'LR');
        //$graph->set('splines', 'ortho');
        $graph->set('nodesep', '0.5');
        $graph->set('ranksep', '1');
        $graph->set('fontcolor', self::FONT_COLOR);

        $renderedNodes = [];

        foreach ($project->boundedContexts as $boundedContext) {
            $boundedContextGraph = $graph->subgraph('cluster_b_' . $boundedContext->name);
            $boundedContextGraph->set('label', $boundedContext->name);
            $boundedContextGraph->set('style', 'dotted');

            foreach ($boundedContext->aggregates as $aggregateClass) {
                $aggregate = $project->aggregates[$aggregateClass];

                $aggregateGraph = $boundedContextGraph->subgraph('cluster_a_' . $aggregate->name);
                $aggregateGraph->set('label', $aggregate->name);
                $aggregateGraph->set('bgcolor', self::AGGREGATE_COLOR);
                $aggregateGraph->set('penwidth', '0');

                foreach ($aggregate->events as $eventClass) {
                    $event = $project->events[$eventClass];

                    $aggregateGraph->node($eventClass, [
                        'label' => $event->name,
                        'color' => self::EVENT_COLOR,
                        ...self::DEFAULT_STYLE,
                    ]);

                    $renderedNodes[$eventClass] = true;
                }

                foreach ($aggregate->commands as $commandClass) {
                    $command = $project->commands[$commandClass];

                    $aggregateGraph->node($commandClass, [
                        'label' => $command->name,
                        'color' => self::COMMAND_COLOR,
                        ...self::DEFAULT_STYLE,
                    ]);

                    $renderedNodes[$commandClass] = true;
                }
            }

            foreach ($boundedContext->events as $eventClass) {
                if (array_key_exists($eventClass, $renderedNodes)) {
                    continue;
                }

                $event = $project->events[$eventClass];

                $boundedContextGraph->node($eventClass, [
                    'label' => $event->name,
                    'color' => self::EVENT_COLOR,
                    ...self::DEFAULT_STYLE,
                ]);

                $renderedNodes[$eventClass] = true;
            }

            foreach ($boundedContext->commands as $commandClass) {
                if (array_key_exists($commandClass, $renderedNodes)) {
                    continue;
                }

                $command = $project->commands[$commandClass];

                $boundedContextGraph->node($commandClass, [
                    'label' => $command->name,
                    'color' => self::COMMAND_COLOR,
                    ...self::DEFAULT_STYLE,
                ]);

                $renderedNodes[$commandClass] = true;
            }

            foreach ($boundedContext->subscribers as $subscriberClass) {
                $subscriber = $project->subscribers[$subscriberClass];

                $boundedContextGraph->node($subscriberClass, [
                    'label' => $subscriber->name,
                    'color' => match ($subscriber->type->value) {
                        'subscriber' => self::SUBSCRIBER_COLOR,
                        'processor' => self::PROCESSOR_COLOR,
                        'projector' => self::PROJECTOR_COLOR,
                    },
                    ...self::DEFAULT_STYLE,
                ]);

                $renderedNodes[$subscriberClass] = true;
            }

            foreach ($boundedContext->userInterfaces as $userInterfaceClass) {
                $userInterface = $project->userInterfaces[$userInterfaceClass];

                $boundedContextGraph->node($userInterface->class, [
                    'label' => $userInterface->name,
                    'color' => self::USER_INTERFACE_COLOR,
                    ...self::DEFAULT_STYLE,
                ]);

                $renderedNodes[$userInterfaceClass] = true;
            }
        }

        foreach ($project->commands as $command) {
            foreach ($command->events as $eventClass) {
                $graph->edge([$command->class, $eventClass], [
                    'color' => self::EDGE_COLOR,
                ]);
            }
        }

        foreach ($project->subscribers as $subscriber) {
            foreach ($subscriber->events as $eventClass) {
                $graph->edge([$eventClass, $subscriber->class], [
                    'color' => self::EDGE_COLOR,
                ]);
            }

            foreach ($subscriber->commands as $commandClass) {
                $graph->edge([$subscriber->class, $commandClass], [
                    'color' => self::EDGE_COLOR,
                ]);
            }
        }

        foreach ($project->userInterfaces as $userInterface) {
            foreach ($userInterface->commands as $commandClass) {
                $graph->edge([$userInterface->class, $commandClass], [
                    'color' => self::EDGE_COLOR,
                ]);
            }

            foreach ($userInterface->subscribers as $subscriberClass) {
                $graph->edge([$subscriberClass, $userInterface->class], [
                    'color' => self::EDGE_COLOR,
                ]);
            }
        }

        $output->writeRaw($graph->render());

        return 0;
    }
}

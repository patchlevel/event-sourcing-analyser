<?php

namespace Patchlevel\EventSourcingAnalyser;

use Graphviz\Digraph;
use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;

class EventSourcingGraphvizFormatter implements ErrorFormatter
{
    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        $data = $analysisResult->getCollectedData();

        $commandToEvent = Helper::commandToEvents($data);
        $aggregates = Helper::aggregates($data);
        $events = Helper::events($data);
        $subscribers = Helper::subscribers($data);
        $controllers = Helper::controller($data);

        $graph = new Digraph();
        $graph->set('rankdir', 'LR');

        $commandInit = [];

        foreach ($aggregates as $aggregate) {
            $sub = $graph->subgraph('cluster_' . $aggregate['name']);
            $sub->set('label', $aggregate['name']);
            $sub->set('bgcolor', '#ffe066');

            foreach ($aggregate['events'] as $event) {
                $sub->node($event, [
                    'label' => $events[$event],
                    'color' => '#ffc078',
                    'shape' => 'box',
                    'style' => 'filled',
                ]);
            }

            foreach ($aggregate['commands'] as $command) {
                $commandInit[$command] = true;

                $sub->node($command, [
                    'label' => Helper::classToName($command),
                    'color' => '#74c0fc',
                    'shape' => 'box',
                    'style' => 'filled',
                ]);
            }
        }

        foreach ($commandToEvent as $command => $events) {
            foreach ($events as $event) {
                $graph->edge([$command, $event]);
            }
        }

        foreach ($subscribers as $class => $subscriber) {
            $graph->node($class, [
                'label' => $subscriber['name'],
                'color' => match ($subscriber['type']) {
                    'subscriber' => '#ffa8a8',
                    'processor' => '#e599f7',
                    'projector' => '#8ce99a',
                },
                'shape' => 'box',
                'style' => 'filled',
            ]);

            foreach ($subscriber['events'] as $event) {
                $graph->edge([$event, $class]);
            }

            foreach ($subscriber['commands'] as $command) {
                $graph->edge([$class, $command]);
            }
        }

        foreach ($controllers as $class => $data) {
            $graph->node($class, [
                'label' => Helper::classToName($class),
                'color' => '#dee2e6',
                'shape' => 'box',
                'style' => 'filled',
            ]);

            foreach ($data['commands'] as $command) {
                $graph->edge([$class, $command]);

                if (array_key_exists($command, $commandInit)) {
                    continue;
                }

                $graph->node($command, [
                    'label' => Helper::classToName($command),
                    'color' => '#74c0fc',
                    'shape' => 'box',
                    'style' => 'filled',
                ]);

                $commandInit[$command] = true;
            }

            foreach ($data['subscribers'] as $subscriber) {
                $graph->edge([$subscriber, $class]);
            }
        }

        echo $graph->render();

        file_put_contents(
            'event-sourcing.dot',
            $graph->render()
        );

        return 0;
    }
}
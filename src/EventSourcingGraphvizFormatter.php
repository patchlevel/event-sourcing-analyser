<?php

namespace Patchlevel\EventSourcingAnalyser;

use Graphviz\Digraph;
use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;

class EventSourcingGraphvizFormatter implements ErrorFormatter
{
    const FONT_COLOR = '#343a40';
    const EDGE_COLOR = '#343a40';

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
        //$graph->set('splines', 'ortho');

        $graph->set('overlap', 'scalexy');

        $graph->set('nodesep', 0.5);
        $graph->set('ranksep', 1);
        $graph->set('concentrate', true);
        $graph->set('fontcolor', self::FONT_COLOR);

        $commandInit = [];

        foreach ($aggregates as $aggregate) {
            $sub = $graph->subgraph('cluster_' . $aggregate['name']);
            $sub->set('label', $aggregate['name']);
            $sub->set('bgcolor', '#ffec99');
            $sub->set('penwidth', 0);

            foreach ($aggregate['events'] as $event) {
                $sub->node($event, [
                    'label' => $events[$event],
                    'color' => '#ffc078',
                    'fontcolor' => self::FONT_COLOR,
                    'shape' => 'Mrecord',
                    'style' => 'filled',
                    'width' => 3,
                    'height' => 1,
                    'fixedsize' => true,
                ]);
            }

            foreach ($aggregate['commands'] as $command) {
                $commandInit[$command] = true;

                $sub->node($command, [
                    'label' => Helper::classToName($command),
                    'color' => '#74c0fc',
                    'fontcolor' => self::FONT_COLOR,
                    'shape' => 'Mrecord',
                    'style' => 'filled',
                    'width' => 3,
                    'height' => 1,
                    'fixedsize' => true,
                ]);
            }
        }

        foreach ($commandToEvent as $command => $events) {
            foreach ($events as $event) {
                $graph->edge([$command, $event], [
                    'color' => self::EDGE_COLOR,
                ]);
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
                'fontcolor' => self::FONT_COLOR,
                'shape' => 'Mrecord',
                'style' => 'filled',
                'width' => 3,
                'height' => 1,
                'fixedsize' => true,
            ]);

            foreach ($subscriber['events'] as $event) {
                $graph->edge([$event, $class], [
                    'color' => self::EDGE_COLOR,
                ]);
            }

            foreach ($subscriber['commands'] as $command) {
                $graph->edge([$class, $command], [
                    'color' => self::EDGE_COLOR,
                ]);
            }
        }

        foreach ($controllers as $class => $data) {
            $graph->node($class, [
                'label' => Helper::classToName($class),
                'color' => '#dee2e6',
                'fontcolor' => self::FONT_COLOR,
                'shape' => 'Mrecord',
                'style' => 'filled',
                'width' => 3,
                'height' => 1,
                'fixedsize' => true,
            ]);

            foreach ($data['commands'] as $command) {
                $graph->edge([$class, $command], [
                    'color' => self::EDGE_COLOR,
                ]);

                if (array_key_exists($command, $commandInit)) {
                    continue;
                }

                $graph->node($command, [
                    'label' => Helper::classToName($command),
                    'color' => '#74c0fc',
                    'fontcolor' => self::FONT_COLOR,
                    'shape' => 'Mrecord',
                    'style' => 'filled',
                    'width' => 3,
                    'height' => 1,
                    'fixedsize' => true,
                ]);

                $commandInit[$command] = true;
            }

            foreach ($data['subscribers'] as $subscriber) {
                $graph->edge([$subscriber, $class], [
                    'color' => self::EDGE_COLOR,
                ]);
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
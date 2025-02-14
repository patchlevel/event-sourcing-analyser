<?php

namespace Patchlevel\EventSourcingAnalyser;

use PHPStan\Collectors\CollectedData;

final class Helper
{
    public static function aggregates(array $dataList): array
    {
        $aggregateData = array_filter(
            $dataList,
            fn(CollectedData $data) => $data->getCollectorType() === AggregateCollector::class
        );

        $aggregateCallData = array_filter(
            $dataList,
            fn(CollectedData $data) => $data->getCollectorType() === AggregateCallCollector::class
        );

        $result = [];

        foreach ($aggregateData as $row) {
            $data = $row->getData();

            $events = [];
            $commands = [];

            foreach ($aggregateCallData as $r) {
                $d = $r->getData();

                if ($d['aggregateClass'] !== $data['class']) {
                    continue;
                }

                if ($d['eventClass'] !== null) {
                    $events[$d['eventClass']] = true;
                }

                if ($d['commandClass'] !== null) {
                    $commands[$d['commandClass']] = true;
                }
            }

            $result[$data['class']] = [
                'name' => $data['name'],
                'class' => $data['class'],
                'events' => array_keys($events),
                'commands' => array_keys($commands),
            ];
        }

        return $result;
    }

    public static function events(array $dataList): array
    {
        $eventData = array_filter(
            $dataList,
            fn(CollectedData $data) => $data->getCollectorType() === EventCollector::class
        );

        $result = [];

        foreach ($eventData as $row) {
            $data = $row->getData();

            $result[$data['class']] = $data['name'];
        }

        return $result;
    }

    public static function subscribers(array $dataList): array
    {
        $eventData = array_filter(
            $dataList,
            fn(CollectedData $data) => $data->getCollectorType() === SubscriberCollector::class
        );

        $subscriberCallData = array_filter(
            $dataList,
            fn(CollectedData $data) => $data->getCollectorType() === SubscriberCallCollector::class
        );

        $result = [];

        foreach ($eventData as $row) {
            $data = $row->getData();

            $events = [];
            $commands = [];

            foreach ($subscriberCallData as $r) {
                $d = $r->getData();

                if ($d['subscriberClass'] !== $data['class']) {
                    continue;
                }

                foreach ($d['eventClasses'] as $event) {
                    $events[$event] = true;
                }

                if ($d['commandClass'] !== null) {
                    $commands[$d['commandClass']] = true;
                }
            }

            $result[$data['class']] = [
                'name' => $data['name'],
                'type' => $data['type'],
                'events' => array_keys($events),
                'commands' => array_keys($commands),
            ];
        }

        return $result;
    }

    public static function commands(array $dataList): array
    {
        $eventData = array_filter(
            $dataList,
            fn(CollectedData $data) => $data->getCollectorType() === AggregateCallCollector::class
        );

        $result = [];

        foreach ($eventData as $row) {
            $data = $row->getData();

            $result[$data['class']] = $data['name'];
        }

        return $result;
    }

    /**
     * @param list<CollectedData> $dataList
     * @return array<class-string, list<class-string>>
     */
    public static function commandToEvents(array $dataList): array
    {
        $aggregateCallData = array_filter(
            $dataList,
            fn(CollectedData $data) => $data->getCollectorType() === AggregateCallCollector::class
        );

        $methodCalls = [];

        foreach ($aggregateCallData as $row) {
            $data = $row->getData();

            $key = sprintf('%s::%s', $data['aggregateClass'], $data['callMethod']);

            if (!array_key_exists($key, $methodCalls)) {
                $methodCalls[$key] = [];
            }

            $methodCalls[$key][] = [
                'aggregateClass' => $data['aggregateClass'],
                'calledMethod' => $data['calledMethod'],
                'eventClass' => $data['eventClass'],
            ];
        }

        $result = [];

        foreach ($aggregateCallData as $row) {
            $data = $row->getData();

            if (!$data['commandClass']) {
                continue;
            }

            if (array_key_exists($data['commandClass'], $result)) {
                continue;
            }

            $result[$data['commandClass']] = self::resolveCallForEvents($methodCalls, $data['aggregateClass'], $data['callMethod']);
        }

        return $result;
    }


    /**
     * @param list<CollectedData> $dataList
     * @return array<class-string, list<class-string>>
     */
    public static function controller(array $dataList): array
    {
        $commandCallData = array_filter(
            $dataList,
            fn(CollectedData $data) => $data->getCollectorType() === SymfonyControllerDispatchCommandCollector::class
        );

        $subscriberCallData = array_filter(
            $dataList,
            fn(CollectedData $data) => $data->getCollectorType() === SymfonyControllerSubscriberAccessCollector::class
        );

        $result = [];

        foreach ($commandCallData as $row) {
            $data = $row->getData();

            $result[$data['controllerClass']]['commands'][] = $data['commandClass'];

            if (!isset($result[$data['controllerClass']]['subscribers'])) {
                $result[$data['controllerClass']]['subscribers'] = [];
            }
        }

        foreach ($subscriberCallData as $row) {
            $data = $row->getData();

            $result[$data['controllerClass']]['subscribers'][] = $data['subscriberClass'];

            if (!isset($result[$data['controllerClass']]['commands'])) {
                $result[$data['controllerClass']]['commands'] = [];
            }
        }

        return $result;
    }

    /**
     * @param array $methodCalls
     * @param string $aggregate
     * @param string $method
     * @return array<class-string>
     */
    private static function resolveCallForEvents(array $methodCalls, string $aggregate, string $method): array
    {
        $key = sprintf('%s::%s', $aggregate, $method);

        $events = [];

        if (!array_key_exists($key, $methodCalls)) {
            return $events;
        }

        $calls = $methodCalls[$key];

        foreach ($calls as $call) {
            if ($call['eventClass'] !== null) {
                $events[] = $call['eventClass'];

                continue;
            }

            $events = array_merge(
                $events,
                self::resolveCallForEvents($methodCalls, $call['aggregateClass'], $call['calledMethod'])
            );
        }

        return $events;
    }

    public static function classToName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }
}
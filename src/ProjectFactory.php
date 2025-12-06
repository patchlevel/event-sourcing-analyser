<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingAnalyser;

use Patchlevel\EventSourcingAnalyser\Data\Aggregate;
use Patchlevel\EventSourcingAnalyser\Data\BoundedContext;
use Patchlevel\EventSourcingAnalyser\Data\Command;
use Patchlevel\EventSourcingAnalyser\Data\Event;
use Patchlevel\EventSourcingAnalyser\Data\Project;
use Patchlevel\EventSourcingAnalyser\Data\Subscriber;
use Patchlevel\EventSourcingAnalyser\Data\SubscriberType;
use Patchlevel\EventSourcingAnalyser\Data\UserInterface;
use PHPStan\Collectors\CollectedData;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function end;
use function explode;
use function preg_match;
use function sprintf;

/**
 * @phpstan-import-type AggregateCollectorType from AggregateCollector
 * @phpstan-import-type AggregateCallCollectorType from AggregateCallCollector
 * @phpstan-import-type EventCollectorType from EventCollector
 * @phpstan-import-type SubscriberCollectorType from SubscriberCollector
 * @phpstan-import-type SubscriberCallCollectorType from SubscriberCallCollector
 * @phpstan-import-type SymfonyControllerDispatchCommandCollectorType from SymfonyControllerDispatchCommandCollector
 * @phpstan-import-type SymfonyControllerSubscriberAccessCollectorType from SymfonyControllerSubscriberAccessCollector
 */
final class ProjectFactory
{
    /** @param list<CollectedData> $collectedDataList */
    public function __invoke(array $collectedDataList): Project
    {
        $aggregates = $this->aggregates($collectedDataList);
        $events = $this->events($collectedDataList);
        $subscribers = $this->subscribers($collectedDataList);
        $commands = $this->commands($collectedDataList);
        $userInterfaces = $this->userInterfaces($collectedDataList);

        $boundedContexts = $this->boundedContexts($aggregates, $events, $subscribers, $commands, $userInterfaces);

        return new Project(
            $boundedContexts,
            $aggregates,
            $events,
            $commands,
            $subscribers,
            $userInterfaces,
        );
    }

    /**
     * @param list<CollectedData> $dataList
     *
     * @return array<class-string, Aggregate>
     */
    private function aggregates(array $dataList): array
    {
        $aggregateData = array_filter(
            $dataList,
            static fn (CollectedData $data) => $data->getCollectorType() === AggregateCollector::class,
        );

        $aggregateCallData = array_filter(
            $dataList,
            static fn (CollectedData $data) => $data->getCollectorType() === AggregateCallCollector::class,
        );

        $result = [];

        foreach ($aggregateData as $row) {
            /** @var AggregateCollectorType $data */
            $data = $row->getData();

            $events = [];
            $commands = [];

            foreach ($aggregateCallData as $r) {
                /** @var AggregateCallCollectorType $d */
                $d = $r->getData();

                if ($d['aggregateClass'] !== $data['class']) {
                    continue;
                }

                if ($d['eventClass'] !== null) {
                    $events[$d['eventClass']] = true;
                }

                if ($d['commandClass'] === null) {
                    continue;
                }

                $commands[$d['commandClass']] = true;
            }

            $result[$data['class']] = new Aggregate(
                name: $data['name'],
                class: $data['class'],
                events: array_keys($events),
                commands: array_keys($commands),
            );
        }

        return $result;
    }

    /**
     * @param list<CollectedData> $dataList
     *
     * @return array<class-string, Event>
     */
    private function events(array $dataList): array
    {
        $eventData = array_filter(
            $dataList,
            static fn (CollectedData $data) => $data->getCollectorType() === EventCollector::class,
        );

        $result = [];

        foreach ($eventData as $row) {
            /** @var EventCollectorType $data */
            $data = $row->getData();

            $result[$data['class']] = new Event(
                name: $data['name'],
                class: $data['class'],
            );
        }

        return $result;
    }

    /**
     * @param list<CollectedData> $dataList
     *
     * @return array<class-string, Subscriber>
     */
    private function subscribers(array $dataList): array
    {
        $eventData = array_filter(
            $dataList,
            static fn (CollectedData $data) => $data->getCollectorType() === SubscriberCollector::class,
        );

        $subscriberCallData = array_filter(
            $dataList,
            static fn (CollectedData $data) => $data->getCollectorType() === SubscriberCallCollector::class,
        );

        $result = [];

        foreach ($eventData as $row) {
            /** @var SubscriberCollectorType $data */
            $data = $row->getData();

            $events = [];
            $commands = [];

            foreach ($subscriberCallData as $r) {
                /** @var SubscriberCallCollectorType $d */
                $d = $r->getData();

                if ($d['subscriberClass'] !== $data['class']) {
                    continue;
                }

                foreach ($d['eventClasses'] as $event) {
                    $events[$event] = true;
                }

                if ($d['commandClass'] === null) {
                    continue;
                }

                $commands[$d['commandClass']] = true;
            }

            $result[$data['class']] = new Subscriber(
                name: $data['name'],
                class: $data['class'],
                type: SubscriberType::from($data['type']),
                events: array_keys($events),
                commands: array_keys($commands),
            );
        }

        return $result;
    }

    /**
     * @param list<CollectedData> $dataList
     *
     * @return array<class-string, Command>
     */
    private function commands(array $dataList): array
    {
        $commandToEvents = $this->commandToEvents($dataList);

        $calls = array_filter(
            $dataList,
            static fn (CollectedData $data) => $data->getCollectorType() === AggregateCallCollector::class ||
                $data->getCollectorType() === SymfonyControllerDispatchCommandCollector::class,
        );

        $result = [];

        foreach ($calls as $row) {
            /** @var AggregateCallCollectorType|SymfonyControllerDispatchCommandCollectorType $data */
            $data = $row->getData();

            if ($data['commandClass'] === null) {
                continue;
            }

            if (array_key_exists($data['commandClass'], $result)) {
                continue;
            }

            $result[$data['commandClass']] = new Command(
                name: self::classToName($data['commandClass']),
                class: $data['commandClass'],
                events: $commandToEvents[$data['commandClass']] ?? [],
            );
        }

        return $result;
    }

    /**
     * @param list<CollectedData> $dataList
     *
     * @return array<class-string, list<class-string>>
     */
    private function commandToEvents(array $dataList): array
    {
        $aggregateCallData = array_filter(
            $dataList,
            static fn (CollectedData $data) => $data->getCollectorType() === AggregateCallCollector::class,
        );

        /** @var array<array{aggregateClass: string, calledMethod: string, eventClass: string}> $methodCalls */
        $methodCalls = [];

        foreach ($aggregateCallData as $row) {
            /** @var AggregateCallCollectorType $data */
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
            /** @var AggregateCallCollectorType $data */
            $data = $row->getData();

            if (!$data['commandClass']) {
                continue;
            }

            if (array_key_exists($data['commandClass'], $result)) {
                continue;
            }

            $result[$data['commandClass']] = self::resolveCallForEvents(
                $methodCalls,
                $data['aggregateClass'],
                $data['callMethod'],
            );
        }

        return $result;
    }

    /**
     * @param list<CollectedData> $dataList
     *
     * @return array<class-string, UserInterface>
     */
    private function userInterfaces(array $dataList): array
    {
        $commandCallData = array_filter(
            $dataList,
            static fn (CollectedData $data) => $data->getCollectorType() === SymfonyControllerDispatchCommandCollector::class,
        );

        $subscriberCallData = array_filter(
            $dataList,
            static fn (CollectedData $data) => $data->getCollectorType() === SymfonyControllerSubscriberAccessCollector::class,
        );

        $result = [];

        foreach ($commandCallData as $row) {
            /** @var SymfonyControllerDispatchCommandCollectorType $data */
            $data = $row->getData();

            if (!isset($result[$data['controllerClass']])) {
                $result[$data['controllerClass']] = new UserInterface(
                    name: self::classToName($data['controllerClass']),
                    class: $data['controllerClass'],
                );
            }

            $result[$data['controllerClass']]->commands[] = $data['commandClass'];
        }

        foreach ($subscriberCallData as $row) {
            /** @var SymfonyControllerSubscriberAccessCollectorType $data */
            $data = $row->getData();

            if (!isset($result[$data['controllerClass']])) {
                $result[$data['controllerClass']] = new UserInterface(
                    name: self::classToName($data['controllerClass']),
                    class: $data['controllerClass'],
                );
            }

            $result[$data['controllerClass']]->subscribers[] = $data['subscriberClass'];
        }

        return $result;
    }

    /**
     * @param array<string, array<array{aggregateClass: string, calledMethod: string, eventClass: string}>> $methodCalls
     *
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
                self::resolveCallForEvents($methodCalls, $call['aggregateClass'], $call['calledMethod']),
            );
        }

        return $events;
    }

    private static function classToName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }

    private static function boundedContext(string $class): string|null
    {
        if (preg_match('#\\\\([^/]+)\\\\(Domain|Infrastructure|Application)\\\\#', $class, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param array<class-string, Aggregate>     $aggregates
     * @param array<class-string, Event>         $events
     * @param array<class-string, Subscriber>    $subscribers
     * @param array<class-string, Command>       $commands
     * @param array<class-string, UserInterface> $userInterfaces
     *
     * @return array<string, BoundedContext>
     */
    private function boundedContexts(
        array $aggregates,
        array $events,
        array $subscribers,
        array $commands,
        array $userInterfaces,
    ): array {
        $boundedContexts = [];

        foreach (array_keys($aggregates) as $class) {
            $boundedContextName = self::boundedContext($class);

            if ($boundedContextName === null) {
                continue;
            }

            if (!isset($boundedContexts[$boundedContextName])) {
                $boundedContexts[$boundedContextName] = new BoundedContext($boundedContextName);
            }

            $boundedContexts[$boundedContextName]->aggregates[] = $class;
        }

        foreach (array_keys($events) as $class) {
            $boundedContextName = self::boundedContext($class);

            if ($boundedContextName === null) {
                continue;
            }

            if (!isset($boundedContexts[$boundedContextName])) {
                $boundedContexts[$boundedContextName] = new BoundedContext($boundedContextName);
            }

            $boundedContexts[$boundedContextName]->events[] = $class;
        }

        foreach (array_keys($commands) as $class) {
            $boundedContextName = self::boundedContext($class);

            if ($boundedContextName === null) {
                continue;
            }

            if (!isset($boundedContexts[$boundedContextName])) {
                $boundedContexts[$boundedContextName] = new BoundedContext($boundedContextName);
            }

            $boundedContexts[$boundedContextName]->commands[] = $class;
        }

        foreach (array_keys($subscribers) as $class) {
            $boundedContextName = self::boundedContext($class);

            if ($boundedContextName === null) {
                continue;
            }

            if (!isset($boundedContexts[$boundedContextName])) {
                $boundedContexts[$boundedContextName] = new BoundedContext($boundedContextName);
            }

            $boundedContexts[$boundedContextName]->subscribers[] = $class;
        }

        foreach (array_keys($userInterfaces) as $class) {
            $boundedContextName = self::boundedContext($class);

            if ($boundedContextName === null) {
                continue;
            }

            if (!isset($boundedContexts[$boundedContextName])) {
                $boundedContexts[$boundedContextName] = new BoundedContext($boundedContextName);
            }

            $boundedContexts[$boundedContextName]->userInterfaces[] = $class;
        }

        return $boundedContexts;
    }
}

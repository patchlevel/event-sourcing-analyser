<?php

namespace Patchlevel\EventSourcingAnalyser;

use PHPStan\Collectors\CollectedData;
use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;

class EventSourcingJsonFormatter implements ErrorFormatter
{
    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        $data = $analysisResult->getCollectedData();

        $commandToEvent = $this->prepareCommandToEvent($data);

        $json = [
            'aggregates' => [],
            'events' => [],
            'subscribers' => [],
            'commands' => [],
        ];

        file_put_contents(
            'event-sourcing.json',
            json_encode($json, JSON_PRETTY_PRINT)
        );

        return 0;
    }

    /**
     * @param list<CollectedData> $dataList
     * @return array
     */
    private function prepareCommandToEvent(array $dataList): array
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

            $result[$data['commandClass']] = [
                'aggregateClass' => $data['aggregateClass'],
                'method' => $data['callMethod'],
                'commandClass' => $data['commandClass'],
                'events' => $this->resolveCallForEvents($methodCalls, $data['aggregateClass'], $data['callMethod']),
            ];
        }

        return $result;
    }

    /**
     * @param array $methodCalls
     * @param string $aggregate
     * @param string $method
     * @return array<class-string>
     */
    private function resolveCallForEvents(array $methodCalls, string $aggregate, string $method): array
    {
        $key = sprintf('%s::%s', $aggregate, $method);

        $events = [];

        $calls = $methodCalls[$key];

        foreach ($calls as $call) {
            if ($call['eventClass'] !== null) {
                $events[] = $call['eventClass'];

                continue;
            }

            $events = array_merge(
                $events,
                $this->resolveCallForEvents($methodCalls, $call['aggregateClass'], $call['calledMethod'])
            );
        }

        return $events;
    }
}
<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingAnalyser;

use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\Output;

class EventSourcingJsonFormatter implements ErrorFormatter
{
    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        $data = $analysisResult->getCollectedData();
        $project = (new ProjectFactory())($data);

        file_put_contents(
            'event-sourcing.json',
            json_encode($project, JSON_PRETTY_PRINT)
        );

        return 0;
    }
}
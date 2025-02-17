<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingAnalyser;

use Patchlevel\EventSourcing\Attribute\Event;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;

/** @implements Collector<InClassNode, array{class: class-string, name: string}> */
final class EventCollector implements Collector
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /** @return array{class: class-string, name: string}|null */
    public function processNode(Node $node, Scope $scope): array|null
    {
        $class = $node->getClassReflection();
        $attributes = $class->getAttributes();

        foreach ($attributes as $attribute) {
            if ($attribute->getName() !== Event::class) {
                continue;
            }

            $arguments = $attribute->getArgumentTypes();

            return [
                'name' => $arguments['name']->getValue(),
                'class' => $class->getName(),
            ];
        }

        return null;
    }
}

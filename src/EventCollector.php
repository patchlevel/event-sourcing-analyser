<?php

namespace Patchlevel\EventSourcingAnalyser;

use Patchlevel\EventSourcing\Attribute\Event;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;

/**
 * @implements Collector<InClassNode, null>
 */
final class EventCollector implements Collector
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope)
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
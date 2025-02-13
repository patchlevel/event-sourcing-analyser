<?php

namespace Patchlevel\EventSourcingAnalyser;

use Patchlevel\EventSourcing\Attribute\Aggregate;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;

/**
 * @implements Collector<InClassNode, null>
 */
final class AggregateCollector implements Collector
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array|null
    {
        if (!$node instanceof InClassNode) {
            return null;
        }

        $classReflection = $scope->getClassReflection();

        if ($classReflection === null) {
            return null;
        }

        $attributes = $classReflection->getAttributes();

        foreach ($attributes as $attribute) {
            if ($attribute->getName() !== Aggregate::class) {
                continue;
            }

            $arguments = $attribute->getArgumentTypes();

            return [
                'class' => $classReflection->getName(),
                'name' => $arguments['name']->getValue(),
            ];
        }

        return null;
    }
}
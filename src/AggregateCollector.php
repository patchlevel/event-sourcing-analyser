<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingAnalyser;

use Patchlevel\EventSourcing\Attribute\Aggregate;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;

/**
 * @phpstan-type AggregateCollectorType array{class: class-string, name: string}
 * @implements Collector<InClassNode, AggregateCollectorType>
 */
final class AggregateCollector implements Collector
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /** @return array{class: class-string, name: string}|null */
    public function processNode(Node $node, Scope $scope): array|null
    {
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
                'name' => (string)$arguments['name']->getValue(),
            ];
        }

        return null;
    }
}

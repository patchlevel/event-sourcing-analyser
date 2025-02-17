<?php

declare(strict_types=1);


namespace Patchlevel\EventSourcingAnalyser;

use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;

/**
 * @implements Collector<InClassNode, array{class: class-string, type: string, name: string}>
 */
final class SubscriberCollector implements Collector
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @return array{class: class-string, type: string, name: string}|null
     */
    public function processNode(Node $node, Scope $scope): array|null
    {
        $classReflection = $scope->getClassReflection();

        if ($classReflection === null) {
            return null;
        }

        $attributes = $classReflection->getAttributes();

        foreach ($attributes as $attribute) {
            $type = match ($attribute->getName()) {
                Subscriber::class => 'subscriber',
                Processor::class => 'processor',
                Projector::class => 'projector',
                default => null,
            };

            if ($type === null) {
                continue;
            }

            $arguments = $attribute->getArgumentTypes();

            return [
                'class' => $classReflection->getName(),
                'type' => $type,
                'name' => $arguments['id']->getValue(),
            ];
        }

        return null;
    }
}
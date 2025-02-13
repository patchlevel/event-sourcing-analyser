<?php

namespace Patchlevel\EventSourcingAnalyser;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ThisType;

/**
 * @implements Collector<InClassNode, null>
 */
final class SubscriberCallCollector implements Collector
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array|null
    {
        if (!$node instanceof MethodCall) {
            return null;
        }

        $classType = $scope->getType($node->var);

        if (!$classType instanceof ObjectType && !$classType instanceof ThisType) {
            return null;
        }

        $subscriberClass = $this->subscriberClass($scope);

        if ($subscriberClass === null) {
            return null;
        }

        $method = $scope->getMethodReflection($classType, $node->name->name);

        return [
            'subscriberClass' => $subscriberClass,
            'callMethod' => $scope->getFunction()?->getName(),
            'calledMethod' => $method?->getName(),
            'commandClass' => $this->commandClass($node, $scope),
            'eventClasses' => $this->eventClasses($node, $scope),
        ];
    }

    private function commandClass(MethodCall $node, Scope $scope): string|null
    {
        if ($node->name->name !== 'dispatch') {
            return null;
        }

        $type = $scope->getType($node->args[0]->value);

        if (!$type instanceof ObjectType) {
            return null;
        }

        return $type->getClassName();
    }

    private function eventClasses(MethodCall $node, Scope $scope): array
    {
        $function = $scope->getFunction();

        if (!$function) {
            return [];
        }

        $attributes = $function->getAttributes();
        $result = [];

        foreach ($attributes as $attribute) {
            if ($attribute->getName() !== Subscribe::class) {
                continue;
            }

            $arguments = $attribute->getArgumentTypes();
            $eventArgument = $arguments['eventClass'] ?? null;

            $event = $eventArgument?->getValue();

            if ($event === '*') {
                return ['*'];
            }

            $result[] = $event;
        }

        return $result;
    }

    private function subscriberClass(Scope $scope): string|null
    {
        $class = $scope->getClassReflection();

        if ($class === null) {
            return null;
        }

        $attributes = $class->getAttributes();

        foreach ($attributes as $attribute) {
            if (in_array($attribute->getName(), [Projector::class, Subscriber::class, Processor::class], true)) {
                return $class->getName();
            }
        }

        return null;
    }
}
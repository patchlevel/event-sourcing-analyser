<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingAnalyser;

use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ThisType;

/**
 * @implements Collector<MethodCall, array{aggregateClass: class-string, callMethod: string|null, calledMethod: string|null, eventClass: string|null, commandClass: string|null}>
 */
final class SubscriberCallCollector implements Collector
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @return array{subscriberClass: class-string, callMethod: string|null, calledMethod: string|null, eventClasses: string[], commandClass: string|null}|null
     */
    public function processNode(Node $node, Scope $scope): array|null
    {
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

    /**
     * @return class-string|null
     */
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

    /**
     * @return list<class-string>
     */
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

    /**
     * @return class-string|null
     */
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
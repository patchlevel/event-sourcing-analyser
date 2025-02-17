<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingAnalyser;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Handle;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ThisType;

/** @implements Collector<MethodCall, array{aggregateClass: class-string, callMethod: string|null, calledMethod: string|null, eventClass: string|null, commandClass: string|null}> */
final class AggregateCallCollector implements Collector
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /** @return array{aggregateClass: class-string, callMethod: string|null, calledMethod: string|null, eventClass: string|null, commandClass: string|null}|null */
    public function processNode(Node $node, Scope $scope): array|null
    {
        $classType = $scope->getType($node->var);

        if (!$classType instanceof ObjectType && !$classType instanceof ThisType) {
            return null;
        }

        $reflection = $classType->getClassReflection();

        if (!$reflection) {
            return null;
        }

        if (!$reflection->is(BasicAggregateRoot::class)) {
            return null;
        }

        $callFunction = $scope->getFunction();
        $method = $scope->getMethodReflection($classType, $node->name->name);

        return [
            'aggregateClass' => $classType->getClassName(),
            'callMethod' => $callFunction?->getName(),
            'calledMethod' => $method?->getName(),
            'eventClass' => $this->eventClass($node, $scope),
            'commandClass' => $this->commandClass($node, $scope),
        ];
    }

    /** @return class-string|null */
    private function eventClass(MethodCall $node, Scope $scope): string|null
    {
        if ($node->name->name !== 'recordThat') {
            return null;
        }

        $type = $scope->getType($node->args[0]->value);

        if (!$type instanceof ObjectType) {
            return null;
        }

        return $type->getClassName();
    }

    /** @return class-string|null */
    private function commandClass(MethodCall $node, Scope $scope): string|null
    {
        $function = $scope->getFunction();

        if (!$function) {
            return null;
        }

        $attributes = $function->getAttributes();

        foreach ($attributes as $attribute) {
            if ($attribute->getName() !== Handle::class) {
                continue;
            }

            $arguments = $attribute->getArgumentTypes();

            if ($arguments === []) {
                $parameters = $function->getParameters();

                if ($parameters === []) {
                    return null;
                }

                $type = $parameters[0]->getType();

                if (!$type instanceof ObjectType) {
                    return null;
                }

                return $type->getClassName();
            }

            $commandArgument = $arguments['commandClass'] ?? null;

            if ($commandArgument === null) {
                return null;
            }

            return $commandArgument->getValue();
        }

        return null;
    }
}

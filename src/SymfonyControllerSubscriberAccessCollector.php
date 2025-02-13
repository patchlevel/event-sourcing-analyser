<?php

namespace Patchlevel\EventSourcingAnalyser;

use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\InClassNode;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ThisType;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * @implements Collector<InClassNode, null>
 */
final class SymfonyControllerSubscriberAccessCollector implements Collector
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

        $subscriptionClass = $this->subscriptionClass($node, $scope);
        $controllerClass = $this->controllerClass($scope);

        if ($controllerClass === null || $subscriptionClass === null) {
            return null;
        }

        return [
            'controllerClass' => $controllerClass,
            'subscriberClass' => $subscriptionClass,
        ];
    }

    private function controllerClass(Scope $scope): string|null
    {
        $class = $scope->getClassReflection();

        if ($class === null) {
            return null;
        }

        $attributes = $class->getAttributes();

        foreach ($attributes as $attribute) {
            if ($attribute->getName() === AsController::class) {
                return $class->getName();
            }
        }

        return null;
    }

    private function subscriptionClass(Node $node, Scope $scope): string|null
    {
        $classType = $scope->getType($node->var);

        if (!$classType instanceof ObjectType && !$classType instanceof ThisType) {
            return null;
        }

        $reflection = $scope->getMethodReflection($classType, $node->name->name)?->getDeclaringClass();

        if (!$reflection) {
            return null;
        }

        $attributes = $reflection->getAttributes();

        foreach ($attributes as $attribute) {
            if (in_array($attribute->getName(), [Projector::class, Subscriber::class, Processor::class], true)) {
                return $reflection->getName();
            }
        }

        return null;
    }
}
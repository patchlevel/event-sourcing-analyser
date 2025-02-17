<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcingAnalyser;

use Patchlevel\EventSourcing\Attribute\Processor;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ThisType;
use PHPStan\Type\Type;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * @implements Collector<MethodCall, array{controllerClass: class-string, commandClass: class-string}>
 */
final class SymfonyControllerSubscriberAccessCollector implements Collector
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @return array{controllerClass: class-string, subscriberClass: class-string}|null
     */
    public function processNode(Node $node, Scope $scope): array|null
    {
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

    /**
     * @return class-string|null
     */
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

    /**
     * @return class-string|null
     */
    private function subscriptionClass(MethodCall $node, Scope $scope): string|null
    {
        $classType = $scope->getType($node->var);

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
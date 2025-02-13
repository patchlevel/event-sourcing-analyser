<?php

namespace Patchlevel\EventSourcingAnalyser;

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
final class SymfonyControllerDispatchCommandCollector implements Collector
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

        $controllerClass = $this->controllerClass($scope);

        if ($controllerClass === null) {
            return null;
        }

        if ($node->name->name !== 'dispatch') {
            return null;
        }

        $type = $scope->getType($node->args[0]->value);

        if (!$type instanceof ObjectType) {
            return null;
        }

        return [
            'controllerClass' => $controllerClass,
            'commandClass' => $type->getClassName(),
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
}
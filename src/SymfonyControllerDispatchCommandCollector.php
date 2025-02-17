<?php

declare(strict_types=1);


namespace Patchlevel\EventSourcingAnalyser;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\ObjectType;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * @implements Collector<MethodCall, array{controllerClass: class-string, commandClass: class-string}>
 */
final class SymfonyControllerDispatchCommandCollector implements Collector
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @return array{controllerClass: class-string, commandClass: class-string}|null
     */
    public function processNode(Node $node, Scope $scope): array|null
    {
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
}
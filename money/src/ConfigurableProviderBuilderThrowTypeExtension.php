<?php

declare(strict_types=1);

namespace Brick\Money\PHPStan;

use Brick\Money\Exception\InvalidArgumentException;
use Brick\Money\ExchangeRateProvider\Configurable\ConfigurableProviderBuilder;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodThrowTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Narrows the throw type of {@see ConfigurableProviderBuilder::addExchangeRate()}.
 *
 * - When the exchange rate is a `BigNumber`, `int`, or `numeric-string`, {@see MathException} from parsing cannot occur.
 * - {@see InvalidArgumentException} can always occur (non-positive rate, same-currency rate ≠ 1, duplicate rate).
 */
final class ConfigurableProviderBuilderThrowTypeExtension implements DynamicMethodThrowTypeExtension
{
    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getDeclaringClass()->getName() === ConfigurableProviderBuilder::class
            && $methodReflection->getName() === 'addExchangeRate';
    }

    public function getThrowTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): Type|null {
        $args = $methodCall->getArgs();

        if (! isset($args[2])) {
            return $methodReflection->getThrowType();
        }

        $exchangeRateType = $scope->getType($args[2]->value);

        if (SafeType::isSafeNumber($exchangeRateType)) {
            return new ObjectType(InvalidArgumentException::class);
        }

        return $methodReflection->getThrowType();
    }
}

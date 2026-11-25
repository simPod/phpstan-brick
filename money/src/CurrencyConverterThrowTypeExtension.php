<?php

declare(strict_types=1);

namespace Brick\Money\PHPStan;

use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\CurrencyConverter;
use Brick\Money\Exception\ContextException;
use Brick\Money\Exception\ExchangeRateException;
use Brick\Money\Exception\UnknownCurrencyException;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodThrowTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Narrows the throw type of {@see CurrencyConverter::convert()} and {@see CurrencyConverter::convertToRational()}.
 *
 * - When the currency is a `Currency` instance or a known ISO code, {@see UnknownCurrencyException} cannot occur.
 * - When the rounding mode is not `Unnecessary`, {@see RoundingNecessaryException} cannot occur.
 */
final class CurrencyConverterThrowTypeExtension implements DynamicMethodThrowTypeExtension
{
    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        $className = $methodReflection->getDeclaringClass()->getName();
        $methodName = $methodReflection->getName();

        return $className === CurrencyConverter::class
            && ($methodName === 'convert' || $methodName === 'convertToRational');
    }

    public function getThrowTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): Type|null {
        $args = $methodCall->getArgs();

        if (! isset($args[1]) || SafeType::hasUnboundArguments($args)) {
            return $methodReflection->getThrowType();
        }

        $methodName = $methodReflection->getName();
        $residualTypes = [new ObjectType(ExchangeRateException::class)];

        if (! SafeType::isSafeCurrency($scope->getType($args[1]->value))) {
            $residualTypes[] = new ObjectType(UnknownCurrencyException::class);
        }

        if ($methodName === 'convert') {
            $contextType = isset($args[3]) ? $scope->getType($args[3]->value) : null;
            $roundingModeType = isset($args[4]) ? $scope->getType($args[4]->value) : null;

            if (! SafeType::isSafeRoundingMode($roundingModeType)) {
                $residualTypes[] = new ObjectType(RoundingNecessaryException::class);
            }

            if (! SafeType::isContextApplicable($contextType, $roundingModeType)) {
                $residualTypes[] = new ObjectType(ContextException::class);
            }
        }

        return TypeCombinator::union(...$residualTypes);
    }
}

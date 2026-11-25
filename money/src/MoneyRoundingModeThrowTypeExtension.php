<?php

declare(strict_types=1);

namespace Brick\Money\PHPStan;

use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\AbstractMoney;
use Brick\Money\Exception\ContextException;
use Brick\Money\Exception\InvalidArgumentException;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use Brick\Money\RationalMoney;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodThrowTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Narrows conversion and context changes on {@see Money} and {@see RationalMoney}.
 */
final class MoneyRoundingModeThrowTypeExtension implements DynamicMethodThrowTypeExtension
{
    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        $methodName = $methodReflection->getName();
        $className = $methodReflection->getDeclaringClass()->getName();

        if ($methodName === 'convertedTo') {
            return $className === Money::class || $className === RationalMoney::class;
        }

        return $methodName === 'toContext' && $className === AbstractMoney::class;
    }

    public function getThrowTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): Type|null {
        $args = $methodCall->getArgs();

        if ($args === [] || SafeType::hasUnboundArguments($args)) {
            return $methodReflection->getThrowType();
        }

        $convertedTo = $methodReflection->getName() === 'convertedTo';
        $residual = [];

        if ($convertedTo) {
            if (! isset($args[1])) {
                return $methodReflection->getThrowType();
            }

            $rateType = $scope->getType($args[1]->value);
            $residual = SafeType::numberParsingExceptions($rateType);

            // The rate must be positive, and a same-currency conversion requires a rate of 1.
            if (! SafeType::isOne($rateType)) {
                $residual[] = new ObjectType(InvalidArgumentException::class);
            }

            if (! SafeType::isSafeCurrency($scope->getType($args[0]->value))) {
                $residual[] = new ObjectType(UnknownCurrencyException::class);
            }
        }

        $isRational = new ObjectType(RationalMoney::class)->isSuperTypeOf($scope->getType($methodCall->var))->yes();

        if (! $convertedTo || ! $isRational) {
            $contextArg = $args[$convertedTo ? 2 : 0] ?? null;
            $roundingModeArg = $args[$convertedTo ? 3 : 1] ?? null;
            $roundingModeType = $roundingModeArg === null ? null : $scope->getType($roundingModeArg->value);

            if (! SafeType::isSafeRoundingMode($roundingModeType)) {
                $residual[] = new ObjectType(RoundingNecessaryException::class);
            }

            if (! SafeType::isContextApplicable($contextArg?->value, $roundingModeType, $scope)) {
                $residual[] = new ObjectType(ContextException::class);
            }
        }

        return $residual === [] ? null : TypeCombinator::union(...$residual);
    }
}

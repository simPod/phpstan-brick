<?php

declare(strict_types=1);

namespace Brick\Money\PHPStan;

use Brick\Math\Exception\DivisionByZeroException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\AbstractMoney;
use Brick\Money\Exception\ContextException;
use Brick\Money\Exception\ContextMismatchException;
use Brick\Money\Exception\CurrencyMismatchException;
use Brick\Money\Money;
use Brick\Money\MoneyBag;
use Brick\Money\RationalMoney;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodThrowTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function in_array;

/**
 * Narrows the throw type of {@see Money}, {@see RationalMoney}, and {@see MoneyBag} instance methods
 * based on argument types and rounding mode.
 *
 * - When the argument is an {@see AbstractMoney}, there is no parsing, but a currency check applies.
 * - When the argument is a number, NumberFormatException can occur only if parsing may fail.
 * - When the rounding mode is known to NOT be `Unnecessary`, {@see RoundingNecessaryException} cannot occur.
 * - {@see RationalMoney} operations do not apply a context or round the result.
 */
final class MoneyOperationThrowTypeExtension implements DynamicMethodThrowTypeExtension
{
    private const array ComparisonMethods = [
        'compareTo',
        'isEqualTo',
        'isLessThan',
        'isLessThanOrEqualTo',
        'isGreaterThan',
        'isGreaterThanOrEqualTo',
    ];

    private const array ArithmeticMethods = [
        'plus',
        'minus',
        'multipliedBy',
        'dividedBy',
    ];

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        $methodName = $methodReflection->getName();
        $className = $methodReflection->getDeclaringClass()->getName();

        if (in_array($methodName, self::ComparisonMethods, true)) {
            return $className === AbstractMoney::class
                || $methodReflection->getDeclaringClass()->isSubclassOf(AbstractMoney::class);
        }

        $isAbstractMoney = $className === AbstractMoney::class
            || $methodReflection->getDeclaringClass()->isSubclassOf(AbstractMoney::class);

        if (in_array($methodName, self::ArithmeticMethods, true)) {
            return $isAbstractMoney;
        }

        if ($methodName === 'getMoney') {
            return $className === MoneyBag::class;
        }

        return false;
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

        $methodName = $methodReflection->getName();
        $argType = $scope->getType($args[0]->value);

        if ($methodName === 'getMoney') {
            return SafeType::isSafeCurrency($argType) ? null : $methodReflection->getThrowType();
        }

        if (in_array($methodName, self::ComparisonMethods, true)) {
            return $this->narrowComparison($argType);
        }

        return $this->narrowArithmetic(
            $methodName,
            $argType,
            $scope->getType($methodCall->var),
            isset($args[1]) ? $scope->getType($args[1]->value) : null,
        );
    }

    /**
     * Narrows comparison methods on {@see AbstractMoney}.
     */
    private function narrowComparison(Type $argType): Type|null
    {
        $residual = SafeType::numberParsingExceptions($argType, acceptsMoney: true);

        if (! new ObjectType(AbstractMoney::class)->isSuperTypeOf($argType)->no()) {
            $residual[] = new ObjectType(CurrencyMismatchException::class);
        }

        return $residual === [] ? null : TypeCombinator::union(...$residual);
    }

    /**
     * Narrows arithmetic methods (`plus`, `minus`, `multipliedBy`, `dividedBy`)
     * on {@see Money} and {@see RationalMoney}.
     */
    private function narrowArithmetic(
        string $methodName,
        Type $argType,
        Type $receiverType,
        Type|null $roundingModeType,
    ): Type|null {
        $acceptsMoney = $methodName === 'plus' || $methodName === 'minus';
        $mayApplyContext = ! new ObjectType(RationalMoney::class)->isSuperTypeOf($receiverType)->yes();
        $residual = SafeType::numberParsingExceptions($argType, $acceptsMoney);

        if ($acceptsMoney && ! new ObjectType(AbstractMoney::class)->isSuperTypeOf($argType)->no()) {
            $residual[] = new ObjectType(CurrencyMismatchException::class);
        }

        if ($acceptsMoney && $mayApplyContext && ! new ObjectType(Money::class)->isSuperTypeOf($argType)->no()) {
            $residual[] = new ObjectType(ContextMismatchException::class);
        }

        if ($methodName === 'dividedBy' && ! SafeType::isNonZero($argType)) {
            $residual[] = new ObjectType(DivisionByZeroException::class);
        }

        if ($mayApplyContext) {
            // The receiver's existing cash context and currency are compatible. AutoContext may
            // still reject the operation if the mode is anything other than Unnecessary.
            if (! SafeType::isUnnecessaryRoundingMode($roundingModeType)) {
                $residual[] = new ObjectType(ContextException::class);
            }

            // Adding an integer can break a cash step (e.g. a step of 10 whole units).
            // Multiplying by an integer or adding/subtracting zero preserves any step.
            $hasExactResult = $acceptsMoney && SafeType::isZero($argType)
                || $methodName === 'multipliedBy' && new IntegerType()->isSuperTypeOf($argType)->yes();

            if (! $hasExactResult && ! SafeType::isSafeRoundingMode($roundingModeType)) {
                $residual[] = new ObjectType(RoundingNecessaryException::class);
            }
        }

        return $residual === [] ? null : TypeCombinator::union(...$residual);
    }
}

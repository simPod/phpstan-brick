<?php

declare(strict_types=1);

namespace Brick\Money\PHPStan;

use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Context\AutoContext;
use Brick\Money\Context\CustomContext;
use Brick\Money\Context\DefaultContext;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use Brick\Money\RationalMoney;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodThrowTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function count;
use function in_array;

/**
 * Narrows the throw type of {@see Money::of()}, {@see Money::ofMinor()}, {@see Money::zero()},
 * {@see RationalMoney::of()}, and {@see RationalMoney::zero()}.
 *
 * - When the amount is a `BigNumber`, `int`, or `numeric-string`, {@see NumberFormatException} from parsing cannot occur.
 * - When the currency is a {@see Currency} instance or a known ISO currency code, {@see UnknownCurrencyException} cannot occur.
 * - For `Money::of()`/`ofMinor()`, {@see RoundingNecessaryException} may still occur depending on the rounding mode.
 */
final class MoneyFactoryThrowTypeExtension implements DynamicStaticMethodThrowTypeExtension
{
    private const array SupportedMethods = [
        Money::class => ['of', 'ofMinor', 'zero'],
        RationalMoney::class => ['of', 'zero'],
    ];

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        $className = $methodReflection->getDeclaringClass()->getName();
        $methodName = $methodReflection->getName();

        return isset(self::SupportedMethods[$className])
            && in_array($methodName, self::SupportedMethods[$className], true);
    }

    public function getThrowTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope,
    ): Type|null {
        $args = $methodCall->getArgs();
        $methodName = $methodReflection->getName();

        // zero() only has a currency parameter.
        if ($methodName === 'zero') {
            return $this->narrowZero($methodCall, $scope, $methodReflection);
        }

        if (count($args) < 2) {
            return $methodReflection->getThrowType();
        }

        $className = $methodReflection->getDeclaringClass()->getName();
        $amountType = $scope->getType($args[0]->value);
        $currencyType = $scope->getType($args[1]->value);

        $amountIsSafe = SafeType::isSafeNumber($amountType);
        $currencyIsSafe = SafeType::isSafeCurrency($currencyType);

        if (! $amountIsSafe && ! $currencyIsSafe) {
            return $methodReflection->getThrowType();
        }

        $residualTypes = [];

        if (! $amountIsSafe) {
            $residualTypes[] = new ObjectType(NumberFormatException::class);
        }

        if (! $currencyIsSafe) {
            $residualTypes[] = new ObjectType(UnknownCurrencyException::class);
        }

        // Money::of()/ofMinor() can still throw RoundingNecessaryException,
        // unless the amount cannot require rounding.
        if ($className === Money::class && ! $this->isRoundingSafe($amountType, $methodName, $args)) {
            $residualTypes[] = new ObjectType(RoundingNecessaryException::class);
        }

        if ($residualTypes === []) {
            return null;
        }

        return TypeCombinator::union(...$residualTypes);
    }

    /**
     * Checks if rounding is guaranteed to not be needed.
     *
     * - Zero amount: zero at any scale is still zero.
     * - Money::of() with int amount and a context that only scales (no step division):
     *   scaling an integer to higher precision just adds trailing zeros.
     * - Money::ofMinor() with int amount and default context:
     *   minor int divided by 10^fractionDigits then scaled back to fractionDigits is always exact.
     *
     * @param Arg[] $args
     */
    private function isRoundingSafe(Type $amountType, string $methodName, array $args): bool
    {
        if (SafeType::isZero($amountType)) {
            return true;
        }

        if (! (new IntegerType())->isSuperTypeOf($amountType)->yes()) {
            return false;
        }

        // For Money::of() with int amount, check if the context is safe (no step division).
        if ($methodName === 'of') {
            return $this->isContextSafeForInteger($args);
        }

        // For Money::ofMinor() with int amount, default context is always safe:
        // int / 10^fractionDigits scaled to fractionDigits is exact.
        if ($methodName === 'ofMinor') {
            return ! isset($args[2]) || $this->isDefaultContext($args[2]);
        }

        return false;
    }

    /**
     * Checks if the context argument (arg index 2) is safe for integer amounts.
     *
     * Safe contexts are those that only perform toScale() without dividing by a step:
     * - No context arg (default {@see DefaultContext})
     * - {@see DefaultContext}
     * - {@see AutoContext}
     * - {@see CustomContext} with step = 1 (default or explicit)
     *
     * @param Arg[] $args
     */
    private function isContextSafeForInteger(array $args): bool
    {
        if (! isset($args[2])) {
            return true;
        }

        $contextExpr = $args[2]->value;

        if (! $contextExpr instanceof New_ || ! $contextExpr->class instanceof Name) {
            return false;
        }

        $contextClass = $contextExpr->class->toString();

        if ($contextClass === DefaultContext::class || $contextClass === AutoContext::class) {
            return true;
        }

        if ($contextClass === CustomContext::class) {
            $ctorArgs = $contextExpr->getArgs();

            // new CustomContext($scale) — step defaults to 1.
            if (count($ctorArgs) < 2) {
                return true;
            }

            // new CustomContext($scale, 1) — explicit step = 1.
            $stepType = $ctorArgs[1]->value;

            return $stepType instanceof Int_ && $stepType->value === 1;
        }

        return false;
    }

    private function isDefaultContext(Arg $arg): bool
    {
        $expr = $arg->value;

        return $expr instanceof New_
            && $expr->class instanceof Name
            && $expr->class->toString() === DefaultContext::class;
    }

    private function narrowZero(
        StaticCall $methodCall,
        Scope $scope,
        MethodReflection $methodReflection,
    ): Type|null {
        $args = $methodCall->getArgs();

        if (count($args) < 1) {
            return $methodReflection->getThrowType();
        }

        $currencyType = $scope->getType($args[0]->value);

        if (SafeType::isSafeCurrency($currencyType)) {
            return null;
        }

        return new ObjectType(UnknownCurrencyException::class);
    }
}

<?php

declare(strict_types=1);

namespace Brick\Money\PHPStan;

use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Context\AutoContext;
use Brick\Money\Context\CustomContext;
use Brick\Money\Context\DefaultContext;
use Brick\Money\Exception\ContextException;
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
 * - When the amount is safe to parse, NumberFormatException cannot occur.
 * - When the currency is a {@see Currency} instance or a known ISO currency code, {@see UnknownCurrencyException} cannot occur.
 * - For `Money::of()`/`ofMinor()`, {@see RoundingNecessaryException} may still occur depending on the rounding mode.
 * - Money factories can throw {@see ContextException} when their context does not apply.
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

        if (SafeType::hasUnboundArguments($args)) {
            return $methodReflection->getThrowType();
        }

        if ($methodName === 'zero') {
            return $this->narrowZero($methodCall, $scope, $methodReflection);
        }

        if (count($args) < 2) {
            return $methodReflection->getThrowType();
        }

        $className = $methodReflection->getDeclaringClass()->getName();
        $amountType = $scope->getType($args[0]->value);
        $currencyType = $scope->getType($args[1]->value);

        $residualTypes = SafeType::numberParsingExceptions($amountType);

        if (! SafeType::isSafeCurrency($currencyType)) {
            $residualTypes[] = new ObjectType(UnknownCurrencyException::class);
        }

        if ($className === Money::class) {
            $contextType = isset($args[2]) ? $scope->getType($args[2]->value) : null;
            $roundingModeType = isset($args[3]) ? $scope->getType($args[3]->value) : null;

            if (! $this->isRoundingSafe($amountType, $methodName, $args, $roundingModeType)) {
                $residualTypes[] = new ObjectType(RoundingNecessaryException::class);
            }

            if (! SafeType::isContextApplicable($contextType, $roundingModeType)) {
                $residualTypes[] = new ObjectType(ContextException::class);
            }
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
    private function isRoundingSafe(
        Type $amountType,
        string $methodName,
        array $args,
        Type|null $roundingModeType,
    ): bool {
        if (SafeType::isSafeRoundingMode($roundingModeType)) {
            return true;
        }

        if (SafeType::isZero($amountType)) {
            return true;
        }

        if (! new IntegerType()->isSuperTypeOf($amountType)->yes()) {
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
            foreach ($contextExpr->getArgs() as $index => $arg) {
                if ($arg->unpack) {
                    return false;
                }

                $isStep = $arg->name === null ? $index === 1 : $arg->name->toString() === 'step';
                if ($isStep) {
                    return $arg->value instanceof Int_ && $arg->value->value === 1;
                }
            }

            return true;
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

        $residualTypes = [];

        if (! SafeType::isSafeCurrency($currencyType)) {
            $residualTypes[] = new ObjectType(UnknownCurrencyException::class);
        }

        if (
            $methodReflection->getDeclaringClass()->getName() === Money::class
            && ! SafeType::isContextApplicable(isset($args[1]) ? $scope->getType($args[1]->value) : null, null)
        ) {
            $residualTypes[] = new ObjectType(ContextException::class);
        }

        return $residualTypes === [] ? null : TypeCombinator::union(...$residualTypes);
    }
}

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
use PhpParser\Node\Expr;
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
            $context = ($args[2] ?? null)?->value;
            $roundingModeType = isset($args[3]) ? $scope->getType($args[3]->value) : null;

            if (! $this->isRoundingSafe($amountType, $methodName, $context, $roundingModeType, $scope)) {
                $residualTypes[] = new ObjectType(RoundingNecessaryException::class);
            }

            if (! SafeType::isContextApplicable($context, $roundingModeType, $scope)) {
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
     * - Money::ofMinor() with int amount and default scale without step division:
     *   minor int divided by 10^fractionDigits then scaled back to fractionDigits is always exact.
     */
    private function isRoundingSafe(
        Type $amountType,
        string $methodName,
        Expr|null $context,
        Type|null $roundingModeType,
        Scope $scope,
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

        if (
            $context === null
            || new ObjectType(DefaultContext::class)->isSuperTypeOf($scope->getType($context))->yes()
            || SafeType::isUnitCashContext($context, $scope)
        ) {
            return true;
        }

        return $methodName === 'of' && $this->isContextSafeForInteger($context);
    }

    /**
     * Checks other contexts that only scale a whole-unit integer without dividing by a step.
     *
     * Safe contexts are those that only perform toScale() without dividing by a step:
     * - {@see AutoContext}
     * - {@see CustomContext} with step = 1 (default or explicit)
     */
    private function isContextSafeForInteger(Expr $context): bool
    {
        if (! $context instanceof New_ || ! $context->class instanceof Name) {
            return false;
        }

        $contextClass = $context->class->toString();

        if ($contextClass === AutoContext::class) {
            return true;
        }

        if ($contextClass === CustomContext::class) {
            foreach ($context->getArgs() as $index => $arg) {
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
            && ! SafeType::isContextApplicable(($args[1] ?? null)?->value, null, $scope)
        ) {
            $residualTypes[] = new ObjectType(ContextException::class);
        }

        return $residualTypes === [] ? null : TypeCombinator::union(...$residualTypes);
    }
}

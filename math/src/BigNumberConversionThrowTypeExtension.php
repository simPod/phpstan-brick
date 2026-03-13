<?php

declare(strict_types=1);

namespace Brick\Math\PHPStan;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\BigNumber;
use Brick\Math\BigRational;
use Brick\Math\Exception\IntegerOverflowException;
use Brick\Math\RoundingMode;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\DynamicMethodThrowTypeExtension;
use PHPStan\Type\Enum\EnumCaseObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Narrows the throw type of toBigInteger(), toBigDecimal(), toBigRational(), and toInt().
 *
 * When the caller is already the target type, toBigInteger/toBigDecimal/toBigRational are no-ops and cannot throw.
 * When toInt() is called on {@see BigInteger}, only {@see IntegerOverflowException} can be thrown
 * (no {@see RoundingNecessaryException}).
 * When toBigInteger() is called on the result of toScale(0, $roundingMode) where $roundingMode is not Unnecessary,
 * the conversion cannot throw because the scale is guaranteed to be 0.
 */
final class BigNumberConversionThrowTypeExtension implements DynamicMethodThrowTypeExtension
{
    private const array MethodToClass = [
        'toBigInteger' => BigInteger::class,
        'toBigDecimal' => BigDecimal::class,
        'toBigRational' => BigRational::class,
    ];

    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        $className = $methodReflection->getDeclaringClass()->getName();
        $isBigNumber = $className === BigNumber::class
            || $methodReflection->getDeclaringClass()->isSubclassOf(BigNumber::class);

        if (! $isBigNumber) {
            return false;
        }

        return isset(self::MethodToClass[$methodReflection->getName()])
            || $methodReflection->getName() === 'toInt';
    }

    public function getThrowTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): Type|null {
        $callerType = $scope->getType($methodCall->var);
        $methodName = $methodReflection->getName();

        if ($methodName === 'toInt') {
            // BigInteger::toInt() can only throw IntegerOverflowException (no RoundingNecessaryException).
            if ((new ObjectType(BigInteger::class))->isSuperTypeOf($callerType)->yes()) {
                return new ObjectType(IntegerOverflowException::class);
            }

            return $methodReflection->getThrowType();
        }

        $targetClass = self::MethodToClass[$methodName];

        if ((new ObjectType($targetClass))->isSuperTypeOf($callerType)->yes()) {
            return null;
        }

        if ($methodName === 'toBigInteger' && $this->isToScaleWithZeroScale($methodCall->var, $scope)) {
            return null;
        }

        return $methodReflection->getThrowType();
    }

    /**
     * Checks if the expression is a toScale(0, $roundingMode) call where $roundingMode is not Unnecessary.
     *
     * After toScale(0, $roundingMode), the BigDecimal is guaranteed to have scale 0,
     * so toBigInteger() cannot throw {@see \Brick\Math\Exception\RoundingNecessaryException}.
     */
    private function isToScaleWithZeroScale(Expr $expr, Scope $scope): bool
    {
        if (! $expr instanceof MethodCall) {
            return false;
        }

        if (! $expr->name instanceof Identifier || $expr->name->name !== 'toScale') {
            return false;
        }

        $args = $expr->getArgs();

        if (! isset($args[0])) {
            return false;
        }

        $scaleType = $scope->getType($args[0]->value);

        if (! (new ConstantIntegerType(0))->isSuperTypeOf($scaleType)->yes()) {
            return false;
        }

        if (! isset($args[1])) {
            return false;
        }

        $roundingModeType = $scope->getType($args[1]->value);
        $unnecessaryType = new EnumCaseObjectType(RoundingMode::class, 'Unnecessary');

        return $unnecessaryType->isSuperTypeOf($roundingModeType)->no();
    }
}

<?php

declare(strict_types=1);

namespace Brick\Math\PHPStan;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\BigNumber;
use Brick\Math\BigRational;
use Brick\Math\Exception\IntegerOverflowException;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\DynamicMethodThrowTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Narrows the throw type of toBigInteger(), toBigDecimal(), toBigRational(), and toInt().
 *
 * When the caller is already the target type, toBigInteger/toBigDecimal/toBigRational are no-ops and cannot throw.
 * When toInt() is called on {@see BigInteger}, only {@see IntegerOverflowException} can be thrown
 * (no rounding is needed).
 * A successful Brick Math toScale(0) call produces an integral decimal, regardless of its rounding mode.
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
            if (new ObjectType(BigInteger::class)->isSuperTypeOf($callerType)->yes()) {
                return new ObjectType(IntegerOverflowException::class);
            }

            return $methodReflection->getThrowType();
        }

        $targetClass = self::MethodToClass[$methodName];

        if (new ObjectType($targetClass)->isSuperTypeOf($callerType)->yes()) {
            return null;
        }

        if ($methodName === 'toBigInteger' && self::isToScaleWithZeroScale($methodCall->var, $scope)) {
            return null;
        }

        return $methodReflection->getThrowType();
    }

    private static function isToScaleWithZeroScale(Expr $expr, Scope $scope): bool
    {
        if (! $expr instanceof MethodCall) {
            return false;
        }

        if (! $expr->name instanceof Identifier || $expr->name->toLowerString() !== 'toscale') {
            return false;
        }

        // Only the three final Brick implementations guarantee the result's scale.
        $brickNumber = TypeCombinator::union(
            new ObjectType(BigInteger::class),
            new ObjectType(BigDecimal::class),
            new ObjectType(BigRational::class),
        );
        if (! $brickNumber->isSuperTypeOf($scope->getType($expr->var))->yes()) {
            return false;
        }

        // This is the inner call, whose arguments PHPStan has not normalized for us.
        $scale = null;
        foreach ($expr->getArgs() as $position => $arg) {
            if ($arg->unpack) {
                return false;
            }

            if ($arg->name !== null ? $arg->name->name === 'scale' : $position === 0) {
                $scale = $arg;
            }
        }

        return $scale !== null && new ConstantIntegerType(0)->isSuperTypeOf($scope->getType($scale->value))->yes();
    }
}

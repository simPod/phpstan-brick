<?php

declare(strict_types=1);

namespace Brick\Math\PHPStan;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\BigNumber;
use Brick\Math\BigRational;
use Brick\Math\Exception\DivisionByZeroException;
use Brick\Math\Exception\IntegerOverflowException;
use Brick\Math\Exception\InvalidArgumentException;
use Brick\Math\Exception\NegativeNumberException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\DynamicMethodThrowTypeExtension;
use PHPStan\Type\Enum\EnumCaseObjectType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Narrows the throw type of methods with a {@see RoundingMode} parameter.
 *
 * The rounding mode controls rounding of the result, not errors from converting
 * an operand, validating the scale, or performing scale arithmetic.
 */
final class RoundingModeThrowTypeExtension implements DynamicMethodThrowTypeExtension
{
    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        $className = $methodReflection->getDeclaringClass()->getName();
        $methodName = $methodReflection->getName();

        if ($methodName === 'toScale') {
            return $className === BigNumber::class
                || $className === BigInteger::class
                || $className === BigDecimal::class
                || $className === BigRational::class;
        }

        return ($className === BigInteger::class || $className === BigDecimal::class)
            && ($methodName === 'dividedBy' || $methodName === 'sqrt' || $methodName === 'nthRoot');
    }

    public function getThrowTypeFromMethodCall(
        MethodReflection $methodReflection,
        MethodCall $methodCall,
        Scope $scope,
    ): Type|null {
        // PHPStan normalizes named arguments for this call. An unpacked argument
        // can still supply several parameters and is not evidence for narrowing.
        $argTypes = [];
        foreach ($methodCall->getArgs() as $arg) {
            if ($arg->unpack) {
                $argTypes = [];

                break;
            }

            $argTypes[] = $scope->getType($arg->value);
        }

        $className = $methodReflection->getDeclaringClass()->getName();
        $methodName = $methodReflection->getName();
        if ($methodName === 'toScale') {
            return self::narrowToScale($className === BigInteger::class, $argTypes[0] ?? null, $argTypes[1] ?? null);
        }

        $isDecimal = $className === BigDecimal::class;
        if ($methodName === 'nthRoot') {
            return self::narrowNthRoot(
                $isDecimal,
                $argTypes[0] ?? null,
                $isDecimal ? ($argTypes[1] ?? null) : null,
                $argTypes[$isDecimal ? 2 : 1] ?? null,
            );
        }

        $scaleType = $isDecimal ? ($argTypes[$methodName === 'sqrt' ? 0 : 1] ?? null) : null;

        // Decimal division and square root validate the scale before doing any work.
        if ($scaleType !== null && IntegerRangeType::fromInterval(null, -1)->isSuperTypeOf($scaleType)->yes()) {
            return new ObjectType(InvalidArgumentException::class);
        }

        return match ($methodName) {
            'sqrt' => self::narrowSqrt(
                $isDecimal,
                $scaleType,
                $argTypes[$isDecimal ? 1 : 0] ?? null,
            ),
            'dividedBy' => self::narrowDivision(
                $methodReflection,
                $isDecimal,
                $argTypes[0] ?? null,
                $scaleType,
                $argTypes[$isDecimal ? 2 : 1] ?? null,
            ),
            default => $methodReflection->getThrowType(),
        };
    }

    /**
     * Scaling a BigInteger never discards digits; other numbers may require rounding.
     */
    private static function narrowToScale(
        bool $isInteger,
        Type|null $scaleType,
        Type|null $roundingModeType,
    ): Type|null {
        $residual = [];

        if (! self::isNonNegativeScale($scaleType)) {
            $residual[] = new ObjectType(InvalidArgumentException::class);
        }

        if (! $isInteger && ! self::permitsRounding($roundingModeType)) {
            $residual[] = new ObjectType(RoundingNecessaryException::class);
        }

        return $residual === [] ? null : TypeCombinator::union(...$residual);
    }

    private static function narrowSqrt(bool $isDecimal, Type|null $scaleType, Type|null $roundingModeType): Type
    {
        $residual = [new ObjectType(NegativeNumberException::class)];

        if ($isDecimal) {
            if (! self::isNonNegativeScale($scaleType)) {
                $residual[] = new ObjectType(InvalidArgumentException::class);
            }

            // The intermediate scale depends on both the requested and input scale.
            $residual[] = new ObjectType(IntegerOverflowException::class);
        }

        if (! self::permitsRounding($roundingModeType)) {
            $residual[] = new ObjectType(RoundingNecessaryException::class);
        }

        return TypeCombinator::union(...$residual);
    }

    private static function narrowNthRoot(
        bool $isDecimal,
        Type|null $degreeType,
        Type|null $scaleType,
        Type|null $roundingModeType,
    ): Type|null {
        if (
            ($degreeType !== null && IntegerRangeType::fromInterval(null, 0)->isSuperTypeOf($degreeType)->yes())
            || ($scaleType !== null && IntegerRangeType::fromInterval(null, -1)->isSuperTypeOf($scaleType)->yes())
        ) {
            return new ObjectType(InvalidArgumentException::class);
        }

        $residual = [];
        if (
            $degreeType === null || ! IntegerRangeType::fromInterval(1, null)->isSuperTypeOf($degreeType)->yes()
            || ($isDecimal && ! self::isNonNegativeScale($scaleType))
        ) {
            $residual[] = new ObjectType(InvalidArgumentException::class);
        }

        $degree = $degreeType instanceof ConstantIntegerType ? $degreeType->getValue() : null;
        if ($degree === null || $degree % 2 === 0) {
            $residual[] = new ObjectType(NegativeNumberException::class);
        }

        // A decimal root of degree one simply rescales the input; other degrees use checked scale arithmetic.
        if ($isDecimal && $degree !== 1) {
            $residual[] = new ObjectType(IntegerOverflowException::class);
        }

        if (($isDecimal || $degree !== 1) && ! self::permitsRounding($roundingModeType)) {
            $residual[] = new ObjectType(RoundingNecessaryException::class);
        }

        return $residual === [] ? null : TypeCombinator::union(...$residual);
    }

    private static function narrowDivision(
        MethodReflection $methodReflection,
        bool $isDecimal,
        Type|null $divisorType,
        Type|null $scaleType,
        Type|null $roundingModeType,
    ): Type|null {
        $integerOperandType = TypeCombinator::union(new IntegerType(), new ObjectType(BigInteger::class));
        $losslessOperandType = $isDecimal
            ? TypeCombinator::union($integerOperandType, new ObjectType(BigDecimal::class))
            : $integerOperandType;

        if ($divisorType === null || ! $losslessOperandType->isSuperTypeOf($divisorType)->yes()) {
            return $methodReflection->getThrowType();
        }

        $residual = [];
        $zero = new ConstantIntegerType(0);

        if ($isDecimal && ! self::isNonNegativeScale($scaleType)) {
            $residual[] = new ObjectType(InvalidArgumentException::class);
        }

        if (! $divisorType->isInteger()->yes() || ! $zero->isSuperTypeOf($divisorType)->no()) {
            $residual[] = new ObjectType(DivisionByZeroException::class);
        }

        if ($zero->isSuperTypeOf($divisorType)->yes()) {
            return TypeCombinator::union(...$residual);
        }

        // Integer operands have scale zero. Overflow requires adding two non-zero scales.
        if (
            $isDecimal
            && ! $integerOperandType->isSuperTypeOf($divisorType)->yes()
            && ($scaleType === null || ! $zero->isSuperTypeOf($scaleType)->yes())
        ) {
            $residual[] = new ObjectType(IntegerOverflowException::class);
        }

        // Integer division by 1 or -1 is exact; this does not hold at an arbitrary decimal scale.
        if (
            ! self::permitsRounding($roundingModeType)
            && ($isDecimal || ! IntegerRangeType::fromInterval(-1, 1)->isSuperTypeOf($divisorType)->yes())
        ) {
            $residual[] = new ObjectType(RoundingNecessaryException::class);
        }

        return $residual === [] ? null : TypeCombinator::union(...$residual);
    }

    private static function isNonNegativeScale(Type|null $scaleType): bool
    {
        return $scaleType !== null && IntegerRangeType::fromInterval(0, null)->isSuperTypeOf($scaleType)->yes();
    }

    private static function permitsRounding(Type|null $roundingModeType): bool
    {
        $unnecessaryType = new EnumCaseObjectType(RoundingMode::class, 'Unnecessary');

        return $roundingModeType !== null
            && new ObjectType(RoundingMode::class)->isSuperTypeOf($roundingModeType)->yes()
            && $unnecessaryType->isSuperTypeOf($roundingModeType)->no();
    }
}

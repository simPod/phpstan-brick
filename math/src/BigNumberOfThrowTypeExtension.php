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
use Brick\Math\Exception\RandomSourceException;
use Brick\Math\Exception\RoundingNecessaryException;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Accessory\AccessoryNumericStringType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\DynamicStaticMethodThrowTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function array_push;
use function count;
use function in_array;

use const PHP_INT_MIN;

/**
 * Narrows the throw type of {@see BigNumber} static factory methods.
 *
 * When arguments are already instances of the expected {@see BigNumber} subtype,
 * parsing and conversion exceptions cannot occur.
 */
final class BigNumberOfThrowTypeExtension implements DynamicStaticMethodThrowTypeExtension
{
    private const array InheritedFactories = ['of', 'ofNullable', 'min', 'max', 'sum'];

    private const array SupportedMethods = [
        BigNumber::class => self::InheritedFactories,
        BigInteger::class => ['of', 'ofNullable', 'gcdAll', 'lcmAll', 'randomRange'],
        BigDecimal::class => ['of', 'ofNullable', 'ofUnscaledValue'],
        BigRational::class => ['of', 'ofNullable', 'ofFraction'],
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
        if (count($methodCall->getArgs()) < 1) {
            return $methodReflection->getThrowType();
        }

        $methodName = $methodReflection->getName();

        // Methods with residual exceptions after parsing is eliminated.
        if ($methodName === 'ofFraction') {
            return $this->narrowOfFraction($methodCall, $scope, $methodReflection);
        }

        if ($methodName === 'randomRange') {
            return $this->narrowRandomRange($methodCall, $scope, $methodReflection);
        }

        if ($methodName === 'ofUnscaledValue') {
            return $this->narrowOfUnscaledValue($methodCall, $scope, $methodReflection);
        }

        $isInheritedFactory = in_array($methodName, self::InheritedFactories, true);
        $calledOnClass = $isInheritedFactory
            ? self::getCalledOnClass($methodCall, $scope)
            : $methodReflection->getDeclaringClass()->getName();
        $noThrowType = $calledOnClass === null ? null : self::getNoThrowType($calledOnClass);

        if ($noThrowType === null) {
            return $methodReflection->getThrowType();
        }

        $numberType = self::getBuiltInNumberInputType();
        if ($methodName === 'ofNullable') {
            $noThrowType = TypeCombinator::addNull($noThrowType);
            $numberType = TypeCombinator::addNull($numberType);
        }

        // Check ALL arguments (including variadic) match the required type.
        $mayRound = false;

        foreach ($methodCall->getArgs() as $arg) {
            if ($arg->unpack) {
                return $methodReflection->getThrowType();
            }

            $argType = $scope->getType($arg->value);

            if ($noThrowType->isSuperTypeOf($argType)->yes()) {
                continue;
            }

            if ($isInheritedFactory && $numberType->isSuperTypeOf($argType)->yes()) {
                $mayRound = true;

                continue;
            }

            if ($methodName === 'of' || $methodName === 'ofNullable') {
                return $this->narrowNonRationalInput($methodCall, $scope, $methodReflection);
            }

            return $methodReflection->getThrowType();
        }

        return $mayRound ? new ObjectType(RoundingNecessaryException::class) : null;
    }

    private static function getCalledOnClass(StaticCall $methodCall, Scope $scope): string|null
    {
        // Inherited factories convert to the called class, not to BigNumber.
        $class = $methodCall->class;
        if ($class instanceof Name) {
            return $class->isSpecialClassName()
                ? null
                : new ObjectType($scope->resolveName($class))->getClassReflection()?->getName();
        }

        $classType = $scope->getType($class);
        if (new ConstantStringType(BigNumber::class)->isSuperTypeOf($classType)->yes()) {
            return BigNumber::class;
        }

        $objectType = $classType->isClassString()->yes() ? $classType->getClassStringObjectType() : $classType;
        foreach ([BigInteger::class, BigDecimal::class, BigRational::class] as $finalClass) {
            if (new ObjectType($finalClass)->isSuperTypeOf($objectType)->yes()) {
                return $finalClass;
            }
        }

        return null;
    }

    private function narrowOfUnscaledValue(
        StaticCall $methodCall,
        Scope $scope,
        MethodReflection $methodReflection,
    ): Type|null {
        // Arguments are normalized by PHPStan, including named calls.
        $args = $methodCall->getArgs();
        if (! isset($args[0]) || $args[0]->unpack || (isset($args[1]) && $args[1]->unpack)) {
            return $methodReflection->getThrowType();
        }

        $valueType = $scope->getType($args[0]->value);
        if (! self::getBuiltInNumberInputType()->isSuperTypeOf($valueType)->yes()) {
            return $methodReflection->getThrowType();
        }

        $residual = [];
        // The unscaled value is converted to BigInteger, not BigDecimal.
        $integerInputType = TypeCombinator::union(new IntegerType(), new ObjectType(BigInteger::class));
        if (! $integerInputType->isSuperTypeOf($valueType)->yes()) {
            $residual[] = new ObjectType(RoundingNecessaryException::class);
        }

        if (
            isset($args[1])
            && ! new ConstantIntegerType(PHP_INT_MIN)->isSuperTypeOf($scope->getType($args[1]->value))->no()
            && ! new ConstantIntegerType(0)->isSuperTypeOf($valueType)->yes()
        ) {
            $residual[] = new ObjectType(IntegerOverflowException::class);
        }

        return $residual === [] ? null : TypeCombinator::union(...$residual);
    }

    private function narrowOfFraction(
        StaticCall $methodCall,
        Scope $scope,
        MethodReflection $methodReflection,
    ): Type|null {
        $argTypes = [];
        foreach ($methodCall->getArgs() as $arg) {
            $type = $scope->getType($arg->value);
            if (! $arg->unpack) {
                $argTypes[] = $type;

                continue;
            }

            // The parameter positions must be known, including the denominator's.
            $arrays = $type->getConstantArrays();
            if (count($arrays) !== 1 || ! $arrays[0]->isSuperTypeOf($type)->yes()) {
                return $methodReflection->getThrowType();
            }

            $array = $arrays[0];
            if (! $array->isList()->yes() || $array->getOptionalKeys() !== []) {
                return $methodReflection->getThrowType();
            }

            array_push($argTypes, ...$array->getValueTypes());
        }

        if (count($argTypes) !== 2) {
            return $methodReflection->getThrowType();
        }

        // int|BigInteger args cannot cause parsing exceptions in BigInteger::of().
        $safeType = TypeCombinator::union(new IntegerType(), new ObjectType(BigInteger::class));

        foreach ($argTypes as $argType) {
            if (! $safeType->isSuperTypeOf($argType)->yes()) {
                return $methodReflection->getThrowType();
            }
        }

        // DivisionByZeroException is impossible when the denominator is guaranteed non-zero.
        if (self::isNonZero($argTypes[1])) {
            return null;
        }

        return new ObjectType(DivisionByZeroException::class);
    }

    private function narrowRandomRange(
        StaticCall $methodCall,
        Scope $scope,
        MethodReflection $methodReflection,
    ): Type|null {
        $args = $methodCall->getArgs();
        if (! isset($args[0], $args[1]) || $args[0]->unpack || $args[1]->unpack) {
            return $methodReflection->getThrowType();
        }

        $minType = $scope->getType($args[0]->value);
        $maxType = $scope->getType($args[1]->value);
        $safeType = TypeCombinator::union(new IntegerType(), new ObjectType(BigInteger::class));
        if (! $safeType->isSuperTypeOf($minType)->yes() || ! $safeType->isSuperTypeOf($maxType)->yes()) {
            return $methodReflection->getThrowType();
        }

        $randomError = new ObjectType(RandomSourceException::class);
        if (
            $minType instanceof ConstantIntegerType && $maxType instanceof ConstantIntegerType
            && $minType->getValue() <= $maxType->getValue()
        ) {
            return $randomError;
        }

        return TypeCombinator::union(new ObjectType(InvalidArgumentException::class), $randomError);
    }

    /**
     * When all arguments are int or numeric-string, the input cannot contain "/",
     * so {@see DivisionByZeroException} is impossible.
     */
    private function narrowNonRationalInput(
        StaticCall $methodCall,
        Scope $scope,
        MethodReflection $methodReflection,
    ): Type|null {
        $nonRationalType = TypeCombinator::union(
            new IntegerType(),
            new AccessoryNumericStringType(),
            new ObjectType(BigNumber::class),
        );

        foreach ($methodCall->getArgs() as $arg) {
            $argType = $scope->getType($arg->value);

            if (! $nonRationalType->isSuperTypeOf($argType)->yes()) {
                return $methodReflection->getThrowType();
            }
        }

        $defaultThrowType = $methodReflection->getThrowType();

        if ($defaultThrowType === null) {
            return null;
        }

        return TypeCombinator::remove($defaultThrowType, new ObjectType(DivisionByZeroException::class));
    }

    private static function isNonZero(Type $type): bool
    {
        if (! new IntegerType()->isSuperTypeOf($type)->yes()) {
            return false;
        }

        return new ConstantIntegerType(0)->isSuperTypeOf($type)->no();
    }

    private static function getBuiltInNumberInputType(): Type
    {
        return TypeCombinator::union(
            new IntegerType(),
            new ObjectType(BigInteger::class),
            new ObjectType(BigDecimal::class),
            new ObjectType(BigRational::class),
        );
    }

    private static function getNoThrowType(string $calledOnClass): Type|null
    {
        $intType = new IntegerType();

        return match ($calledOnClass) {
            BigNumber::class => TypeCombinator::union($intType, new ObjectType(BigNumber::class)),
            BigInteger::class => TypeCombinator::union($intType, new ObjectType(BigInteger::class)),
            BigDecimal::class => TypeCombinator::union(
                $intType,
                new ObjectType(BigInteger::class),
                new ObjectType(BigDecimal::class),
            ),
            BigRational::class => TypeCombinator::union($intType, new ObjectType(BigNumber::class)),
            default => null,
        };
    }
}

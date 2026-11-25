<?php

declare(strict_types=1);

namespace Brick\Math\PHPStan\Tests\Data;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\BigNumber;
use Brick\Math\BigRational;
use Brick\Math\Exception\DivisionByZeroException;
use Brick\Math\Exception\IntegerOverflowException;
use Brick\Math\Exception\InvalidArgumentException;
use Brick\Math\Exception\RandomSourceException;
use Brick\Math\Exception\RoundingNecessaryException;
use PHPStan\TrinaryLogic;

use function PHPStan\Testing\assertVariableCertainty;

use const PHP_INT_MIN;

final class FactoryThrowTypes
{
    public function ofWithBigInteger(BigInteger $number): void
    {
        try {
            $result = BigInteger::of($number);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofWithInt(): void
    {
        try {
            $result = BigDecimal::of(42);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofWithString(string $number): void
    {
        try {
            $result = BigInteger::of($number);
        } catch (DivisionByZeroException $zero) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createNo(), $zero);
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function integerOfWithDecimal(BigDecimal $number): void
    {
        try {
            $result = BigInteger::of($number);
        } catch (RoundingNecessaryException $rounding) {
            $result = BigInteger::zero();
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $rounding);
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function decimalOfNullableWithRational(BigRational|null $number): void
    {
        try {
            $result = BigDecimal::ofNullable($number);
        } catch (RoundingNecessaryException $rounding) {
            $result = null;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $rounding);
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofUnscaledValueWithDecimal(BigDecimal $number): void
    {
        try {
            $result = BigDecimal::ofUnscaledValue($number);
        } catch (RoundingNecessaryException $rounding) {
            $result = BigDecimal::zero();
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $rounding);
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofUnscaledValueWithMinimumScale(): void
    {
        try {
            $result = BigDecimal::ofUnscaledValue(scale: PHP_INT_MIN, value: 1);
        } catch (IntegerOverflowException $overflow) {
            $result = BigDecimal::zero();
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $overflow);
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofFractionWithNonZeroDenominator(): void
    {
        try {
            $result = BigRational::ofFraction(1, 2);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofFractionWithMaybeZeroDenominator(int $denominator): void
    {
        try {
            $result = BigRational::ofFraction(1, $denominator);
        } catch (DivisionByZeroException $zero) {
            $result = BigRational::zero();
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $zero);
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofFractionWithUnpackedArguments(): void
    {
        try {
            $result = BigRational::ofFraction(...[0, 2]);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofFractionWithUnpackedZeroDenominator(): void
    {
        try {
            BigRational::ofFraction(...[2, 0]);
        } catch (DivisionByZeroException $zero) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $zero);
        }
    }

    public function randomRangeWithOrderedBounds(): void
    {
        try {
            $result = BigInteger::randomRange(0, 1);
        } catch (RandomSourceException $randomSource) {
            $result = BigInteger::zero();
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $randomSource);
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function randomRangeWithReversedBounds(): void
    {
        try {
            BigInteger::randomRange(1, 0);
        } catch (InvalidArgumentException $invalidBounds) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $invalidBounds);
        }
    }

    public function minWithSameType(BigInteger $a, BigInteger $b): void
    {
        try {
            $result = BigInteger::min($a, $b);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function minWithInt(): void
    {
        try {
            $result = BigInteger::min(1, 2, 3);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function minWithString(string $a, string $b): void
    {
        try {
            $result = BigInteger::min($a, $b);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function minOnBigNumberWithMixedBigNumber(BigInteger $a, BigDecimal $b): void
    {
        try {
            $result = BigNumber::min($a, $b);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function integerMinWithDecimal(BigDecimal $number): void
    {
        try {
            BigInteger::min(1, $number);
        } catch (RoundingNecessaryException $rounding) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $rounding);
        }
    }

    public function maxWithSameType(BigDecimal $a, BigDecimal $b): void
    {
        try {
            $result = BigDecimal::max($a, $b);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function maxWithString(string $a): void
    {
        try {
            $result = BigDecimal::max($a);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function integerMaxWithDecimal(BigDecimal $number): void
    {
        try {
            BigInteger::max(1, $number);
        } catch (RoundingNecessaryException $rounding) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $rounding);
        }
    }

    public function sumWithSameType(BigRational $a, BigRational $b): void
    {
        try {
            $result = BigRational::sum($a, $b);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function sumWithInt(): void
    {
        try {
            $result = BigInteger::sum(1, 2, 3);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function sumWithString(string $a, string $b): void
    {
        try {
            $result = BigInteger::sum($a, $b);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function integerSumWithDecimal(BigDecimal $number): void
    {
        try {
            BigInteger::sum(1, $number);
        } catch (RoundingNecessaryException $rounding) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $rounding);
        }
    }

    public function gcdAllWithSameType(BigInteger $a, BigInteger $b, BigInteger $c): void
    {
        try {
            $result = BigInteger::gcdAll($a, $b, $c);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function gcdAllWithInt(): void
    {
        try {
            $result = BigInteger::gcdAll(12, 18, 24);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function gcdAllWithString(string $a, string $b): void
    {
        try {
            $result = BigInteger::gcdAll($a, $b);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function lcmAllWithSameType(BigInteger $a, BigInteger $b): void
    {
        try {
            $result = BigInteger::lcmAll($a, $b);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function lcmAllWithString(string $a): void
    {
        try {
            $result = BigInteger::lcmAll($a);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }
}

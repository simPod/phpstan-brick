<?php

declare(strict_types=1);

namespace Brick\Math\PHPStan\Tests\Data;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\BigRational;
use Brick\Math\Exception\DivisionByZeroException;
use Brick\Math\Exception\IntegerOverflowException;
use Brick\Math\Exception\InvalidArgumentException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use PHPStan\TrinaryLogic;

use function PHPStan\Testing\assertVariableCertainty;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

final class MissedExceptions
{
    public function integerDivisionDefaultRounding(BigInteger $number): void
    {
        try {
            $number->dividedBy(2);
        } catch (RoundingNecessaryException $rounding) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $rounding);
        }
    }

    public function decimalDivisionExplicitUnnecessary(BigDecimal $number): void
    {
        try {
            $number->dividedBy(roundingMode: RoundingMode::Unnecessary, that: 3, scale: 0);
        } catch (RoundingNecessaryException $rounding) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $rounding);
        }
    }

    public function decimalDivisionUnknownScale(BigDecimal $number, int $scale): void
    {
        try {
            $result = $number->dividedBy(3, $scale, RoundingMode::Down);
        } catch (InvalidArgumentException $invalidScale) {
            $result = $number;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $invalidScale);
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function decimalRootUncertainRounding(BigDecimal $number, bool $round): void
    {
        try {
            $number->sqrt(0, $round ? RoundingMode::Down : RoundingMode::Unnecessary);
        } catch (RoundingNecessaryException $rounding) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $rounding);
        }
    }

    public function decimalRootUnknownScale(BigDecimal $number, int $scale): void
    {
        try {
            $number->sqrt(roundingMode: RoundingMode::Down, scale: $scale);
        } catch (InvalidArgumentException $invalidScale) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $invalidScale);
        }
    }

    public function decimalRootScaleOverflow(BigDecimal $number): void
    {
        try {
            $number->sqrt(0, RoundingMode::Down);
        } catch (IntegerOverflowException $overflow) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $overflow);
        }
    }

    public function decimalDivisionScaleOverflow(BigDecimal $number, BigDecimal $divisor): void
    {
        try {
            $number->dividedBy($divisor, PHP_INT_MAX, RoundingMode::Down);
        } catch (IntegerOverflowException $overflow) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $overflow);
        }
    }

    public function decimalExactDivisionScaleOverflow(BigDecimal $number, BigDecimal $divisor): void
    {
        try {
            $number->dividedByExact($divisor);
        } catch (IntegerOverflowException $overflow) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $overflow);
        }
    }

    public function decimalMultiplicationScaleOverflow(BigDecimal $number, BigDecimal $multiplier): void
    {
        try {
            $number->multipliedBy($multiplier);
        } catch (IntegerOverflowException $overflow) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $overflow);
        }
    }

    public function decimalMultiplicationWithInteger(BigDecimal $number, BigInteger $multiplier): void
    {
        try {
            $result = $number->multipliedBy($multiplier);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function integerFactoryWithFraction(BigDecimal $number): void
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

    public function nullableDecimalFactoryWithRational(?BigRational $number): void
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

    public function staticAggregatesWithFraction(BigDecimal $number): void
    {
        try {
            BigInteger::min(1, $number);
        } catch (RoundingNecessaryException $roundingMin) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $roundingMin);
        }

        try {
            BigInteger::max(1, $number);
        } catch (RoundingNecessaryException $roundingMax) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $roundingMax);
        }

        try {
            BigInteger::sum(1, $number);
        } catch (RoundingNecessaryException $roundingSum) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $roundingSum);
        }
    }

    public function decimalFactoryWithFractionalUnscaledValue(BigDecimal $number): void
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

    public function decimalFactoryWithMinimumScale(): void
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

    public function modPowWithZeroModulus(BigInteger $number): void
    {
        try {
            $number->modPow(1, 0);
        } catch (DivisionByZeroException $zero) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $zero);
        }
    }

    public function namedModPowWithZeroModulus(BigInteger $number): void
    {
        try {
            $number->modPow(modulus: 0, exponent: 1);
        } catch (DivisionByZeroException $zero) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $zero);
        }
    }

    public function namedModPowWithZeroExponent(BigInteger $number): void
    {
        try {
            $result = $number->modPow(modulus: 3, exponent: 0);
        } catch (InvalidArgumentException) {
            $result = $number;
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function unrelatedToScale(OtherScaler $scaler): void
    {
        try {
            $scaler->toScale(0, RoundingMode::Down)->toBigInteger();
        } catch (RoundingNecessaryException $rounding) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $rounding);
        }
    }

    public function namedToScaleZero(BigDecimal $number): void
    {
        try {
            $result = $number->toScale(roundingMode: RoundingMode::Down, scale: 0)->toBigInteger();
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }
}

final class OtherScaler
{
    public function toScale(int $scale, RoundingMode $roundingMode): BigDecimal
    {
        return BigDecimal::ofUnscaledValue(15, 1);
    }
}

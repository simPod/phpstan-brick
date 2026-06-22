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
use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NegativeNumberException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use PHPStan\TrinaryLogic;

use function PHPStan\Testing\assertVariableCertainty;

class BigNumberThrowTypes
{
    // --- Factory methods ---

    public function ofWithBigInteger(BigInteger $a): void
    {
        try {
            $result = BigInteger::of($a);
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

    public function ofWithString(string $s): void
    {
        try {
            $result = BigInteger::of($s);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function ofWithStringCatchingDivisionByZero(string $s): void
    {
        try {
            $result = BigInteger::of($s);
        } catch (DivisionByZeroException) {
            $result = BigInteger::zero();
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
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

    public function ofFractionWithNonZeroDenominatorCatchingDivisionByZero(): void
    {
        try {
            $result = BigRational::ofFraction(1, 2);
        } catch (DivisionByZeroException) {
            $result = BigRational::zero();
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofFractionWithMaybeZeroDenominator(int $denominator): void
    {
        try {
            $result = BigRational::ofFraction(1, $denominator);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function ofFractionWithMaybeZeroDenominatorCatchingDivisionByZero(int $denominator): void
    {
        try {
            $result = BigRational::ofFraction(1, $denominator);
        } catch (DivisionByZeroException) {
            $result = BigRational::zero();
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    // --- Conversion methods ---

    public function toBigIntegerOnBigInteger(BigInteger $a): void
    {
        try {
            $result = $a->toBigInteger();
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function toBigDecimalOnBigRational(BigRational $a): void
    {
        try {
            $result = $a->toBigDecimal();
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function toBigDecimalOnBigRationalCatchingRounding(BigRational $a): void
    {
        try {
            $result = $a->toBigDecimal();
        } catch (RoundingNecessaryException) {
            $result = BigDecimal::zero();
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function toIntOnBigIntegerCatchingOverflow(BigInteger $a): void
    {
        try {
            $result = $a->toInt();
        } catch (IntegerOverflowException) {
            $result = 0;
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function toIntOnBigDecimalCatchingOverflow(BigDecimal $a): void
    {
        try {
            $result = $a->toInt();
        } catch (IntegerOverflowException) {
            $result = 0;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    // --- Operation methods ---

    public function plusWithSameType(BigInteger $a, BigInteger $b): void
    {
        try {
            $result = $a->plus($b);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function plusWithInt(BigDecimal $a): void
    {
        try {
            $result = $a->plus(5);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function plusWithString(BigInteger $a, string $s): void
    {
        try {
            $result = $a->plus($s);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function plusWithStringCatchingParsingException(BigInteger $a, string $s): void
    {
        try {
            $result = $a->plus($s);
        } catch (MathException) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function quotientMayThrowDivisionByZero(BigInteger $a, BigInteger $b): void
    {
        try {
            $result = $a->quotient($b);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function quotientMayThrowDivisionByZeroCatchingDivisionByZero(BigInteger $a, BigInteger $b): void
    {
        try {
            $result = $a->quotient($b);
        } catch (DivisionByZeroException) {
            $result = BigInteger::zero();
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    /** @param int<1, max> $divisor */
    public function quotientWithNonZeroDivisor(BigInteger $a, int $divisor): void
    {
        try {
            $result = $a->quotient($divisor);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function quotientWithStringCatchingDivisionByZero(BigInteger $a, string $divisor): void
    {
        try {
            $result = $a->quotient($divisor);
        } catch (DivisionByZeroException) {
            $result = BigInteger::zero();
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function compareToWithBigNumber(BigInteger $a, BigDecimal $b): void
    {
        try {
            $result = $a->compareTo($b);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    // --- toScale() + toBigInteger() chain ---

    public function toBigIntegerAfterToScaleZero(BigDecimal $a): void
    {
        try {
            $result = $a->toScale(0, RoundingMode::HalfUp)->toBigInteger();
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function toBigIntegerAfterToScaleZeroDown(BigDecimal $a): void
    {
        try {
            $result = $a->toScale(0, RoundingMode::Down)->toBigInteger();
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function toBigIntegerAfterToScaleNonZero(BigDecimal $a): void
    {
        try {
            $result = $a->toScale(2, RoundingMode::HalfUp)->toBigInteger();
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function toBigIntegerAfterToScaleUnnecessary(BigDecimal $a): void
    {
        try {
            $result = $a->toScale(0, RoundingMode::Unnecessary)->toBigInteger();
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function toBigIntegerAfterToScaleNoRoundingMode(BigDecimal $a): void
    {
        try {
            $result = $a->toScale(0)->toBigInteger();
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    // --- Rounding mode methods ---

    /** @param int<0, max> $scale */
    public function toScaleWithSafeArgs(BigDecimal $a, int $scale): void
    {
        try {
            $result = $a->toScale($scale, RoundingMode::Down);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function toScaleWithUnnecessary(BigDecimal $a): void
    {
        try {
            $result = $a->toScale(2, RoundingMode::Unnecessary);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    /** @param int<0, max> $scale */
    public function toScaleWithNonNegativeScaleCatchingRounding(BigDecimal $a, int $scale): void
    {
        try {
            $result = $a->toScale($scale, RoundingMode::Unnecessary);
        } catch (RoundingNecessaryException) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function toScaleWithMaybeNegativeScaleCatchingRounding(BigDecimal $a, int $scale): void
    {
        try {
            $result = $a->toScale($scale, RoundingMode::Unnecessary);
        } catch (RoundingNecessaryException) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function toScaleWithSafeRoundingCatchingInvalidScale(BigDecimal $a, int $scale): void
    {
        try {
            $result = $a->toScale($scale, RoundingMode::Down);
        } catch (InvalidArgumentException) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function toScaleWithMaybeNegativeScaleCatchingInvalidScale(BigDecimal $a, int $scale): void
    {
        try {
            $result = $a->toScale($scale, RoundingMode::Down);
        } catch (InvalidArgumentException) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function dividedByWithSafeRounding(BigInteger $a, BigInteger $b): void
    {
        try {
            $result = $a->dividedBy($b, RoundingMode::Down);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function dividedByWithSafeRoundingCatchingNonRoundingExceptions(BigInteger $a, BigInteger $b): void
    {
        try {
            $result = $a->dividedBy($b, RoundingMode::Down);
        } catch (MathException | DivisionByZeroException) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    /** @param int<1, max> $divisor */
    public function dividedByWithSafeRoundingAndNonZero(BigInteger $a, int $divisor): void
    {
        try {
            $result = $a->dividedBy($divisor, RoundingMode::Down);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function dividedByWithMaybeZeroIntCatchingDivisionByZero(BigInteger $a, int $divisor): void
    {
        try {
            $result = $a->dividedBy($divisor, RoundingMode::Down);
        } catch (DivisionByZeroException) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function sqrtWithSafeRoundingCatchingNegativeNumber(BigDecimal $a): void
    {
        try {
            $result = $a->sqrt(2, RoundingMode::Down);
        } catch (NegativeNumberException) {
            $result = BigDecimal::zero();
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    /** Static variadic methods (min, max, sum, gcdAll, lcmAll) */

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

    public function minOnBigNumberWithMixedBigNumber(BigInteger $a, BigDecimal $b): void
    {
        try {
            $result = BigNumber::min($a, $b);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }
}

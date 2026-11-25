<?php

declare(strict_types=1);

namespace Brick\Math\PHPStan\Tests\Data;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\Exception\DivisionByZeroException;
use Brick\Math\Exception\IntegerOverflowException;
use Brick\Math\Exception\InvalidArgumentException;
use Brick\Math\Exception\NegativeNumberException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use PHPStan\TrinaryLogic;

use function PHPStan\Testing\assertVariableCertainty;

use const PHP_INT_MAX;

final class RoundingThrowTypes
{
    /** @param int<0, max> $scale */
    public function toScaleWithSafeArgs(BigDecimal $a, int $scale): void
    {
        try {
            $result = $a->toScale($scale, RoundingMode::Down);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    /** @param int<0, max> $scale */
    public function toScaleWithoutRounding(BigDecimal $a, int $scale): void
    {
        try {
            $result = $a->toScale($scale, RoundingMode::Unnecessary);
        } catch (RoundingNecessaryException $rounding) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $rounding);
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function toScaleWithMaybeNegativeScaleWithoutRounding(BigDecimal $a, int $scale): void
    {
        try {
            $result = $a->toScale($scale, RoundingMode::Unnecessary);
        } catch (RoundingNecessaryException) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function toScaleWithMaybeNegativeScaleAndRounding(BigDecimal $a, int $scale): void
    {
        try {
            $result = $a->toScale($scale, RoundingMode::Down);
        } catch (InvalidArgumentException $invalidScale) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $invalidScale);
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function integerDivisionWithRounding(BigInteger $a, BigInteger $divisor): void
    {
        try {
            $result = $a->dividedBy($divisor, RoundingMode::Down);
        } catch (DivisionByZeroException $zero) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $zero);
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    /** @param int<1, max> $divisor */
    public function integerDivisionByNonZeroWithRounding(BigInteger $a, int $divisor): void
    {
        try {
            $result = $a->dividedBy($divisor, RoundingMode::Down);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function integerDivisionByMaybeZeroIntWithRounding(BigInteger $a, int $divisor): void
    {
        try {
            $result = $a->dividedBy($divisor, RoundingMode::Down);
        } catch (DivisionByZeroException $zero) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $zero);
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function integerDivisionWithDefaultRounding(BigInteger $a): void
    {
        try {
            $a->dividedBy(2);
        } catch (RoundingNecessaryException $rounding) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $rounding);
        }
    }

    public function decimalDivisionWithoutRounding(BigDecimal $a): void
    {
        try {
            $a->dividedBy(roundingMode: RoundingMode::Unnecessary, that: 3, scale: 0);
        } catch (RoundingNecessaryException $rounding) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $rounding);
        }
    }

    public function decimalDivisionWithMaybeNegativeScale(BigDecimal $a, int $scale): void
    {
        try {
            $result = $a->dividedBy(3, $scale, RoundingMode::Down);
        } catch (InvalidArgumentException $invalidScale) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $invalidScale);
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function decimalDivisionWithMaximumScale(BigDecimal $a, BigDecimal $divisor): void
    {
        try {
            $a->dividedBy($divisor, PHP_INT_MAX, RoundingMode::Down);
        } catch (IntegerOverflowException $overflow) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $overflow);
        }
    }

    public function decimalSquareRootWithRounding(BigDecimal $a): void
    {
        try {
            $result = $a->sqrt(2, RoundingMode::Down);
        } catch (NegativeNumberException $negativeNumber) {
            $result = BigDecimal::zero();
        } catch (IntegerOverflowException $overflow) {
            $result = BigDecimal::zero();
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $negativeNumber);
            assertVariableCertainty(TrinaryLogic::createMaybe(), $overflow);
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function decimalSquareRootWithUncertainRounding(BigDecimal $a, bool $round): void
    {
        try {
            $a->sqrt(0, $round ? RoundingMode::Down : RoundingMode::Unnecessary);
        } catch (RoundingNecessaryException $rounding) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $rounding);
        }
    }

    public function decimalSquareRootWithMaybeNegativeScale(BigDecimal $a, int $scale): void
    {
        try {
            $a->sqrt(roundingMode: RoundingMode::Down, scale: $scale);
        } catch (InvalidArgumentException $invalidScale) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $invalidScale);
        }
    }
}

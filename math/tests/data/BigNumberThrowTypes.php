<?php

declare(strict_types=1);

namespace Brick\Math\PHPStan\Tests\Data;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\BigNumber;
use Brick\Math\BigRational;
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

    public function quotientMayThrowDivisionByZero(BigInteger $a, BigInteger $b): void
    {
        try {
            $result = $a->quotient($b);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
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

    public function compareToWithBigNumber(BigInteger $a, BigDecimal $b): void
    {
        try {
            $result = $a->compareTo($b);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
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

    public function dividedByWithSafeRounding(BigInteger $a, BigInteger $b): void
    {
        try {
            $result = $a->dividedBy($b, RoundingMode::Down);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
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
}

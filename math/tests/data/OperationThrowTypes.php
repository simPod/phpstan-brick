<?php

declare(strict_types=1);

namespace Brick\Math\PHPStan\Tests\Data;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\Exception\DivisionByZeroException;
use Brick\Math\Exception\IntegerOverflowException;
use Brick\Math\Exception\InvalidArgumentException;
use Brick\Math\Exception\MathException;
use PHPStan\TrinaryLogic;

use function PHPStan\Testing\assertVariableCertainty;

final class OperationThrowTypes
{
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

    public function plusWithString(BigInteger $a, string $number): void
    {
        try {
            $result = $a->plus($number);
        } catch (MathException $invalidNumber) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $invalidNumber);
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function multipliedByWithDecimal(BigDecimal $a, BigDecimal $multiplier): void
    {
        try {
            $a->multipliedBy($multiplier);
        } catch (IntegerOverflowException $overflow) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $overflow);
        }
    }

    public function multipliedByWithInteger(BigDecimal $a, BigInteger $multiplier): void
    {
        try {
            $result = $a->multipliedBy($multiplier);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function quotientWithInteger(BigInteger $a, BigInteger $divisor): void
    {
        try {
            $result = $a->quotient($divisor);
        } catch (DivisionByZeroException $zero) {
            $result = BigInteger::zero();
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $zero);
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

    public function quotientWithString(BigInteger $a, string $divisor): void
    {
        try {
            $result = $a->quotient($divisor);
        } catch (DivisionByZeroException) {
            $result = BigInteger::zero();
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function dividedByExactWithDecimal(BigDecimal $a, BigDecimal $divisor): void
    {
        try {
            $a->dividedByExact($divisor);
        } catch (IntegerOverflowException $overflow) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $overflow);
        }
    }

    public function modPowWithZeroModulus(BigInteger $a): void
    {
        try {
            $a->modPow(1, 0);
        } catch (DivisionByZeroException $zero) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $zero);
        }
    }

    public function modPowWithNamedZeroModulus(BigInteger $a): void
    {
        try {
            $a->modPow(modulus: 0, exponent: 1);
        } catch (DivisionByZeroException $zero) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $zero);
        }
    }

    public function modPowWithZeroExponent(BigInteger $a): void
    {
        try {
            $result = $a->modPow(modulus: 3, exponent: 0);
        } catch (InvalidArgumentException) {
            $result = $a;
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
}

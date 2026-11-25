<?php

declare(strict_types=1);

namespace Brick\Math\PHPStan\Tests\Data;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\BigRational;
use Brick\Math\Exception\IntegerOverflowException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use PHPStan\TrinaryLogic;

use function PHPStan\Testing\assertVariableCertainty;

final class ConversionThrowTypes
{
    public function toBigIntegerOnBigInteger(BigInteger $a): void
    {
        try {
            $result = $a->toBigInteger();
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function toBigIntegerAfterToScaleZero(BigDecimal $a): void
    {
        try {
            $result = $a->toScale(0, RoundingMode::HalfUp)->toBigInteger();
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function toBigIntegerAfterNamedToScaleZero(BigDecimal $a): void
    {
        try {
            $result = $a->toScale(roundingMode: RoundingMode::Down, scale: 0)->toBigInteger();
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

    public function toBigIntegerAfterToScaleWithoutRounding(BigDecimal $a): void
    {
        try {
            $result = $a->toScale(0)->toBigInteger();
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function toBigIntegerAfterUnrelatedToScale(OtherScaler $scaler): void
    {
        try {
            $scaler->toScale(0, RoundingMode::Down)->toBigInteger();
        } catch (RoundingNecessaryException $rounding) {
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $rounding);
        }
    }

    public function toBigDecimalOnBigRational(BigRational $a): void
    {
        try {
            $result = $a->toBigDecimal();
        } catch (RoundingNecessaryException $rounding) {
            $result = BigDecimal::zero();
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $rounding);
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function toIntOnBigIntegerCatchingOverflow(BigInteger $a): void
    {
        try {
            $result = $a->toInt();
        } catch (IntegerOverflowException $overflow) {
            $result = 0;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $overflow);
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
}

final class OtherScaler
{
    public function toScale(int $scale, RoundingMode $roundingMode): BigDecimal
    {
        return BigDecimal::ofUnscaledValue(15, 1);
    }
}

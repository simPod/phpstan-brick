<?php

declare(strict_types=1);

namespace Brick\Money\PHPStan\Tests\Data;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Brick\Money\Context\CustomContext;
use Brick\Money\Currency;
use Brick\Money\Money;
use Brick\Money\RationalMoney;
use PHPStan\TrinaryLogic;

use function PHPStan\Testing\assertVariableCertainty;

class MoneyThrowTypes
{
    // --- Factory methods ---

    public function ofIntWithKnownCurrency(): void
    {
        try {
            $result = Money::of(100, 'USD');
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofIntWithCurrencyInstance(Currency $currency): void
    {
        try {
            $result = Money::of(100, $currency);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofStringWithKnownCurrency(string $amount): void
    {
        try {
            $result = Money::of($amount, 'USD');
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function zeroWithKnownCurrency(): void
    {
        try {
            $result = Money::zero('EUR');
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function zeroWithUnknownCurrency(string $code): void
    {
        try {
            $result = Money::zero($code);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function rationalMoneyOfWithSafeArgs(Currency $currency): void
    {
        try {
            $result = RationalMoney::of(100, $currency);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofZeroWithContext(): void
    {
        try {
            $result = Money::of(0, 'EUR', new CustomContext(4, 1));
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofZeroWithCurrencyInstance(Currency $currency): void
    {
        try {
            $result = Money::of(0, $currency);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofIntWithCustomContextStep1(): void
    {
        try {
            $result = Money::of(500, 'EUR', new CustomContext(4, 1));
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofIntWithCustomContextDefaultStep(): void
    {
        try {
            $result = Money::of(500, 'EUR', new CustomContext(4));
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofIntWithCustomContextStepGreaterThan1(): void
    {
        try {
            $result = Money::of(500, 'EUR', new CustomContext(2, 5));
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function ofStringWithCustomContext(string $amount): void
    {
        try {
            $result = Money::of($amount, 'EUR', new CustomContext(4, 1));
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    // --- Comparison methods ---

    public function compareToWithInt(Money $a): void
    {
        try {
            $result = $a->compareTo(100);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function compareToWithMoney(Money $a, Money $b): void
    {
        try {
            $result = $a->compareTo($b);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    // --- Arithmetic methods ---

    public function plusWithSafeRounding(Money $a): void
    {
        try {
            $result = $a->plus(10, RoundingMode::Down);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function dividedByWithSafeRounding(Money $a): void
    {
        try {
            $result = $a->dividedBy(3, RoundingMode::Down);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function dividedByWithUnnecessaryRounding(Money $a): void
    {
        try {
            $result = $a->dividedBy(3, RoundingMode::Unnecessary);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    // --- RationalMoney arithmetic ---

    public function rationalPlusWithInt(RationalMoney $a): void
    {
        try {
            $result = $a->plus(10);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function rationalDividedByWithString(RationalMoney $a, string $s): void
    {
        try {
            $result = $a->dividedBy($s);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    // --- Rounding mode methods ---

    public function toContextWithSafeRounding(Money $a): void
    {
        try {
            $result = $a->toContext($a->getContext(), RoundingMode::Down);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    // --- MoneyBag ---

    public function getMoneyWithKnownCurrency(\Brick\Money\MoneyBag $bag): void
    {
        try {
            $result = $bag->getMoney('USD');
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function getMoneyWithUnknownCurrency(\Brick\Money\MoneyBag $bag, string $code): void
    {
        try {
            $result = $bag->getMoney($code);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }
}

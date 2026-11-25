<?php

declare(strict_types=1);

namespace Brick\Money\PHPStan\Tests\Data;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\Exception\DivisionByZeroException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Math\RoundingMode;
use Brick\Money\Context\AutoContext;
use Brick\Money\Context\CashContext;
use Brick\Money\Context\CustomContext;
use Brick\Money\Context\DefaultContext;
use Brick\Money\Currency;
use Brick\Money\CurrencyConverter;
use Brick\Money\Exception\ContextException;
use Brick\Money\Exception\ContextMismatchException;
use Brick\Money\Exception\CurrencyMismatchException;
use Brick\Money\Exception\ExchangeRateException;
use Brick\Money\Exception\InvalidArgumentException;
use Brick\Money\Exception\UnknownCurrencyException;
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

    public function ofStringWithKnownCurrencyCatchingCurrencyAndRoundingExceptions(string $amount): void
    {
        try {
            $result = Money::of($amount, 'USD');
        } catch (UnknownCurrencyException | RoundingNecessaryException) {
            $result = Money::zero('USD');
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function ofIntWithUnknownCurrencyCatchingCurrencyException(string $code): void
    {
        try {
            $result = Money::of(100, $code);
        } catch (UnknownCurrencyException) {
            $result = Money::zero('USD');
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofStringWithKnownCurrencyCatchingNumberAndRoundingExceptions(string $amount): void
    {
        try {
            $result = Money::of($amount, 'USD');
        } catch (NumberFormatException | RoundingNecessaryException) {
            $result = Money::zero('USD');
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofStringWithUnknownCurrencyCatchingNumberAndRoundingExceptions(string $amount, string $code): void
    {
        try {
            $result = Money::of($amount, $code);
        } catch (NumberFormatException | RoundingNecessaryException) {
            $result = Money::zero('USD');
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

    public function zeroWithIncompatibleCashContext(): void
    {
        $context = new CashContext(3);

        try {
            $result = Money::zero('USD', $context);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
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

    public function zeroWithUnknownCurrencyCatchingCurrencyException(string $code): void
    {
        try {
            $result = Money::zero($code);
        } catch (UnknownCurrencyException) {
            $result = Money::zero('USD');
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofMinorIntWithKnownCurrency(): void
    {
        try {
            $result = Money::ofMinor(500, 'USD');
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofMinorIntWithCurrencyInstance(Currency $currency): void
    {
        try {
            $result = Money::ofMinor(500, $currency);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofMinorIntWithCustomContext(): void
    {
        try {
            $result = Money::ofMinor(500, 'USD', new CustomContext(4, 1));
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function ofMinorStringWithKnownCurrency(string $amount): void
    {
        try {
            $result = Money::ofMinor($amount, 'USD');
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

    public function rationalMoneyOfWithUnsafeAmountCatchingCurrencyException(string $amount, Currency $currency): void
    {
        try {
            $result = RationalMoney::of($amount, $currency);
        } catch (UnknownCurrencyException) {
            $result = RationalMoney::of(100, 'USD');
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function rationalMoneyOfWithUnknownCurrencyCatchingNumberException(string $code): void
    {
        try {
            $result = RationalMoney::of(100, $code);
        } catch (NumberFormatException) {
            $result = RationalMoney::of(100, 'USD');
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
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

    public function ofIntWithReorderedCustomContextArguments(): void
    {
        try {
            // Keep step before scale to exercise named argument binding.
            $result = Money::of(1, 'EUR', new CustomContext(step: 100, scale: 1));
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function ofIntWithUnpackedCustomContextArguments(): void
    {
        try {
            $result = Money::of(1, 'EUR', new CustomContext(...[1, 100]));
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

    public function ofWithRoundingMode(BigDecimal $amount): void
    {
        try {
            $result = Money::of($amount, 'USD', roundingMode: RoundingMode::HalfUp);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofWithWhitespaceInNumericString(): void
    {
        try {
            Money::of(' 1', 'USD', roundingMode: RoundingMode::Down);
        } catch (NumberFormatException) {
            $caught = true;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $caught);
        }
    }

    public function ofWithPlainDecimalAndRoundingMode(): void
    {
        try {
            $result = Money::of('-1.001', 'USD', roundingMode: RoundingMode::Down);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofWithZeroDenominator(): void
    {
        try {
            $result = Money::of('1/0', 'USD', roundingMode: RoundingMode::Down);
        } catch (NumberFormatException $invalidNumber) {
            $result = Money::zero('USD');
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $invalidNumber);
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofWithUnpackedRoundingMode(BigDecimal $amount): void
    {
        try {
            Money::of($amount, 'USD', new DefaultContext(), ...[RoundingMode::Unnecessary]);
        } catch (RoundingNecessaryException) {
            $caught = true;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $caught);
        }
    }

    public function ofWithAutoContextAndRoundingMode(): void
    {
        try {
            $result = Money::of(1, 'USD', new AutoContext(), RoundingMode::Down);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function ofWithAutoContextAndDefaultRoundingMode(): void
    {
        try {
            $result = Money::of(1, 'USD', new AutoContext());
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofDecimalWithoutRoundingModeCatchingNumberAndCurrencyExceptions(BigDecimal $amount): void
    {
        try {
            $result = Money::of($amount, 'USD');
        } catch (NumberFormatException | UnknownCurrencyException) {
            $result = Money::zero('USD');
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function ofMinorWithRoundingMode(BigDecimal $amount): void
    {
        try {
            $result = Money::ofMinor($amount, 'USD', roundingMode: RoundingMode::HalfUp);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function ofMinorWithAutoContextAndRoundingMode(): void
    {
        try {
            Money::ofMinor(1, 'USD', roundingMode: RoundingMode::Down, context: new AutoContext());
        } catch (ContextException) {
            $caught = true;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $caught);
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

    public function compareToWithOversizedExponent(Money $a): void
    {
        try {
            $a->compareTo('1e99999999999999999999999');
        } catch (NumberFormatException) {
            $caught = true;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $caught);
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

    public function compareToWithMoneyCatchingCurrencyMismatch(Money $a, Money $b): void
    {
        try {
            $result = $a->compareTo($b);
        } catch (CurrencyMismatchException) {
            $result = 0;
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    // --- Arithmetic methods ---

    public function plusWithSafeRounding(Money $a): void
    {
        try {
            $result = $a->plus(10, RoundingMode::Down);
        } catch (ContextException) {
            $caught = true;
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $caught);
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function dividedByWithSafeRounding(Money $a): void
    {
        try {
            $result = $a->dividedBy(3, RoundingMode::Down);
        } catch (ContextException) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function plusWithUnsafeNumberCatchingRoundingException(Money $a, string $number): void
    {
        try {
            $result = $a->plus($number, RoundingMode::Down);
        } catch (RoundingNecessaryException) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function plusWithMoneyCatchingMoneyExceptions(Money $a, Money $b): void
    {
        try {
            $result = $a->plus($b, RoundingMode::Down);
        } catch (CurrencyMismatchException | ContextMismatchException | ContextException) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function plusWithMoneyOrInt(Money $a, Money|int $b): void
    {
        try {
            $a->plus($b, RoundingMode::Down);
        } catch (CurrencyMismatchException) {
            $currencyMismatch = true;
        } catch (ContextMismatchException) {
            $contextMismatch = true;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $currencyMismatch);
            assertVariableCertainty(TrinaryLogic::createMaybe(), $contextMismatch);
        }
    }

    public function plusWithUnpackedMoneyOperand(Money $a, Money $b): void
    {
        try {
            $a->plus(...[$b], roundingMode: RoundingMode::Down);
        } catch (CurrencyMismatchException) {
            $currencyMismatch = true;
        } catch (ContextMismatchException) {
            $contextMismatch = true;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $currencyMismatch);
            assertVariableCertainty(TrinaryLogic::createMaybe(), $contextMismatch);
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

    public function dividedByMaybeZeroIntWithSafeRounding(Money $a, int $divisor): void
    {
        try {
            $result = $a->dividedBy($divisor, RoundingMode::Down);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function dividedByMaybeZeroIntWithSafeRoundingCatchingDivisionByZero(Money $a, int $divisor): void
    {
        try {
            $result = $a->dividedBy($divisor, RoundingMode::Down);
        } catch (DivisionByZeroException) {
            $zeroDivisor = true;
            $result = $a;
        } catch (ContextException) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $zeroDivisor);
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function dividedByWithUnnecessaryRoundingCatchingDivisionByZero(Money $a): void
    {
        try {
            $result = $a->dividedBy(3, RoundingMode::Unnecessary);
        } catch (DivisionByZeroException) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    // --- Int arithmetic ---

    public function multipliedByInt(Money $a): void
    {
        try {
            $result = $a->multipliedBy(5);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function plusWithInt(Money $a): void
    {
        try {
            $a->plus(1);
        } catch (RoundingNecessaryException) {
            $caught = true;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $caught);
        }
    }

    public function minusWithInt(Money $a): void
    {
        try {
            $a->minus(1);
        } catch (RoundingNecessaryException) {
            $caught = true;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $caught);
        }
    }

    public function plusWithZero(Money $a): void
    {
        try {
            $result = $a->plus(0);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function dividedByIntNoRoundingMode(Money $a): void
    {
        try {
            $result = $a->dividedBy(3);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function multipliedByString(Money $a, string $s): void
    {
        try {
            $result = $a->multipliedBy($s);
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

    /** @param numeric-string $number */
    public function rationalPlusWithNumericString(RationalMoney $a, string $number): void
    {
        try {
            $a->plus($number);
        } catch (NumberFormatException) {
            $caught = true;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $caught);
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

    public function rationalDividedByWithNonZeroInt(RationalMoney $a): void
    {
        try {
            $result = $a->dividedBy(3);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function rationalDividedByWithMaybeZeroInt(RationalMoney $a, int $divisor): void
    {
        try {
            $result = $a->dividedBy($divisor);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function rationalDividedByWithMaybeZeroIntCatchingDivisionByZero(
        RationalMoney $a,
        int $divisor,
    ): void {
        try {
            $result = $a->dividedBy($divisor);
        } catch (DivisionByZeroException) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function rationalDividedByWithStringCatchingDivisionByZero(RationalMoney $a, string $s): void
    {
        try {
            $result = $a->dividedBy($s);
        } catch (DivisionByZeroException) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    // --- Rounding mode methods ---

    public function toContextWithSafeRounding(Money $a): void
    {
        try {
            $result = $a->toContext(new DefaultContext(), RoundingMode::Down);
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function toContextWithIncompatibleCashContext(Money $a): void
    {
        $context = new CashContext(3);

        try {
            $a->toContext($context, RoundingMode::Down);
        } catch (ContextException) {
            $caught = true;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $caught);
        }
    }

    public function toContextWithUnpackedRoundingMode(Money $a): void
    {
        try {
            $a->toContext(new DefaultContext(), ...[RoundingMode::Unnecessary]);
        } catch (RoundingNecessaryException) {
            $caught = true;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $caught);
        }
    }

    public function toContextWithUnnecessaryRounding(Money $a): void
    {
        try {
            $result = $a->toContext($a->getContext(), RoundingMode::Unnecessary);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function convertedToWithSafeRoundingCatchingNonRoundingExceptions(Money $a, Currency $currency): void
    {
        try {
            $result = $a->convertedTo($currency, 2, roundingMode: RoundingMode::Down);
        } catch (UnknownCurrencyException | NumberFormatException | InvalidArgumentException) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function convertedToWithUnsafeRoundingCatchingNonRoundingExceptions(Money $a, Currency $currency): void
    {
        try {
            $result = $a->convertedTo($currency, 2, roundingMode: RoundingMode::Unnecessary);
        } catch (UnknownCurrencyException | NumberFormatException | InvalidArgumentException) {
            $result = $a;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function convertedToWithAutoContextAndRoundingMode(Money $a): void
    {
        try {
            $a->convertedTo('USD', 1, new AutoContext(), RoundingMode::Down);
        } catch (ContextException) {
            $caught = true;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $caught);
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

    public function getMoneyWithUnknownCurrencyCatchingCurrencyException(\Brick\Money\MoneyBag $bag, string $code): void
    {
        try {
            $result = $bag->getMoney($code);
        } catch (UnknownCurrencyException) {
            $result = $bag->getMoney('USD');
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    // --- CurrencyConverter ---

    public function convertWithSafeCurrency(CurrencyConverter $converter, Money $money): void
    {
        try {
            $result = $converter->convert($money, 'USD');
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function convertWithSafeCurrencyCatchingResidualExceptions(
        CurrencyConverter $converter,
        Money $money,
    ): void {
        // Keep the sealed ExchangeRateException in a separate catch (phpstan/phpstan#15392).
        try {
            $result = $converter->convert($money, 'USD');
        } catch (ExchangeRateException) {
            $result = $money;
        } catch (RoundingNecessaryException) {
            $result = $money;
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function convertWithIncompatibleCashContext(CurrencyConverter $converter, Money $money): void
    {
        $context = new CashContext(3);

        try {
            $converter->convert($money, 'USD', context: $context);
        } catch (ContextException) {
            $caught = true;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $caught);
        }
    }

    public function convertWithAutoContextAndRoundingMode(CurrencyConverter $converter, Money $money): void
    {
        try {
            $converter->convert($money, 'USD', context: new AutoContext(), roundingMode: RoundingMode::Down);
        } catch (ContextException) {
            $caught = true;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $caught);
        }
    }

    public function convertWithUnpackedRoundingMode(CurrencyConverter $converter, Money $money): void
    {
        try {
            $converter->convert($money, 'USD', [], new DefaultContext(), ...[RoundingMode::Unnecessary]);
        } catch (RoundingNecessaryException) {
            $caught = true;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $caught);
        }
    }

    public function convertWithUnknownCurrency(CurrencyConverter $converter, Money $money, string $code): void
    {
        try {
            $result = $converter->convert($money, $code);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function convertWithUnknownCurrencyCatchingNonCurrencyExceptions(
        CurrencyConverter $converter,
        Money $money,
        string $code,
    ): void {
        try {
            $result = $converter->convert($money, $code);
        } catch (ExchangeRateException | RoundingNecessaryException) {
            $result = $money;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function convertWithUnknownCurrencyAndSkippedNamedSafeRoundingMode(
        CurrencyConverter $converter,
        Money $money,
        string $code,
    ): void {
        // Keep the sealed ExchangeRateException in a separate catch (phpstan/phpstan#15392).
        try {
            $result = $converter->convert($money, $code, roundingMode: RoundingMode::Down);
        } catch (ExchangeRateException) {
            $result = $money;
        } catch (UnknownCurrencyException) {
            $result = $money;
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function convertToRationalWithSafeCurrency(CurrencyConverter $converter, Money $money): void
    {
        try {
            $result = $converter->convertToRational($money, 'USD');
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function convertToRationalWithSafeCurrencyCatchingResidualException(
        CurrencyConverter $converter,
        Money $money,
    ): void {
        try {
            $result = $converter->convertToRational($money, 'USD');
        } catch (ExchangeRateException) {
            $result = $money->toRational();
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function convertToRationalWithUnknownCurrency(CurrencyConverter $converter, Money $money, string $code): void
    {
        try {
            $result = $converter->convertToRational($money, $code);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function convertToRationalWithUnknownCurrencyCatchingNonCurrencyException(
        CurrencyConverter $converter,
        Money $money,
        string $code,
    ): void {
        try {
            $result = $converter->convertToRational($money, $code);
        } catch (ExchangeRateException) {
            $result = $money->toRational();
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    /** @param 'USD'|numeric-string $code */
    public function convertToRationalWithPartiallyKnownCurrency(
        CurrencyConverter $converter,
        Money $money,
        string $code,
    ): void {
        try {
            $converter->convertToRational($money, $code);
        } catch (UnknownCurrencyException) {
            $caught = true;
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $caught);
        }
    }

    // --- RationalMoney::convertedTo ---

    public function rationalConvertedToWithSafeArgs(RationalMoney $a, Currency $currency): void
    {
        try {
            $result = $a->convertedTo($currency, 2);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function rationalConvertedToWithSafeArgsCatchingResidualException(
        RationalMoney $a,
        Currency $currency,
    ): void {
        try {
            $result = $a->convertedTo($currency, 2);
        } catch (InvalidArgumentException) {
            $result = Money::zero('USD');
        } finally {
            assertVariableCertainty(TrinaryLogic::createYes(), $result);
        }
    }

    public function rationalConvertedToWithUnsafeCurrency(RationalMoney $a, string $code): void
    {
        try {
            $result = $a->convertedTo($code, 2);
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function rationalConvertedToWithUnsafeCurrencyCatchingNonCurrencyExceptions(
        RationalMoney $a,
        string $code,
    ): void {
        try {
            $result = $a->convertedTo($code, 2);
        } catch (NumberFormatException | InvalidArgumentException) {
            $result = Money::zero('USD');
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }

    public function rationalConvertedToWithUnsafeRateCatchingNonNumberExceptions(
        RationalMoney $a,
        Currency $currency,
        string $rate,
    ): void {
        try {
            $result = $a->convertedTo($currency, $rate);
        } catch (UnknownCurrencyException | InvalidArgumentException) {
            $result = Money::zero('USD');
        } finally {
            assertVariableCertainty(TrinaryLogic::createMaybe(), $result);
        }
    }
}

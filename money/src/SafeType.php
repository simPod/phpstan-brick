<?php

declare(strict_types=1);

namespace Brick\Money\PHPStan;

use Brick\Math\BigNumber;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\RoundingMode;
use Brick\Money\AbstractMoney;
use Brick\Money\Context\AutoContext;
use Brick\Money\Context\CashContext;
use Brick\Money\Context\CustomContext;
use Brick\Money\Context\DefaultContext;
use Brick\Money\Currency;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Enum\EnumCaseObjectType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use ReflectionClass;

use function assert;
use function count;
use function dirname;
use function filter_var;
use function ltrim;
use function preg_match;
use function rtrim;
use function str_starts_with;
use function strlen;

use const FILTER_VALIDATE_INT;
use const PHP_INT_MAX;

/**
 * Checks which parts of a declared throw type are possible for the given arguments.
 */
final class SafeType
{
    /** @var array<string, true>|null */
    private static array|null $knownCurrencies = null;

    /**
     * PHPStan normally binds named arguments before invoking the extension. An unpacked
     * argument can occupy multiple parameter slots, though, and must not be read by index.
     *
     * @param Arg[] $args
     */
    public static function hasUnboundArguments(array $args): bool
    {
        foreach ($args as $arg) {
            if ($arg->unpack || $arg->name !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * `numeric-string` is broader than Brick's grammar (e.g. whitespace and oversized exponents).
     * Check decimal literals without expanding exponents.
     *
     * @return list<Type>
     */
    public static function numberParsingExceptions(Type $type, bool $acceptsMoney = false): array
    {
        $safeTypes = [new ObjectType(BigNumber::class), new IntegerType()];

        if ($acceptsMoney) {
            $safeTypes[] = new ObjectType(AbstractMoney::class);
        }

        $safeType = TypeCombinator::union(...$safeTypes);

        foreach ($type instanceof UnionType ? $type->getTypes() : [$type] as $variant) {
            if ($safeType->isSuperTypeOf($variant)->yes()) {
                continue;
            }

            $value = self::constantString($variant);

            if ($value === null || self::parseDecimalLiteral($value) === null) {
                return [new ObjectType(NumberFormatException::class)];
            }
        }

        return [];
    }

    /**
     * Returns whether every possible currency is a Currency instance or a known ISO currency code,
     * so UnknownCurrencyException cannot occur.
     */
    public static function isSafeCurrency(Type $type): bool
    {
        $currencyType = new ObjectType(Currency::class);

        if ($currencyType->isSuperTypeOf($type)->yes()) {
            return true;
        }

        $knownCurrencies = self::getKnownCurrencies();

        foreach ($type instanceof UnionType ? $type->getTypes() : [$type] as $variant) {
            if ($currencyType->isSuperTypeOf($variant)->yes()) {
                continue;
            }

            $code = self::constantString($variant);

            if ($code === null || ! isset($knownCurrencies[$code])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns whether the given rounding mode type is known to NOT be `Unnecessary`,
     * meaning RoundingNecessaryException cannot occur.
     */
    public static function isSafeRoundingMode(Type|null $type): bool
    {
        return $type !== null
            && new EnumCaseObjectType(RoundingMode::class, 'Unnecessary')->isSuperTypeOf($type)->no();
    }

    public static function isUnnecessaryRoundingMode(Type|null $type): bool
    {
        return $type === null
            || new EnumCaseObjectType(RoundingMode::class, 'Unnecessary')->isSuperTypeOf($type)->yes();
    }

    /**
     * Checks a target context that need not have been applied to this currency before.
     * CashContext with a step of 1 applies to any currency. Other steps may be incompatible with the currency's scale.
     */
    public static function isContextApplicable(Expr|null $context, Type|null $roundingMode, Scope $scope): bool
    {
        if ($context === null) {
            return true;
        }

        $safeContexts = [new ObjectType(DefaultContext::class), new ObjectType(CustomContext::class)];

        if (self::isUnnecessaryRoundingMode($roundingMode)) {
            $safeContexts[] = new ObjectType(AutoContext::class);
        }

        $contextType = $scope->getType($context);

        return TypeCombinator::union(...$safeContexts)->isSuperTypeOf($contextType)->yes()
            || self::isUnitCashContext($context, $scope);
    }

    public static function isUnitCashContext(Expr $context, Scope $scope): bool
    {
        if (
            ! $context instanceof New_
            || ! new ObjectType(CashContext::class)->isSuperTypeOf($scope->getType($context))->yes()
        ) {
            return false;
        }

        $args = $context->getArgs();

        return count($args) === 1
            && ! $args[0]->unpack
            && ($args[0]->name === null || $args[0]->name->toString() === 'step')
            && new ConstantIntegerType(1)->isSuperTypeOf($scope->getType($args[0]->value))->yes();
    }

    /**
     * Returns whether the given type is guaranteed to be zero.
     *
     * Zero scaled to any precision is still zero, so no rounding can occur.
     */
    public static function isZero(Type $type): bool
    {
        return new ConstantIntegerType(0)->isSuperTypeOf($type)->yes();
    }

    public static function isOne(Type $type): bool
    {
        $one = new ConstantIntegerType(1);

        foreach ($type instanceof UnionType ? $type->getTypes() : [$type] as $variant) {
            if ($one->isSuperTypeOf($variant)->yes()) {
                continue;
            }

            $value = self::constantString($variant);
            $decimal = $value === null ? null : self::parseDecimalLiteral($value);

            if ($decimal === null) {
                return false;
            }

            [$digits, $scale] = $decimal;

            if ($scale !== strlen($digits) - 1 || rtrim($digits, '0') !== '1') {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns whether the given type is guaranteed to be non-zero.
     *
     * This is used to eliminate {@see DivisionByZeroException} from throw types.
     * Only proven for integer types (e.g. int<1, max>, literal 5).
     */
    public static function isNonZero(Type $type): bool
    {
        if (! new IntegerType()->isSuperTypeOf($type)->yes()) {
            return false;
        }

        $zeroType = new ConstantIntegerType(0);

        return $zeroType->isSuperTypeOf($type)->no();
    }

    private static function constantString(Type $type): string|null
    {
        $constant = $type->getConstantStrings()[0] ?? null;

        // getConstantStrings() alone does not prove the type excludes other values.
        return $constant !== null && $constant->isSuperTypeOf($type)->yes() ? $constant->getValue() : null;
    }

    /**
     * Returns the signed digits and scale without padding the digits or converting the mantissa to a native number.
     *
     * @return array{string, int}|null
     */
    private static function parseDecimalLiteral(string $value): array|null
    {
        $matched = preg_match(
            '~\A(?<sign>[+-]?)(?=[0-9]|\.[0-9])(?<integral>[0-9]*+)'
            . '(?:\.(?<fractional>[0-9]*+))?(?:[eE](?<exponent>[+-]?[0-9]++))?\z~',
            $value,
            $parts,
        );

        if ($matched !== 1) {
            return null;
        }

        $fractional = $parts['fractional'] ?? '';
        $rawExponent = $parts['exponent'] ?? '';
        $exponentDigits = ltrim($rawExponent, '+-0');
        $exponentSign = str_starts_with($rawExponent, '-') ? '-' : '';
        $exponent = $exponentDigits === '' ? 0 : filter_var($exponentSign . $exponentDigits, FILTER_VALIDATE_INT);

        // Brick Math requires both the scale and the expanded digit count (at least scale + 1) to fit in an int.
        if ($exponent === false || $exponent <= strlen($fractional) - PHP_INT_MAX) {
            return null;
        }

        $scale = strlen($fractional) - $exponent;
        $digits = ltrim($parts['integral'] . $fractional, '0');

        if ($scale < 0 && strlen($digits) > PHP_INT_MAX + $scale) {
            return null;
        }

        return [$parts['sign'] === '-' ? '-' . $digits : $digits, $scale];
    }

    /** @return array<string, true> */
    private static function getKnownCurrencies(): array
    {
        if (self::$knownCurrencies === null) {
            $currencyClassFile = new ReflectionClass(Currency::class)->getFileName();
            assert($currencyClassFile !== false);

            /** @var array<string, mixed> $data */
            $data = require dirname($currencyClassFile, 2) . '/data/iso-currencies.php';
            self::$knownCurrencies = [];

            foreach ($data as $code => $_) {
                self::$knownCurrencies[$code] = true;
            }
        }

        return self::$knownCurrencies;
    }
}

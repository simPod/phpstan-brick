<?php

declare(strict_types=1);

namespace Brick\Money\PHPStan\Tests;

use Brick\Money\CurrencyConverter;
use PHPStan\Testing\TypeInferenceTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

class ThrowTypeExtensionTest extends TypeInferenceTestCase
{
    /** @return iterable<mixed> */
    public static function dataFileAsserts(): iterable
    {
        yield from self::gatherAssertTypes(__DIR__ . '/data/MoneyThrowTypes.php');
    }

    #[DataProvider('dataFileAsserts')]
    public function testFileAsserts(string $assertType, string $file, mixed ...$args): void
    {
        $this->assertFileAsserts($assertType, $file, ...$args);
    }

    public function testCurrencyConverterConvertUsesPre013Signature(): void
    {
        $method = new ReflectionMethod(CurrencyConverter::class, 'convert');
        $parameters = array_map(
            static fn ($parameter): string => $parameter->getName(),
            $method->getParameters(),
        );

        self::assertSame(['money', 'currency', 'context', 'roundingMode'], $parameters);
    }

    /** @return list<string> */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/test.neon'];
    }
}

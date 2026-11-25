# PHPStan Brick Money

[![Latest release](https://poser.pugx.org/simpod/phpstan-brick-money/v/stable)](https://packagist.org/packages/simpod/phpstan-brick-money)
[![Total downloads](https://poser.pugx.org/simpod/phpstan-brick-money/d/total.svg)](https://packagist.org/packages/simpod/phpstan-brick-money)
[![Monorepo Tests](https://github.com/simPod/phpstan-brick/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/simPod/phpstan-brick/actions/workflows/tests.yml)

PHPStan extensions that narrow throw types for [brick/money](https://github.com/brick/money).

## Installation

This extension requires `brick/money` 0.15.1 or later in the 0.15 series and `brick/math` 1.x.

```
composer require --dev simpod/phpstan-brick-money
```

If you use [phpstan/extension-installer](https://github.com/phpstan/extension-installer), you're all set.

Otherwise, include in your `phpstan.neon`:

```neon
includes:
    - vendor/simpod/phpstan-brick-money/extension.neon
```

## Contributing

This is a read-only repository, automatically split from [simPod/phpstan-brick](https://github.com/simPod/phpstan-brick). Please submit pull requests and issues there.

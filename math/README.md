# PHPStan Brick Math

[![Latest release](https://poser.pugx.org/simpod/phpstan-brick-math/v/stable)](https://packagist.org/packages/simpod/phpstan-brick-math)
[![Total downloads](https://poser.pugx.org/simpod/phpstan-brick-math/d/total.svg)](https://packagist.org/packages/simpod/phpstan-brick-math)
[![Monorepo Tests](https://github.com/simPod/phpstan-brick/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/simPod/phpstan-brick/actions/workflows/tests.yml)

PHPStan extensions that narrow throw types for [brick/math](https://github.com/brick/math).

## Installation

```
composer require --dev simpod/phpstan-brick-math
```

If you use [phpstan/extension-installer](https://github.com/phpstan/extension-installer), you're all set.

Otherwise, include in your `phpstan.neon`:

```neon
includes:
    - vendor/simpod/phpstan-brick-math/extension.neon
```

## Contributing

This is a read-only repository, automatically split from [simPod/phpstan-brick](https://github.com/simPod/phpstan-brick). Please submit pull requests and issues there.

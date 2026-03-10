# PHPStan Brick Extensions

PHPStan extensions that narrow throw types for [brick/math](https://github.com/brick/math) and [brick/money](https://github.com/brick/money).

## Packages

### `simpod/phpstan-brick-math`

```
composer require --dev simpod/phpstan-brick-math
```

If you use [phpstan/extension-installer](https://github.com/phpstan/extension-installer), you're all set.

Otherwise, include in your `phpstan.neon`:

```neon
includes:
    - vendor/simpod/phpstan-brick-math/extension.neon
```

### `simpod/phpstan-brick-money`

```
composer require --dev simpod/phpstan-brick-money
```

If you use [phpstan/extension-installer](https://github.com/phpstan/extension-installer), you're all set.

Otherwise, include in your `phpstan.neon`:

```neon
includes:
    - vendor/simpod/phpstan-brick-money/extension.neon
```

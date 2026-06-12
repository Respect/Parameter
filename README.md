# Respect\Parameter

Resolves function and constructor parameters from a PSR-11 container by type.

## Install

```bash
composer require respect/parameter
```

## Usage

The package offers two contracts with different guarantees:

- `Resolver` **completes a call**: it returns a full argument list keyed by
  parameter name, padding gaps with container services, defaults, or `null`.
  Implemented by `ContainerResolver`.
- `Augmenter` **assists a factory**: the given arguments stay authoritative —
  never rebound, reordered, or padded — and the container only fills genuine
  gaps. Implemented by `ContainerAugmenter`.

Type-hint the interfaces to keep implementations swappable and testable.

### Resolve from a container

For each parameter the resolver tries, in order:

1. Positional argument of matching **type**
2. Container match by **type** (non-builtin)
3. Next **positional argument**
4. **Default value**
5. `null`

```php
use Respect\Parameter\ContainerResolver;

function notify(Mailer $mailer, Logger $logger, string $to, string $subject = 'Hi') {
    // ...
}

$resolver = new ContainerResolver($container);
$args = $resolver->resolve(new ReflectionFunction('notify'), ['bob@example.com']);
// ['mailer' => Mailer, 'logger' => Logger, 'to' => 'bob@example.com', 'subject' => 'Hi']
```

Results are keyed by parameter name, so you can spread them with named arguments:

```php
notify(...$args);
```

### Resolve with named arguments

When arguments are keyed by name (e.g. from configuration):

```php
$args = $resolver->resolveNamed(
    $constructor,
    ['username' => 'admin', 'password' => 'secret'],
);
// Named args take precedence, gaps filled from container by name and type
```

### Augment arguments

Use the augmenter when the arguments must stay exactly as the caller provided
them (e.g. factories that pass user input straight to a constructor) and the
container should only supply the missing services:

```php
use Respect\Parameter\ContainerAugmenter;

final class Notifier
{
    public function __construct(
        private string $channel,
        private Mailer|null $mailer = null,
    ) {
    }
}

$augmenter = new ContainerAugmenter($container);
$args = $augmenter->augment($constructor, ['slack']);
// ['slack', 'mailer' => Mailer] — positional args untouched, gaps named
```

Variadic, builtin-typed, and already-filled parameters are never augmented.
Extra arguments (e.g. for variadic parameters) pass through unchanged, and
missing arguments are never padded with defaults or `null`.

#### Unresolvable types

Value-like classes should never be served by the container, even when it can
provide them — a container-cached `DateTimeImmutable` is a frozen clock.
List them at construction to exclude them from container lookups:

```php
$augmenter = new ContainerAugmenter($container, [
    DateTimeImmutable::class,
    DateTimeInterface::class,
]);
```

### Reflect any callable

Convert any callable form into a `ReflectionFunctionAbstract`:

```php
use Respect\Parameter\Reflector;

Reflector::reflectCallable(fn() => ...);                  // Closure
Reflector::reflectCallable([$obj, 'method']);             // Array callable
Reflector::reflectCallable(new Invocable());              // __invoke object
Reflector::reflectCallable('strlen');                     // Function name
Reflector::reflectCallable('DateTime::createFromFormat'); // Static method
```

### Check accepted types

```php
Reflector::acceptsType($reflection, LoggerInterface::class); // true/false
```

## API

| Method                                      | Defined on  | Description                                          |
|---------------------------------------------|-------------|------------------------------------------------------|
| `resolve($reflection, $positional)`         | `Resolver`  | Resolve parameters from positional args + container. Returns `array<string, mixed>` keyed by parameter name |
| `resolveNamed($reflection, $named)`         | `Resolver`  | Resolve from named args (priority) + container. Returns `array<string, mixed>` keyed by parameter name     |
| `augment($reflection, $args)`               | `Augmenter` | Fill only unfilled parameters from the container; given args are never rebound, reordered, or padded       |
| `Reflector::reflectCallable($callable)`     | `Reflector` | Any callable to `ReflectionFunctionAbstract`         |
| `Reflector::acceptsType($reflection, $type)`| `Reflector` | Check if any parameter accepts a type                |

## Upgrading from 1.x

- `Resolver` is now an interface; the concrete class is `ContainerResolver`.
- `Resolver::reflectCallable()` and `Resolver::acceptsType()` moved to `Reflector`.
- The new `Augmenter`/`ContainerAugmenter` fill unfilled parameters without
  touching the given arguments.

## License

ISC. See [LICENSE](LICENSE).

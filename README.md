# Respect\Parameter

Resolves function and constructor parameters from a PSR-11 container by type.

## Install

```bash
composer require respect/parameter
```

## Usage

### Resolve arguments

For each parameter the resolver tries, in order:

1. An explicit **named** argument (keyed by parameter name)
2. A **positional** argument already matching the parameter **type**
3. The **container**, matched by **type** (non-builtin)
4. The next **positional** argument
5. The parameter's **default value**
6. `null`

A trailing **variadic** parameter receives a matching named argument (if any) followed by every remaining positional argument.

```php
use Respect\Parameter\ContainerResolver;

function notify(Mailer $mailer, Logger $logger, string $to, string $subject = 'Hi') {
    // ...
}

$resolver = new ContainerResolver($container);
$args = $resolver->resolve(new ReflectionFunction('notify'), ['bob@example.com']);
// [Mailer, Logger, 'bob@example.com', 'Hi']  — ordered, ready to splat
```

The result is an ordered list, so spread it straight into the call or constructor:

```php
notify(...$args);
// or
$reflection->newInstanceArgs($args);
```

### Named arguments

`resolve()` accepts named arguments too — keyed by parameter name, taking precedence over the
container; the remaining parameters are filled by type and defaults:

```php
$args = $resolver->resolve($constructor, ['username' => 'admin']);
```

### Bind to the interface

Type-hint `Resolver` (the `resolve()` contract) rather than the concrete `ContainerResolver` to stay
decoupled from the implementation:

```php
use Respect\Parameter\Resolver;

final class Factory
{
    public function __construct(private Resolver $resolver)
    {
    }
}
```

### Reflect any callable

Convert any callable form into a `ReflectionFunctionAbstract`:

```php
use Respect\Parameter\ContainerResolver;

ContainerResolver::reflectCallable(fn() => ...);                  // Closure
ContainerResolver::reflectCallable([$obj, 'method']);             // Array callable
ContainerResolver::reflectCallable(new Invocable());              // __invoke object
ContainerResolver::reflectCallable('strlen');                     // Function name
ContainerResolver::reflectCallable('DateTime::createFromFormat'); // Static method
```

### Check accepted types

```php
ContainerResolver::acceptsType($reflection, LoggerInterface::class); // true/false
```

## API

| Method                                  | Type     | Description                                                                                       |
|-----------------------------------------|----------|---------------------------------------------------------------------------------------------------|
| `resolve($reflection, $arguments)`      | instance | Resolve named/positional arguments + container into an ordered `list<mixed>`, expanding variadics |
| `reflectCallable($callable)`            | static   | Any callable to `ReflectionFunctionAbstract`                                                      |
| `acceptsType($reflection, $type)`       | static   | Check if any parameter accepts a type                                                             |

`ContainerResolver` implements `Resolver`.

## License

ISC. See [LICENSE](LICENSE).

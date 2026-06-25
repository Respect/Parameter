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
use Respect\Parameter\Resolver;

function notify(Mailer $mailer, Logger $logger, string $to, string $subject = 'Hi') {
    // ...
}

$resolver = new Resolver($container);
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

Type-hint `ParameterResolver` (the `resolve()` contract) rather than the concrete `Resolver` to stay
decoupled from the implementation:

```php
use Respect\Parameter\ParameterResolver;

final class Factory
{
    public function __construct(private ParameterResolver $resolver)
    {
    }
}
```

### Reflect any callable

Convert any callable form into a `ReflectionFunctionAbstract`:

```php
use Respect\Parameter\Resolver;

Resolver::reflectCallable(fn() => ...);                  // Closure
Resolver::reflectCallable([$obj, 'method']);             // Array callable
Resolver::reflectCallable(new Invocable());              // __invoke object
Resolver::reflectCallable('strlen');                     // Function name
Resolver::reflectCallable('DateTime::createFromFormat'); // Static method
```

### Check accepted types

```php
Resolver::acceptsType($reflection, LoggerInterface::class); // true/false
```

## API

| Method                                  | Type     | Description                                                                                       |
|-----------------------------------------|----------|---------------------------------------------------------------------------------------------------|
| `resolve($reflection, $arguments)`      | instance | Resolve named/positional arguments + container into an ordered `list<mixed>`, expanding variadics |
| `reflectCallable($callable)`            | static   | Any callable to `ReflectionFunctionAbstract`                                                      |
| `acceptsType($reflection, $type)`       | static   | Check if any parameter accepts a type                                                             |

`Resolver` implements `ParameterResolver`.

## License

ISC. See [LICENSE](LICENSE).

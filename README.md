# Respect\Parameter

Resolves function and constructor parameters from a PSR-11 container by type.

## Install

```bash
composer require respect/parameter
```

## Usage

### Resolve from a container

For each parameter the resolver tries, in order:

1. Positional argument of matching **type**
2. Container match by **type** (non-builtin)
3. Next **positional argument**
4. **Default value**
5. `null`

```php
use Respect\Parameter\Resolver;

function notify(Mailer $mailer, Logger $logger, string $to, string $subject = 'Hi') {
    // ...
}

$resolver = new Resolver($container);
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

| Method                                  | Type     | Description                                          |
|-----------------------------------------|----------|------------------------------------------------------|
| `resolve($reflection, $positional)`     | instance | Resolve parameters from positional args + container. Returns `array<string, mixed>` keyed by parameter name |
| `resolveNamed($reflection, $named)`     | instance | Resolve from named args (priority) + container. Returns `array<string, mixed>` keyed by parameter name     |
| `reflectCallable($callable)`            | static   | Any callable to `ReflectionFunctionAbstract`         |
| `acceptsType($reflection, $type)`       | static   | Check if any parameter accepts a type                |

## License

ISC. See [LICENSE](LICENSE).

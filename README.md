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

### Memoize parameter introspection

Reflection is expensive. The resolver accepts an optional PSR-16 cache as its second argument;
when supplied, the per-parameter spec (name, type, variadic flag, default-available flag) is
memoized under a stable key derived from the callable identity (`FQCN::method` for methods,
`fn:name` for named functions), so repeated `resolve()` calls on different
`ReflectionMethod` / `ReflectionFunction` instances of the same callable share one spec and
skip `ReflectionParameter` method calls entirely. Closures and invocable objects have no stable
identity across reflections and bypass the cache.

```php
use Respect\Parameter\Resolver;

$resolver = new Resolver($container, $psr16Cache);
```

The package ships with a ready-to-use in-memory PSR-16 implementation so you get the memoization
benefit with no external dependency:

```php
use Respect\Parameter\InMemoryCache;
use Respect\Parameter\Resolver;

$resolver = new Resolver($container, new InMemoryCache());
```

`InMemoryCache` is a process-local array-backed cache: entries live for the lifetime of the
cache instance and are not shared across processes. For longer-lived or shared caching, pass any
real PSR-16 implementation (Symfony Cache, PSR-16 adapter over APCu, etc.).

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

`InMemoryCache` implements `Psr\SimpleCache\CacheInterface` and is the bundled zero-dependency
PSR-16 cache for memoizing the resolver's parameter spec.

## License

ISC. See [LICENSE](LICENSE).

Valinor • PHP object mapper with strong type support
====================================================

[![Total Downloads](http://poser.pugx.org/cuyz/valinor/downloads)][link-packagist]
[![Latest Stable Version](http://poser.pugx.org/cuyz/valinor/v)][link-packagist]
[![PHP Version Require](http://poser.pugx.org/cuyz/valinor/require/php)][link-packagist]

[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FCuyZ%2FValinor%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/CuyZ/Valinor/master)

Valinor is a PHP library that helps to map any input into a strongly-typed value
object structure.

The conversion can handle native PHP types as well as other well-known advanced
type annotations like array shapes, generics and more.

## Why?

There are many benefits of using value objects instead of plain arrays and
scalar values in a modern codebase, among which:

1. **Data and behaviour encapsulation** — locks an object's behaviour inside its
   class, preventing it from being scattered across the codebase.
2. **Data validation** — guarantees the valid state of an object.
3. **Immutability** — ensures the state of an object cannot be changed during
   runtime.

When mapping any source to an object structure, this library will ensure that
all input values are properly converted to match the types of the nodes — class
properties or method parameters. Any value that cannot be converted to the
correct type will trigger an error and prevent the mapping from completing.

These checks guarantee that if the mapping succeeds, the object structure is
perfectly valid, hence there is no need for further validation nor type
conversion: the objects are ready to be used.

### Static analysis

A strongly-typed codebase allows the usage of static analysis tools like
[PHPStan] and [Psalm] that can identify issues in a codebase without running it.

Moreover, static analysis can help during a refactoring of a codebase with tools
like an IDE or [Rector].

## Usage

### Installation

```bash
composer require cuyz/valinor
```

### Example

An application must handle the data coming from an external API; the response
has a JSON format and describes a thread and its answers. The validity of this
input is unsure, besides manipulating a raw JSON string is laborious and
inefficient.

```json
{
    "id": 1337,
    "content": "Do you like potatoes?",
    "date": "1957-07-23 13:37:42",
    "answers": [
        {
            "user": "Ella F.",
            "message": "I like potatoes",
            "date": "1957-07-31 15:28:12"
        },
        {
            "user": "Louis A.",
            "message": "And I like tomatoes",
            "date": "1957-08-13 09:05:24"
        }
    ]
}
```

The application must be certain that it can handle this data correctly; wrapping
the input in a value object will help.

---

A schema representing the needed structure must be provided, using classes.

```php
final class Thread
{
    public function __construct(
        public readonly int $id,
        public readonly string $content,
        public readonly DateTimeInterface $date,
        /** @var Answer[] */
        public readonly array $answers, 
    ) {}
}

final class Answer
{
    public function __construct(
        public readonly string $user,
        public readonly string $message,
        public readonly DateTimeInterface $date,
    ) {}
}
```

Then a mapper is used to hydrate a source into these objects.

```php
public function getThread(int $id): Thread
{
    $rawJson = $this->client->request("https://example.com/thread/$id");

    try {   
       return (new \CuyZ\Valinor\MapperBuilder())
           ->mapper()
           ->map(
               Thread::class,
               new \CuyZ\Valinor\Mapper\Source\JsonSource($rawJson)
           );
   } catch (\CuyZ\Valinor\Mapper\MappingError $error) {
       $this->logger->error(
           'Invalid JSON returned by API', 
           $error->describe() // This gives more information about what was wrong
       );

       throw $error;
   }
}
```

### Validation

The source given to a mapper can never be trusted, this is actually the very
goal of this library: transforming an unstructured input to a well-defined
object structure. If the mapper cannot guess how to cast a certain value, it
means that it is not able to guarantee the validity of the desired object thus
it will fail.

Any issue encountered during the mapping will add an error to an upstream
exception of type `\CuyZ\Valinor\Mapper\MappingError`. It is therefore always
recommended wrapping the mapping function call with a try/catch statement and
handle the error properly.

More specific validation should be done in the constructor of the value object,
by throwing an exception if something is wrong with the given data. A good
practice would be to use lightweight validation tools like [Webmozart Assert].

```php
final class SomeClass
{
    public function __construct(private string $value)
    {
        Assert::startsWith($value, 'foo_');
    }
}

try {
   (new \CuyZ\Valinor\MapperBuilder())
       ->mapper()
       ->map(
           SomeClass::class,
           ['value' => 'bar_baz']
       );
} catch (\CuyZ\Valinor\Mapper\MappingError $error) {
    // Contains an error similar to:
    // > Expected a value to start with "foo_". Got: "bar_baz"
    var_dump($error->describe());
}
```

### Source

Any source can be given to the mapper, but some helpers can be used for more
convenience:

```php
function map($source) {
    return (new \CuyZ\Valinor\MapperBuilder())
        ->mapper()
        ->map(SomeClass::class, $source);
}

map(new \CuyZ\Valinor\Mapper\Source\JsonSource($jsonString));

map(new \CuyZ\Valinor\Mapper\Source\YamlSource($yamlString));

// File containing valid Json or Yaml content and with valid extension
map(new \CuyZ\Valinor\Mapper\Source\FileSource(
    new SplFileObject('path/to/my/file.json')
));
```

### Construction strategy

During the mapping, instances of the objects are created and hydrated with the
correct values. construction strategies will determine what values are needed
and how an object is built.

An object can provide either…

- …a constructor that will be called with proper parameters.
- …a list of properties that will be filled with proper values — even if they
  are private.

## Handled types

To prevent conflicts or duplication of the type annotations, this library tries
to handle most of the type annotations that are accepted by [PHPStan] and
[Psalm].

### Scalar

```php
final class SomeClass
{
    public function __construct(
        private bool $boolean,

        private float $float,

        private int $integer,

        /** @var positive-int */
        private int $positiveInteger,

        /** @var negative-int */
        private int $negativeInteger,

        /** @var int<-42, 1337> */
        private int $integerRange,

        /** @var int<min, 0> */
        private int $integerRangeWithMinRange,

        /** @var int<0, max> */
        private int $integerRangeWithMaxRange,

        private string $string,
        
        /** @var non-empty-string */
        private string $nonEmptyString,

        /** @var class-string */
        private string $classString,

        /** @var class-string<SomeInterface> */
        private string $classStringOfAnInterface,
    ) {}
}
```

### Object

```php
final class SomeClass
{
    public function __construct(
        private SomeClass $class,

        private DateTimeInterface $interface,

        /** @var SomeInterface&AnotherInterface */
        private object $intersection,

        /** @var SomeCollection<SomeClass> */
        private SomeCollection $classWithGeneric,
    ) {}
}

/**
 * @template T of object 
 */
final class SomeCollection
{
    public function __construct(
        /** @var array<T> */
        private array $objects,
    ) {}
}
```

### Array & lists

```php
final class SomeClass
{
    public function __construct(
        /** @var string[] */
        private array $simpleArray,

        /** @var array<string> */
        private array $arrayOfStrings,

        /** @var array<string, SomeClass> */
        private array $arrayOfClassWithStringKeys,

        /** @var array<int, SomeClass> */
        private array $arrayOfClassWithIntegerKeys,

        /** @var non-empty-array<string> */
        private array $nonEmptyArrayOfStrings,

        /** @var non-empty-array<string, SomeClass> */
        private array $nonEmptyArrayWithStringKeys,
        
        /** @var list<string> */
        private array $listOfStrings,
        
        /** @var non-empty-list<string> */
        private array $nonEmptyListOfStrings,

        /** @var array{foo: string, bar: int} */
        private array $shapedArray,

        /** @var array{foo: string, bar?: int} */
        private array $shapedArrayWithOptionalElement,

        /** @var array{string, bar: int} */
        private array $shapedArrayWithUndefinedKey,
    ) {}
}
```

### Union

```php
final class SomeClass
{
    public function __construct(
        private int|string $simpleUnion,
        
        /** @var class-string<SomeInterface>|class-string<AnotherInterface> */
        private string $unionOfClassString,
        
        /** @var array<SomeInterface|AnotherInterface> */
        private array $unionInsideArray,
    ) {}
}
```

[PHPStan]: https://phpstan.org/

[Psalm]: https://psalm.dev/

[Rector]: https://github.com/rectorphp/rector

[Webmozart Assert]: https://github.com/webmozarts/assert

[link-packagist]: https://packagist.org/packages/cuyz/valinor

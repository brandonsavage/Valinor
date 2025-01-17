<?php

declare(strict_types=1);

namespace CuyZ\Valinor\Tests\Unit\Mapper\Object;

use CuyZ\Valinor\Mapper\Object\Argument;
use CuyZ\Valinor\Mapper\Object\Exception\InvalidSourceForObject;
use CuyZ\Valinor\Mapper\Object\Exception\MissingPropertyArgument;
use CuyZ\Valinor\Mapper\Object\ReflectionObjectBuilder;
use CuyZ\Valinor\Tests\Fake\Definition\FakeClassDefinition;
use Generator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ReflectionObjectBuilderTest extends TestCase
{
    public function test_build_object_without_constructor_returns_correct_object(): void
    {
        $object = new class () {
            public string $valueA;

            protected string $valueB;

            private string $valueC = 'Some property default value';

            public function valueA(): string
            {
                return $this->valueA;
            }

            public function valueB(): string
            {
                return $this->valueB;
            }

            public function valueC(): string
            {
                return $this->valueC;
            }
        };

        $class = FakeClassDefinition::fromReflection(new ReflectionClass($object));
        $objectBuilder = new ReflectionObjectBuilder($class);
        $result = $objectBuilder->build([
            'valueA' => 'valueA',
            'valueB' => 'valueB',
            'valueC' => 'valueC',
        ]);

        self::assertSame('valueA', $result->valueA()); // @phpstan-ignore-line
        self::assertSame('valueB', $result->valueB()); // @phpstan-ignore-line
        self::assertSame('valueC', $result->valueC()); // @phpstan-ignore-line
    }

    public function test_invalid_source_type_throws_exception(): void
    {
        $object = new class () {
            public function __construct()
            {
            }
        };

        $class = FakeClassDefinition::fromReflection(new ReflectionClass($object));
        $objectBuilder = new ReflectionObjectBuilder($class);

        $this->expectException(InvalidSourceForObject::class);
        $this->expectExceptionCode(1632903281);
        $this->expectExceptionMessage('Invalid source type `string`, it must be an iterable.');

        /** @var Generator<Argument> $arguments */
        $arguments = $objectBuilder->describeArguments('foo');
        $arguments->current();
    }

    public function test_missing_arguments_throws_exception(): void
    {
        $object = new class () {
            public string $value;
        };

        $class = FakeClassDefinition::fromReflection(new ReflectionClass($object));
        $objectBuilder = new ReflectionObjectBuilder($class);

        $this->expectException(MissingPropertyArgument::class);
        $this->expectExceptionCode(1629469529);
        $this->expectExceptionMessage("Missing value `Signature::value` of type `string`.");

        $objectBuilder->build([]);
    }
}

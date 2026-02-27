<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Tests\Util;

use Tugrul\ApiGen\Util\PhpExporter;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

use Tugrul\ApiGen\Tests\Fixtures\Util\{Suit, Direction, SimpleValueObject,
    SetStateObject, NoConstructorObject, OptionalParamObject};


final class PhpExporterTest extends TestCase
{
    public function test_exports_null(): void
    {
        self::assertSame('null', PhpExporter::export(null));
    }

    public function test_exports_true(): void
    {
        self::assertSame('true', PhpExporter::export(true));
    }

    public function test_exports_false(): void
    {
        self::assertSame('false', PhpExporter::export(false));
    }

    public function test_exports_integer(): void
    {
        self::assertSame('42', PhpExporter::export(42));
        self::assertSame('-7', PhpExporter::export(-7));
        self::assertSame('0', PhpExporter::export(0));
    }

    public function test_exports_string(): void
    {
        self::assertSame("'hello'", PhpExporter::export('hello'));
        self::assertSame("'it\\'s'", PhpExporter::export("it's"));
        self::assertSame("''", PhpExporter::export(''));
    }

    // Floats

    #[DataProvider('floatProvider')]
    public function test_exports_float(float $input, string $expected): void
    {
        self::assertSame($expected, PhpExporter::export($input));
    }

    public static function floatProvider(): array
    {
        return [
            'whole float'    => [1.0,  '1.0'],
            'decimal float'  => [3.14, '3.14'],
            'negative float' => [-2.5, '-2.5'],
            'trailing zeros' => [1.50, '1.5'],
            'NAN'            => [NAN,  'NAN'],
            'INF'            => [INF,  'INF'],
            'negative INF'   => [-INF, '-INF'],
        ];
    }

    // Arrays

    public function test_exports_empty_array(): void
    {
        self::assertSame('[]', PhpExporter::export([]));
    }

    public function test_exports_list_array_without_keys(): void
    {
        $result = PhpExporter::export([1, 2, 3]);

        self::assertSame("[1,2,3]", $result);
        self::assertStringNotContainsString('=>', $result);
    }

    public function test_exports_assoc_array_with_keys(): void
    {
        $result = PhpExporter::export(['foo' => 'bar', 'baz' => 42]);

        self::assertStringContainsString("'foo' => 'bar'", $result);
        self::assertStringContainsString("'baz' => 42", $result);
    }

    public function test_exports_nested_array(): void
    {
        $result = PhpExporter::export(['a' => [1, 2], 'b' => ['x' => true]]);

        self::assertStringContainsString("'a' =>", $result);
        self::assertStringContainsString("'b' =>", $result);
        self::assertStringContainsString('true', $result);
    }

    public function test_uses_bracket_syntax_not_array_keyword(): void
    {
        $result = PhpExporter::export(['a' => 1]);

        self::assertStringStartsWith('[', $result);
        self::assertStringNotContainsString('array(', $result);
    }

    public function test_exports_int_keyed_non_list_as_assoc(): void
    {
        // Non-sequential int keys → assoc
        $result = PhpExporter::export([0 => 'a', 2 => 'b']);

        self::assertStringContainsString('0 =>', $result);
        self::assertStringContainsString('2 =>', $result);
    }

    // Enums

    public function test_exports_backed_enum(): void
    {
        $result = PhpExporter::export(Suit::Hearts);

        self::assertStringContainsString('Suit::Hearts', $result);
    }

    public function test_exports_pure_enum(): void
    {
        $result = PhpExporter::export(Direction::North);

        self::assertStringContainsString('Direction::North', $result);
    }

    // DateTime

    public function test_exports_date_time(): void
    {
        $dt     = new DateTime('2024-06-15 12:00:00', new DateTimeZone('UTC'));
        $result = PhpExporter::export($dt);

        self::assertStringContainsString('new \DateTime(', $result);
        self::assertStringContainsString('2024-06-15 12:00:00', $result);
        self::assertStringContainsString('UTC', $result);
    }

    public function test_exports_date_time_immutable(): void
    {
        $dt     = new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('Europe/Istanbul'));
        $result = PhpExporter::export($dt);

        self::assertStringContainsString('DateTimeImmutable', $result);
        self::assertStringContainsString('Europe/Istanbul', $result);
    }

    // stdClass

    public function test_exports_empty_std_class(): void
    {
        self::assertSame('(object) []', PhpExporter::export(new stdClass()));
    }

    public function test_exports_std_class_with_properties(): void
    {
        $obj       = new stdClass();
        $obj->name = 'Alice';
        $obj->age  = 30;

        $result = PhpExporter::export($obj);

        self::assertStringStartsWith('(object) [', $result);
        self::assertStringContainsString("'name' => 'Alice'", $result);
        self::assertStringContainsString("'age' => 30", $result);
    }

    // __set_state

    public function test_exports_object_with_set_state(): void
    {
        $obj        = new SetStateObject();
        $obj->name  = 'test';
        $obj->value = 99;

        $result = PhpExporter::export($obj);

        self::assertStringContainsString('__set_state(', $result);
        self::assertStringContainsString("'name' => 'test'", $result);
        self::assertStringContainsString("'value' => 99", $result);
    }

    // Reflection-based objects

    public function test_exports_simple_value_object_via_reflection(): void
    {
        $obj    = new SimpleValueObject('Alice', 25);
        $result = PhpExporter::export($obj);

        self::assertStringContainsString('new \\' . SimpleValueObject::class . '(', $result);
        self::assertStringContainsString("'Alice'", $result);
        self::assertStringContainsString('25', $result);
    }

    public function test_exports_object_with_no_constructor(): void
    {
        $result = PhpExporter::export(new NoConstructorObject());

        self::assertStringContainsString('new \\' . NoConstructorObject::class . '()', $result);
    }

    public function test_emits_comment_stub_for_unreconstructable_object(): void
    {
        // Constructor requires a param that has no matching property (stored under different name)
        $obj = new class('secret') {
            private string $internalToken;

            public function __construct(string $token)
            {
                // stored under a different name — reflection can't map $token back
                $this->internalToken = $token;
            }
        };

        $result = PhpExporter::export($obj);

        self::assertStringContainsString('cannot reconstruct', $result);
        self::assertStringContainsString('null', $result);
    }

    // Invalid type

    public function test_throws_for_unsupported_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unsupported type/');

        // Resources are the only native PHP type not handled
        $resource = fopen('php://memory', 'r');
        try {
            PhpExporter::export($resource);
        } finally {
            fclose($resource);
        }
    }
}

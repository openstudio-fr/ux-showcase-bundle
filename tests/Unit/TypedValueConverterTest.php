<?php

declare(strict_types=1);

/*
 * This file is part of the openstudio/ux-showcase-bundle package.
 *
 * (c) OpenStudio
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OpenStudio\UxShowcaseBundle\Tests\Unit;

use OpenStudio\UxShowcaseBundle\Preview\InvalidPostedValuesException;
use OpenStudio\UxShowcaseBundle\Preview\PropShape;
use OpenStudio\UxShowcaseBundle\Preview\TypedValueConverter;
use OpenStudio\UxShowcaseBundle\Preview\ValueSerializer;
use OpenStudio\UxShowcaseBundle\Props\PropDoc;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\Align;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\Circle;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\Layout;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\Plain;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\VehicleCard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TypedValueConverterTest extends TestCase
{
    private const CARD = 'OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\VehicleCard';
    private const SHAPE = 'OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\Shape';
    private const PLAIN = 'OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\Plain';
    private const LAYOUT = 'OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\Layout';
    private const ALIGN = 'OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\Align';

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function editableTypes(): iterable
    {
        yield 'string' => ['string', true];
        yield 'nullable' => ['?int', true];
        yield 'union with null' => ['string|null', true];
        yield 'html' => ['html', true];
        yield 'list of objects' => ['list<\\'.self::CARD.'>', true];
        yield 'map of mixed' => ['array<string, mixed>', true];
        yield 'class' => ['\\'.self::CARD, true];
        yield 'enum' => [self::LAYOUT, true];
        yield 'date' => ['?\DateTimeImmutable', true];
        yield 'interface with a discriminator map' => [self::SHAPE, true];
        yield 'mixed' => ['mixed', false];
        yield 'object' => ['object', false];
        yield 'interface without discriminator' => [self::PLAIN, false];
        yield 'union with such an interface' => [self::LAYOUT.'|'.self::PLAIN, false];
        yield 'class that does not exist' => ['VehicleDto', false];
        yield 'callable' => ['callable', false];
        yield 'an abstract class' => ['\FilterIterator', false];
        yield 'a type that cannot be read' => ['list<', false];
    }

    #[DataProvider('editableTypes')]
    public function testWhichTypesAreEditable(string $type, bool $editable): void
    {
        self::assertSame($editable, $this->converter()->isEditable(PropDoc::create('p', $type)));
    }

    public function testAnObjectHandedOverByAHostStepIsKeptWhenItSatisfiesTheType(): void
    {
        $object = new class implements Plain {
        };

        self::assertSame($object, $this->converter()->convert($object, PropDoc::create('p', self::PLAIN), false));
        $this->expectRefusal('p', 'not editable', $object, 'mixed');
    }

    public function testAnObjectOfAnotherTypeIsRefused(): void
    {
        $this->expectRefusal('p', 'not editable', new \stdClass(), self::PLAIN);
    }

    public function testAnInterfaceWithADiscriminatorMapIsBuiltFromItsTypeField(): void
    {
        $shape = $this->converter()->convert(['type' => 'circle', 'radius' => 2], PropDoc::create('p', self::SHAPE), false);

        self::assertEquals(new Circle(2.0), $shape);
        $this->expectRefusal('p.type', '', ['type' => 'square'], self::SHAPE);
    }

    public function testScalarsFromJsonAreStrictAndFromAFormAreConverted(): void
    {
        $converter = $this->converter();

        self::assertSame(12, $converter->convert(12, PropDoc::create('p', 'int'), false));
        self::assertSame(12, $converter->convert('12', PropDoc::create('p', 'int'), true));
        self::assertSame(1.5, $converter->convert('1.5', PropDoc::create('p', 'float'), true));
        self::assertSame(2.0, $converter->convert(2, PropDoc::create('p', 'float'), false));
        self::assertTrue($converter->convert('on', PropDoc::create('p', 'bool'), true));
        self::assertFalse($converter->convert('0', PropDoc::create('p', 'bool'), true));
        $this->expectRefusal('p', 'Expected an integer, got "12"', '12', 'int');
    }

    public function testAnEmptyFormFieldIsNullUnlessTheTypeTakesText(): void
    {
        $converter = $this->converter();

        self::assertNull($converter->convert('', PropDoc::create('p', '?int'), true));
        self::assertNull($converter->convert('', PropDoc::create('p', '?'.self::LAYOUT), true));
        self::assertSame('', $converter->convert('', PropDoc::create('p', '?string'), true));
        self::assertSame('', $converter->convert('', PropDoc::create('p', 'string|null'), true));
    }

    public function testAUnionTakesTheMemberTheValueIsExactly(): void
    {
        $converter = $this->converter();
        $prop = PropDoc::create('p', 'int|string');

        self::assertSame(12, $converter->convert(12, $prop, false));
        self::assertSame('12', $converter->convert('12', $prop, false));
        self::assertSame(12.5, $converter->convert('12.5', PropDoc::create('p', 'int|float'), true));
    }

    public function testAUnionTakesTheOnlyMemberThatAcceptsTheValue(): void
    {
        $converter = $this->converter();

        self::assertSame(Layout::Rows, $converter->convert('rows', PropDoc::create('p', self::LAYOUT.'|\\'.self::CARD), false));
        self::assertEquals(new Circle(3.0), $converter->convert(['radius' => 3], PropDoc::create('p', self::CARD.'|\\'.Circle::class), false));
        self::assertEquals(new VehicleCard('A'), $converter->convert(['name' => 'A'], PropDoc::create('p', self::CARD.'|\\'.Circle::class), false));
    }

    public function testAUnionThatSeveralDifferentMembersAcceptIsAmbiguous(): void
    {
        $this->expectRefusal('p', 'ambiguous', ['name' => 'A'], self::CARD.'|array');
    }

    public function testAUnionThatNoMemberAcceptsSaysWhatItExpected(): void
    {
        $this->expectRefusal('p', 'Expected int or '.self::LAYOUT.', got "x"', 'x', 'int|'.self::LAYOUT);
    }

    public function testAUnionKeepsTheFieldErrorOfTheMemberThatTookTheObject(): void
    {
        $this->expectRefusal('p.price', 'Expected float, got string', ['name' => 'A', 'price' => 'x'], self::CARD.'|int');
    }

    public function testEnums(): void
    {
        $converter = $this->converter();

        self::assertSame(Layout::Grid, $converter->convert('grid', PropDoc::create('p', self::LAYOUT), false));
        self::assertSame(Align::Left, $converter->convert('Left', PropDoc::create('p', self::ALIGN), false));
        $this->expectRefusal('p', 'is not a case of', 'left', self::ALIGN);
    }

    public function testListsAreReindexedAndEachItemIsChecked(): void
    {
        $converter = $this->converter();

        self::assertSame([1, 2], $converter->convert([3 => 1, 5 => '2'], PropDoc::create('p', 'list<int>'), true));
        self::assertSame(['a' => 1], $converter->convert(['a' => 1], PropDoc::create('p', 'array<string, int>'), false));
        $this->expectRefusal('p', 'Expected a list, got an object', ['a' => 1], 'list<int>');
    }

    public function testEveryFaultyItemIsReportedWithItsPath(): void
    {
        try {
            $this->converter()->convert(['a' => ['name' => 'A', 'price' => 'x'], 'b' => ['name' => 'B'], 'c' => ['price' => 1]], PropDoc::create('named', 'array<string, \\'.self::CARD.'>'), false);
            self::fail('Expected a refusal.');
        } catch (InvalidPostedValuesException $exception) {
            self::assertSame(['named[a].price', 'named[c].name'], array_map(static fn ($error): string => $error->path, $exception->errors));
        }
    }

    public function testLiteralTypes(): void
    {
        self::assertSame('b', $this->converter()->convert('b', PropDoc::create('p', "'a'|'b'"), false));
        $this->expectRefusal('p', '', 'c', "'a'|'b'");
    }

    public function testDatesAreIsoText(): void
    {
        $converter = $this->converter();
        $date = $converter->convert('2026-11-26T10:00:00+01:00', PropDoc::create('p', '\DateTimeImmutable'), false);

        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2026-11-26T09:00:00+00:00', $date->setTimezone(new \DateTimeZone('UTC'))->format('c'));
        self::assertInstanceOf(\DateTimeInterface::class, $converter->convert('2026-11-26', PropDoc::create('p', \DateTimeInterface::class), false));
        $this->expectRefusal('p', 'ISO 8601', 'next monday', '\DateTimeImmutable');
    }

    /**
     * @return iterable<string, array{string, string|null, bool, list<bool|int|string>, bool}> type, kind, nullable, choices, numeric text
     */
    public static function shapes(): iterable
    {
        yield 'string' => ['string', PropShape::TEXT, false, [], false];
        yield 'nullable string' => ['?string', PropShape::TEXT, true, [], false];
        yield 'int' => ['int', PropShape::INTEGER, false, [], false];
        yield 'int range' => ['int<0, 10>', PropShape::INTEGER, false, [], false];
        yield 'float' => ['float', PropShape::NUMBER, false, [], false];
        yield 'int or float' => ['int|float|null', PropShape::NUMBER, true, [], false];
        yield 'bool' => ['bool', PropShape::BOOLEAN, false, [], false];
        yield 'literal strings' => ["'sm'|'md'|'lg'", PropShape::CHOICE, false, ['sm', 'md', 'lg'], false];
        yield 'literal strings or null' => ["'sm'|'md'|null", PropShape::CHOICE, true, ['sm', 'md'], false];
        yield 'literal ints' => ['1|2|3', PropShape::CHOICE, false, [1, 2, 3], false];
        yield 'one literal' => ["'only'", PropShape::CHOICE, false, ['only'], false];
        yield 'backed enum' => [self::LAYOUT, PropShape::CHOICE, false, ['grid', 'rows'], false];
        yield 'nullable unit enum' => ['?'.self::ALIGN, PropShape::CHOICE, true, ['Left', 'Right'], false];
        yield 'int or string' => ['int|string', PropShape::TEXT, false, [], true];
        yield 'string or null' => ['string|null', PropShape::TEXT, true, [], false];
        yield 'date' => ['?\DateTimeImmutable', PropShape::TEXT, true, [], false];
        yield 'list' => ['list<string>', PropShape::JSON, false, [], false];
        yield 'map' => ['array<string, int>', PropShape::JSON, false, [], false];
        yield 'object' => ['\\'.self::CARD, PropShape::JSON, false, [], false];
        yield 'list of objects or null' => ['list<\\'.self::CARD.'>|null', PropShape::JSON, true, [], false];
        yield 'object or text' => ['\\'.self::CARD.'|string', PropShape::JSON, false, [], false];
        yield 'mixed' => ['mixed', null, false, [], false];
        yield 'object type' => ['?object', null, false, [], false];
        yield 'interface without a map' => [self::PLAIN, null, false, [], false];
    }

    /**
     * @param list<bool|int|string> $choices
     */
    #[DataProvider('shapes')]
    public function testWhichControlATypeAsksFor(string $type, ?string $kind, bool $nullable, array $choices, bool $numeric): void
    {
        $shape = $this->converter()->shape(PropDoc::create('p', $type));

        if (null === $kind) {
            self::assertNull($shape);

            return;
        }

        self::assertNotNull($shape);
        self::assertSame([$kind, $nullable, $choices, $numeric], [$shape->kind, $shape->nullable, $shape->choices, $shape->numeric]);
    }

    public function testAllowedValuesAreTheChoicesWhateverTheType(): void
    {
        $shape = $this->converter()->shape(PropDoc::create('type', 'string', allowedValues: ['info', null, 'warning']));

        self::assertNotNull($shape);
        self::assertSame([PropShape::CHOICE, true, ['info', 'warning']], [$shape->kind, $shape->nullable, $shape->choices]);
    }

    public function testNullIsNotRefusedByTheAllowedValuesOfAPropThatTakesNull(): void
    {
        $nullable = PropDoc::create('layout', '?string', allowedValues: ['grid', 'rows']);
        self::assertNull($this->converter()->convert('', $nullable, true), 'the empty choice of a form');
        self::assertNull($this->converter()->convert(null, $nullable, false));

        $this->expectException(InvalidPostedValuesException::class);
        $this->converter()->convert(null, PropDoc::create('layout', 'string', allowedValues: ['grid', 'rows']), false);
    }

    public function testTheJsonOfAFormFieldIsDecodedWithTheTypesOfJson(): void
    {
        $prop = PropDoc::create('vehicle', '\\'.self::CARD);

        $card = $this->converter()->convert('{"name":"Golf","price":3}', $prop, true);

        self::assertInstanceOf(VehicleCard::class, $card);
        self::assertSame(3.0, $card->price, 'an int is a float where a float is declared, as in a JSON body');
        self::assertSame(['a', 'b'], $this->converter()->convert('["a","b"]', PropDoc::create('tags', 'list<string>'), true));
    }

    public function testAFormFieldThatIsNotJsonIsRefusedAtItsPropUnlessTheTypeTakesText(): void
    {
        try {
            $this->converter()->convert('{nope', PropDoc::create('vehicle', '\\'.self::CARD), true);
            self::fail('Expected a refusal.');
        } catch (InvalidPostedValuesException $exception) {
            self::assertSame('vehicle', $exception->errors[0]->path);
            self::assertStringStartsWith('Not valid JSON', $exception->errors[0]->message);
        }
    }

    public function testATypeThatTakesObjectsOrTextKeepsTheTextOfAFormAsIs(): void
    {
        self::assertSame('plain words', $this->converter()->convert('plain words', PropDoc::create('p', '\\'.self::CARD.'|string'), true));
    }

    public function testAnAllowedValuesListOfThePropIsEnforced(): void
    {
        $prop = PropDoc::create('type', 'string', allowedValues: ['info', 'warning']);

        self::assertSame('info', $this->converter()->convert('info', $prop, false));
        try {
            $this->converter()->convert('purple', $prop, false);
            self::fail('Expected a refusal.');
        } catch (InvalidPostedValuesException $exception) {
            self::assertSame('type', $exception->errors[0]->path);
            self::assertSame('Expected one of: "info", "warning".', $exception->errors[0]->message);
        }
    }

    public function testAConstructorThatThrowsIsARefusalNotAnError(): void
    {
        $this->expectRefusal('p', 'must be positive', ['n' => -1], '\\'.Positive::class);
    }

    private function expectRefusal(string $path, string $message, mixed $value, string $type): void
    {
        try {
            $this->converter()->convert($value, PropDoc::create('p', $type), false);
        } catch (InvalidPostedValuesException $exception) {
            self::assertSame($path, $exception->errors[0]->path);
            self::assertStringContainsString($message, $exception->errors[0]->message);

            return;
        }

        self::fail('Expected a refusal.');
    }

    private function converter(): TypedValueConverter
    {
        return new TypedValueConverter(new ValueSerializer());
    }
}

final readonly class Positive
{
    public function __construct(public int $n)
    {
        if ($n < 0) {
            throw new \DomainException('n must be positive');
        }
    }
}

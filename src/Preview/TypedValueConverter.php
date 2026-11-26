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

namespace OpenStudio\UxShowcaseBundle\Preview;

use OpenStudio\UxShowcaseBundle\Props\PropDoc;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFalseNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprNullNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprTrueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\ParserException;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;

/**
 * Turns a value posted for a prop into what the prop declares: the type of the `PropDoc` (a string such as `?string`,
 * `list<App\Dto\Vehicle>`, `App\Suit|null`) is read with phpstan/phpdoc-parser, then.
 *
 * - scalars are checked (text sent by a form is converted: "12" to 12, "on" to true);
 * - backed enums by value, unit enums by case name; dates from ISO 8601 text;
 * - objects (constructor-promoted or settable properties, nested objects, lists of objects) by the Serializer, and
 *   interfaces or abstract classes only when they carry a `#[DiscriminatorMap]`;
 * - lists and maps item by item, with the path of the faulty item (`vehicles[1].price`);
 * - unions by the member the value matches, an error when several different ones do.
 *
 * `mixed`, `object`, `callable`, an interface with no discriminator and a class that cannot be found are not editable:
 * a value posted for them is refused, whatever it is. An object already built (by a PostedValuesNormalizerInterface) is
 * kept when it satisfies the type.
 *
 * @internal
 */
final readonly class TypedValueConverter
{
    private const array STRINGS = ['string', 'html', 'non-empty-string', 'numeric-string', 'class-string', 'lowercase-string', 'uppercase-string', 'literal-string', 'truthy-string', 'non-falsy-string'];
    private const array INTS = ['int', 'integer', 'positive-int', 'negative-int', 'non-negative-int', 'non-positive-int', 'non-zero-int'];
    private const array FLOATS = ['float', 'double'];
    private const array BOOLS = ['bool', 'boolean', 'true', 'false'];
    private const array ARRAYS = ['array', 'iterable', 'non-empty-array', 'list', 'non-empty-list'];
    private const array NOT_EDITABLE = ['object', 'callable', 'resource', 'closed-resource', 'self', 'static', 'parent', 'void', 'never', 'callable-string', 'callable-array'];

    private Lexer $lexer;
    private TypeParser $typeParser;

    public function __construct(private ValueSerializer $serializer)
    {
        $config = new ParserConfig([]);
        $this->lexer = new Lexer($config);
        $this->typeParser = new TypeParser($config, new ConstExprParser($config));
    }

    public function isEditable(PropDoc $prop): bool
    {
        try {
            return $this->editable($this->parse($prop->type), false);
        } catch (ParserException) {
            return false;
        }
    }

    /**
     * @throws InvalidPostedValuesException with the path of every faulty value
     */
    public function convert(mixed $value, PropDoc $prop, bool $fromForm): mixed
    {
        try {
            $node = $this->parse($prop->type);
        } catch (ParserException) {
            throw InvalidPostedValuesException::at($prop->name, \sprintf('This prop is not editable: its type "%s" cannot be read.', $prop->type));
        }

        // Only a step of the host can hand over an object: it is kept when it is of the declared class or interface.
        if (\is_object($value) && $this->accepts($node, $value)) {
            return $value;
        }

        if (!$this->editable($node, false)) {
            throw InvalidPostedValuesException::at($prop->name, \sprintf('This prop is not editable: its type is "%s".', $prop->type));
        }

        $converted = $this->node($node, $value, $prop->name, $fromForm);

        if (null !== $prop->allowedValues && (null === $converted || \is_scalar($converted)) && !\in_array($converted, $prop->allowedValues, true)) {
            throw InvalidPostedValuesException::at($prop->name, \sprintf('Expected one of: %s.', implode(', ', array_map(static fn (mixed $allowed): string => json_encode($allowed, \JSON_THROW_ON_ERROR), $prop->allowedValues))));
        }

        return $converted;
    }

    private function accepts(TypeNode $node, object $value): bool
    {
        return match (true) {
            $node instanceof NullableTypeNode => $this->accepts($node->type, $value),
            $node instanceof UnionTypeNode => array_reduce($node->types, fn (bool $any, TypeNode $member): bool => $any || $this->accepts($member, $value), false),
            $node instanceof IdentifierTypeNode => !$this->isBuiltin($this->name($node)) && !\in_array($this->name($node), self::NOT_EDITABLE, true) && is_a($value, ltrim($node->name, '\\')),
            default => false,
        };
    }

    private function parse(string $type): TypeNode
    {
        $tokens = new TokenIterator($this->lexer->tokenize($type));
        $node = $this->typeParser->parse($tokens);
        $tokens->consumeTokenType(Lexer::TOKEN_END);

        return $node;
    }

    private function editable(TypeNode $node, bool $nested): bool
    {
        return match (true) {
            $node instanceof NullableTypeNode => $this->editable($node->type, $nested),
            $node instanceof UnionTypeNode => array_reduce($node->types, fn (bool $all, TypeNode $member): bool => $all && $this->editable($member, $nested), true),
            $node instanceof ConstTypeNode => $node->constExpr instanceof ConstExprStringNode || $node->constExpr instanceof ConstExprIntegerNode || $node->constExpr instanceof ConstExprTrueNode || $node->constExpr instanceof ConstExprFalseNode || $node->constExpr instanceof ConstExprNullNode,
            $node instanceof ArrayShapeNode => true,
            $node instanceof ArrayTypeNode => $this->editable($node->type, true),
            $node instanceof GenericTypeNode => $this->genericEditable($node),
            $node instanceof IdentifierTypeNode => $this->identifierEditable($node->name, $nested),
            default => false,
        };
    }

    private function genericEditable(GenericTypeNode $node): bool
    {
        $name = $this->name((string) $node->type);

        if (\in_array($name, self::ARRAYS, true)) {
            $values = $node->genericTypes;

            return [] !== $values && $this->editable($values[array_key_last($values)], true);
        }

        return \in_array($name, [...self::INTS, 'class-string'], true);
    }

    private function identifierEditable(string $name, bool $nested): bool
    {
        $lower = $this->name($name);

        return match (true) {
            'mixed' === $lower => $nested,
            \in_array($lower, self::NOT_EDITABLE, true) => false,
            $this->isBuiltin($lower) => true,
            default => $this->classEditable(ltrim($name, '\\')),
        };
    }

    private function classEditable(string $class): bool
    {
        if (enum_exists($class) || $this->isDate($class)) {
            return true;
        }

        if (class_exists($class)) {
            return (new \ReflectionClass($class))->isInstantiable() || null !== $this->serializer->discriminator->getMappingForClass($class);
        }

        return interface_exists($class) && null !== $this->serializer->discriminator->getMappingForClass($class);
    }

    private function isBuiltin(string $lower): bool
    {
        return \in_array($lower, [...self::STRINGS, ...self::INTS, ...self::FLOATS, ...self::BOOLS, ...self::ARRAYS, 'null', 'scalar', 'numeric', 'array-key', 'mixed'], true);
    }

    private function isDate(string $class): bool
    {
        return \in_array(strtolower(ltrim($class, '\\')), [strtolower(\DateTimeInterface::class), strtolower(\DateTimeImmutable::class), strtolower(\DateTime::class)], true);
    }

    private function name(IdentifierTypeNode|string $node): string
    {
        return strtolower(ltrim(\is_string($node) ? $node : $node->name, '\\'));
    }

    private function node(TypeNode $node, mixed $value, string $path, bool $form): mixed
    {
        return match (true) {
            $node instanceof NullableTypeNode => $this->nullable($node->type, $value, $path, $form),
            $node instanceof UnionTypeNode => $this->union($node, $value, $path, $form),
            $node instanceof ConstTypeNode => $this->constant($node, $value, $path),
            $node instanceof ArrayShapeNode => $this->arrayOf(null, false, $value, $path, $form),
            $node instanceof ArrayTypeNode => $this->arrayOf($node->type, false, $value, $path, $form),
            $node instanceof GenericTypeNode => $this->generic($node, $value, $path, $form),
            $node instanceof IdentifierTypeNode => $this->identifier($node->name, $value, $path, $form),
            default => throw InvalidPostedValuesException::at($path, 'This type is not editable.'),
        };
    }

    private function nullable(TypeNode $inner, mixed $value, string $path, bool $form): mixed
    {
        // A form cannot say null: an empty field is null, unless the type takes text, where it is an empty text.
        if (null === $value || ($form && '' === $value && !$this->acceptsText($inner))) {
            return null;
        }

        return $this->node($inner, $value, $path, $form);
    }

    private function acceptsText(TypeNode $node): bool
    {
        return match (true) {
            $node instanceof NullableTypeNode => $this->acceptsText($node->type),
            $node instanceof UnionTypeNode => array_reduce($node->types, fn (bool $any, TypeNode $member): bool => $any || $this->acceptsText($member), false),
            $node instanceof IdentifierTypeNode => \in_array($this->name($node), [...self::STRINGS, 'scalar', 'mixed'], true),
            default => false,
        };
    }

    private function union(UnionTypeNode $union, mixed $value, string $path, bool $form): mixed
    {
        $members = [];
        $acceptsNull = false;
        foreach ($union->types as $member) {
            if ($member instanceof IdentifierTypeNode && 'null' === $this->name($member)) {
                $acceptsNull = true;
            } else {
                $members[] = $member;
            }
        }

        if ($acceptsNull && (null === $value || ($form && '' === $value && !array_reduce($members, fn (bool $any, TypeNode $member): bool => $any || $this->acceptsText($member), false)))) {
            return null;
        }

        // The member that is exactly the type of the value wins: 12 is an int, "12" a string, in `int|string`.
        foreach ($members as $member) {
            if ($this->isExactly($member, $value)) {
                return $this->node($member, $value, $path, $form);
            }
        }

        $matches = [];
        $problems = [];
        foreach ($members as $member) {
            try {
                $matches[] = [$member, $this->node($member, $value, $path, $form)];
            } catch (InvalidPostedValuesException $exception) {
                array_push($problems, ...$exception->errors);
            }
        }

        if (1 === \count($matches)) {
            return $matches[0][1];
        }

        if ([] === $matches) {
            // A member that took the value as its own and found a field wrong says more than "no member fits".
            $deeper = array_values(array_filter($problems, static fn (PostedValueError $problem): bool => $problem->path !== $path));

            throw [] !== $deeper ? new InvalidPostedValuesException($deeper) : InvalidPostedValuesException::at($path, \sprintf('Expected %s, got %s.', $this->describeUnion($members), $this->describe($value)));
        }

        // Several members take the value: harmless between scalars (the first declared), ambiguous otherwise.
        foreach ($matches as [$member]) {
            if (!$member instanceof IdentifierTypeNode || !$this->isBuiltin($this->name($member))) {
                throw InvalidPostedValuesException::at($path, 'The value matches several types of the union: it is ambiguous.');
            }
        }

        return $matches[0][1];
    }

    /**
     * @param list<TypeNode> $members
     */
    private function describeUnion(array $members): string
    {
        return implode(' or ', array_map(static fn (TypeNode $member): string => (string) $member, $members));
    }

    private function isExactly(TypeNode $member, mixed $value): bool
    {
        if (!$member instanceof IdentifierTypeNode) {
            return false;
        }

        $name = $this->name($member);

        return match (true) {
            \is_string($value) => \in_array($name, self::STRINGS, true),
            \is_int($value) => \in_array($name, self::INTS, true),
            \is_float($value) => \in_array($name, self::FLOATS, true),
            \is_bool($value) => \in_array($name, self::BOOLS, true),
            default => false,
        };
    }

    private function constant(ConstTypeNode $node, mixed $value, string $path): mixed
    {
        $expected = match (true) {
            $node->constExpr instanceof ConstExprStringNode => $node->constExpr->value,
            $node->constExpr instanceof ConstExprIntegerNode => (int) $node->constExpr->value,
            $node->constExpr instanceof ConstExprTrueNode => true,
            $node->constExpr instanceof ConstExprFalseNode => false,
            default => null,
        };

        if ($value !== $expected) {
            throw InvalidPostedValuesException::at($path, \sprintf('Expected %s, got %s.', json_encode($expected, \JSON_THROW_ON_ERROR), $this->describe($value)));
        }

        return $value;
    }

    private function generic(GenericTypeNode $node, mixed $value, string $path, bool $form): mixed
    {
        $name = $this->name((string) $node->type);

        if (\in_array($name, self::ARRAYS, true)) {
            return $this->arrayOf($node->genericTypes[array_key_last($node->genericTypes)], \in_array($name, ['list', 'non-empty-list'], true), $value, $path, $form);
        }

        return $this->identifier(\in_array($name, self::INTS, true) ? 'int' : 'string', $value, $path, $form);
    }

    /**
     * @return array<mixed>
     */
    private function arrayOf(?TypeNode $items, bool $list, mixed $value, string $path, bool $form): array
    {
        if (!\is_array($value)) {
            throw InvalidPostedValuesException::at($path, \sprintf('Expected %s, got %s.', $list ? 'a list' : 'an array', $this->describe($value)));
        }

        if ($list) {
            if ([] !== array_filter(array_keys($value), 'is_string')) {
                throw InvalidPostedValuesException::at($path, 'Expected a list, got an object.');
            }
            $value = array_values($value);
        }

        if (null === $items) {
            return $value;
        }

        $converted = [];
        $errors = [];
        foreach ($value as $key => $item) {
            try {
                $converted[$key] = $this->node($items, $item, \sprintf('%s[%s]', $path, $key), $form);
            } catch (InvalidPostedValuesException $exception) {
                array_push($errors, ...$exception->errors);
            }
        }

        if ([] !== $errors) {
            throw new InvalidPostedValuesException($errors);
        }

        return $converted;
    }

    private function identifier(string $name, mixed $value, string $path, bool $form): mixed
    {
        $lower = $this->name($name);

        return match (true) {
            \in_array($lower, self::STRINGS, true) => $this->text($value, $path),
            \in_array($lower, self::INTS, true) => $this->integer($value, $path, $form),
            \in_array($lower, self::FLOATS, true) => $this->float($value, $path, $form),
            \in_array($lower, self::BOOLS, true) => $this->boolean($value, $path, $form),
            'null' === $lower => null === $value ? null : throw InvalidPostedValuesException::at($path, \sprintf('Expected null, got %s.', $this->describe($value))),
            'mixed' === $lower => $value,
            'scalar' === $lower => null !== $value && \is_scalar($value) ? $value : throw InvalidPostedValuesException::at($path, \sprintf('Expected a scalar, got %s.', $this->describe($value))),
            'numeric' === $lower => $this->numeric($value, $path, $form),
            'array-key' === $lower => \is_int($value) || \is_string($value) ? $value : throw InvalidPostedValuesException::at($path, \sprintf('Expected an int or a string, got %s.', $this->describe($value))),
            \in_array($lower, self::ARRAYS, true) => $this->arrayOf(null, \in_array($lower, ['list', 'non-empty-list'], true), $value, $path, $form),
            default => $this->instance(ltrim($name, '\\'), $value, $path, $form),
        };
    }

    private function text(mixed $value, string $path): string
    {
        return match (true) {
            \is_string($value) => $value,
            \is_int($value), \is_float($value) => (string) $value,
            default => throw InvalidPostedValuesException::at($path, \sprintf('Expected a string, got %s.', $this->describe($value))),
        };
    }

    private function integer(mixed $value, string $path, bool $form): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if ($form && \is_string($value) && 1 === preg_match('/^-?[0-9]+$/', $value)) {
            return (int) $value;
        }

        throw InvalidPostedValuesException::at($path, \sprintf('Expected an integer, got %s.', $this->describe($value)));
    }

    private function float(mixed $value, string $path, bool $form): float
    {
        if (\is_float($value) || \is_int($value)) {
            return (float) $value;
        }

        if ($form && \is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        throw InvalidPostedValuesException::at($path, \sprintf('Expected a number, got %s.', $this->describe($value)));
    }

    private function numeric(mixed $value, string $path, bool $form): int|float
    {
        if (\is_int($value) || \is_float($value)) {
            return $value;
        }

        if ($form && \is_string($value) && is_numeric($value)) {
            return $value + 0;
        }

        throw InvalidPostedValuesException::at($path, \sprintf('Expected a number, got %s.', $this->describe($value)));
    }

    private function boolean(mixed $value, string $path, bool $form): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if ($form && \is_string($value)) {
            $known = ['1' => true, 'true' => true, 'on' => true, 'yes' => true, '0' => false, 'false' => false, 'off' => false, 'no' => false];
            if (isset($known[strtolower($value)])) {
                return $known[strtolower($value)];
            }
        }

        throw InvalidPostedValuesException::at($path, \sprintf('Expected true or false, got %s.', $this->describe($value)));
    }

    private function instance(string $class, mixed $value, string $path, bool $form): object
    {
        if (\is_object($value)) {
            if ($value instanceof $class) {
                return $value;
            }

            throw InvalidPostedValuesException::at($path, \sprintf('Expected %s, got %s.', $class, get_debug_type($value)));
        }

        return match (true) {
            enum_exists($class) => $this->enum($class, $value, $path),
            $this->isDate($class) => $this->date($class, $value, $path),
            default => $this->object($class, $value, $path, $form),
        };
    }

    /**
     * @param class-string<\UnitEnum> $class
     */
    private function enum(string $class, mixed $value, string $path): \UnitEnum
    {
        $names = [];
        if (\is_string($value) || \is_int($value) || \is_float($value)) {
            foreach ($class::cases() as $case) {
                $key = $case instanceof \BackedEnum ? $case->value : $case->name;
                $names[] = (string) $key;
                if ((string) $key === (string) $value) {
                    return $case;
                }
            }
        }

        throw InvalidPostedValuesException::at($path, \sprintf('%s is not a case of %s; expected one of: %s.', $this->describe($value), $class, implode(', ', $names ?: array_map(static fn (\UnitEnum $case): string => $case instanceof \BackedEnum ? (string) $case->value : $case->name, $class::cases()))));
    }

    private function date(string $class, mixed $value, string $path): \DateTimeInterface
    {
        // ISO 8601 text only: PHP would also read "tomorrow" or "last monday".
        if (!\is_string($value) || 1 !== preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}/', $value)) {
            throw InvalidPostedValuesException::at($path, \sprintf('Expected a date as ISO 8601 text (2026-11-26 or 2026-11-26T10:00:00+01:00), got %s.', $this->describe($value)));
        }

        try {
            $date = $this->serializer->serializer->denormalize($value, $class, 'json');
        } catch (ExceptionInterface) {
            throw InvalidPostedValuesException::at($path, \sprintf('"%s" is not a valid date.', $value));
        }

        \assert($date instanceof \DateTimeInterface);

        return $date;
    }

    private function object(string $class, mixed $value, string $path, bool $form): object
    {
        if (!\is_array($value)) {
            throw InvalidPostedValuesException::at($path, \sprintf('Expected an object (%s), got %s.', $class, $this->describe($value)));
        }

        try {
            $object = $this->serializer->serializer->denormalize($value, $class, $form ? 'csv' : 'json', [
                'collect_denormalization_errors' => true,
                'allow_extra_attributes' => false,
                'deserialization_path' => $path,
            ]);
        } catch (\Exception|\TypeError $exception) { // @phpstan-ignore catch.neverThrown
            throw $this->refusalOf($exception, $path);
        }

        \assert(\is_object($object));

        return $object;
    }

    /**
     * What the Serializer, a constructor or a setter refused, with the path of each faulty field: the request is wrong,
     * not the server.
     */
    private function refusalOf(\Throwable $exception, string $path): InvalidPostedValuesException
    {
        return match (true) {
            $exception instanceof PartialDenormalizationException => new InvalidPostedValuesException(array_values(array_map(fn (NotNormalizableValueException $error): PostedValueError => new PostedValueError($error->getPath() ?? $path, $this->messageOf($error)), $exception->getErrors()))),
            $exception instanceof ExtraAttributesException => new InvalidPostedValuesException(array_values(array_map(static fn (mixed $field): PostedValueError => new PostedValueError(self::fieldPath($path, $field), 'Unknown field.'), $exception->getExtraAttributes()))),
            $exception instanceof MissingConstructorArgumentsException => new InvalidPostedValuesException(array_values(array_map(static fn (mixed $field): PostedValueError => new PostedValueError(self::fieldPath($path, $field), 'Required field.'), $exception->getMissingConstructorArguments()))),
            $exception instanceof NotNormalizableValueException => InvalidPostedValuesException::at($exception->getPath() ?? $path, $this->messageOf($exception)),
            default => InvalidPostedValuesException::at($path, $exception->getMessage()),
        };
    }

    /**
     * Symfony 7.4 names an extra field `color`, 8.x `vehicle.color` when a deserialization path is given.
     */
    private static function fieldPath(string $path, mixed $field): string
    {
        $field = \is_scalar($field) ? (string) $field : '?';

        return str_starts_with($field, $path.'.') ? $field : $path.'.'.$field;
    }

    private function messageOf(NotNormalizableValueException $error): string
    {
        $expected = $error->getExpectedTypes();
        $current = $error->getCurrentType();

        if (null !== $expected && [] !== $expected && null !== $current) {
            return \sprintf('Expected %s, got %s.', implode(' or ', $expected), $current);
        }

        return $error->getMessage();
    }

    private function describe(mixed $value): string
    {
        return match (true) {
            null === $value => 'null',
            '' === $value => 'an empty string',
            \is_string($value) => \sprintf('"%s"', mb_strimwidth($value, 0, 40, '…')),
            \is_scalar($value) => json_encode($value, \JSON_THROW_ON_ERROR),
            default => get_debug_type($value),
        };
    }
}

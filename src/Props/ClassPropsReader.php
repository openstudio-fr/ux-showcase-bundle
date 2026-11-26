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

namespace OpenStudio\UxShowcaseBundle\Props;

use OpenStudio\UxShowcaseBundle\Library\ComponentCatalog;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * Reads the class of a component with reflection: its public properties, the parameters of `mount()` and the allowed
 * values a `#[PreMount]` method declares. Types come from symfony/property-info (native types and PHPDoc, generics
 * included); an anonymous component has no class and gives nothing.
 *
 * What is not a prop: a public property `mount()` assigns (it is computed from the arguments, which are the props), a
 * property or a method marked `#[ExposeInTemplate]` that is not public (a variable for the template, not an input),
 * and the properties of the traits of symfony/ux-* (state of the component).
 *
 * @internal
 */
final readonly class ClassPropsReader implements PropsReaderInterface
{
    private PropertyInfoExtractor $types;

    public function __construct(private ComponentCatalog $catalog)
    {
        $this->types = new PropertyInfoExtractor([], [new PhpStanExtractor(), new ReflectionExtractor()]);
    }

    public function read(string $component): ComponentDoc
    {
        $class = $this->catalog->className($component);
        if (null === $class) {
            return new ComponentDoc($component);
        }

        $reflection = new \ReflectionClass($class);
        $mount = $reflection->hasMethod('mount') ? $reflection->getMethod('mount') : null;
        $parameters = [];
        foreach ($mount?->getParameters() ?? [] as $parameter) {
            if (!$parameter->isVariadic()) {
                $parameters[$parameter->getName()] = $parameter;
            }
        }
        $computed = null === $mount ? [] : array_diff(MethodSourceScanner::assignedProperties($mount), array_keys($parameters));
        $internal = $this->frameworkProperties($reflection);

        $props = [];
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            if ($property->isStatic() || \in_array($name, $computed, true) || isset($internal[$name])) {
                continue;
            }
            $props[$name] = $this->fromProperty($class, $property, $reflection);
        }

        if (null !== $mount) {
            $docblock = $this->paramTags($mount);
            foreach ($parameters as $name => $parameter) {
                $fromMount = $this->fromParameter($parameter, $docblock[$name] ?? null, $mount);
                $props[$name] = isset($props[$name]) ? $props[$name]->mergedWith($fromMount) : $fromMount;
            }
        }

        foreach ($reflection->getMethods() as $method) {
            if ([] === $method->getAttributes(PreMount::class)) {
                continue;
            }
            foreach (MethodSourceScanner::allowedValues($method) as $name => $values) {
                if (isset($props[$name])) {
                    $props[$name] = $props[$name]->withAllowedValues($values, PropDoc::SOURCE_PRE_MOUNT);
                }
            }
        }

        return new ComponentDoc($component, $this->summary($reflection->getDocComment()), array_values($props));
    }

    /**
     * @param class-string             $class
     * @param \ReflectionClass<object> $reflection
     */
    private function fromProperty(string $class, \ReflectionProperty $property, \ReflectionClass $reflection): PropDoc
    {
        $type = $this->types->getType($class, $property->getName());
        $typeName = null === $type ? PropDoc::UNKNOWN_TYPE : self::normalize((string) $type);

        $hasDefault = $property->hasDefaultValue();
        $default = $hasDefault ? $property->getDefaultValue() : null;
        if ($property->isPromoted() && null !== ($constructor = $reflection->getConstructor())) {
            foreach ($constructor->getParameters() as $parameter) {
                if ($parameter->getName() === $property->getName() && $parameter->isDefaultValueAvailable()) {
                    $hasDefault = true;
                    $default = $parameter->getDefaultValue();
                }
            }
        }

        return PropDoc::create(
            $property->getName(),
            $typeName,
            $hasDefault,
            $default,
            $this->summary($property->getDocComment()),
            self::enumValues($typeName),
            [PropDoc::SOURCE_PROPERTY],
        );
    }

    private function fromParameter(\ReflectionParameter $parameter, ?ParamTagValueNode $tag, \ReflectionMethod $mount): PropDoc
    {
        $native = $parameter->getType();
        $type = null === $native ? PropDoc::UNKNOWN_TYPE : self::normalize((string) $native);
        if (null !== $tag) {
            $type = self::normalize(self::tidy(self::qualify((string) $tag->type, $mount->getDeclaringClass())));
        }
        $hasDefault = $parameter->isDefaultValueAvailable();

        return PropDoc::create(
            $parameter->getName(),
            $type,
            $hasDefault,
            $hasDefault ? $parameter->getDefaultValue() : null,
            null === $tag ? '' : TypeSplitter::oneLine($tag->description),
            self::enumValues($type),
            [PropDoc::SOURCE_MOUNT],
        );
    }

    /**
     * The PHPDoc parser prints a union as `(A | B)`: written `A|B`.
     */
    private static function tidy(string $type): string
    {
        $type = preg_replace('/\s*\|\s*/', '|', $type) ?? $type;
        if (str_starts_with($type, '(') && str_ends_with($type, ')')) {
            $depth = 0;
            foreach (str_split($type) as $position => $char) {
                $depth += '(' === $char ? 1 : (')' === $char ? -1 : 0);
                if (0 === $depth && $position < \strlen($type) - 1) {
                    return $type;
                }
            }

            return substr($type, 1, -1);
        }

        return $type;
    }

    /**
     * A type that is one other type or null is written `?type` (`null|string`, `Dto|null`).
     */
    private static function normalize(string $type): string
    {
        $parts = [];
        $depth = 0;
        $current = '';
        foreach (str_split($type) as $char) {
            $depth += match ($char) {
                '<', '{', '(' => 1,
                '>', '}', ')' => -1,
                default => 0,
            };
            if ('|' === $char && 0 === $depth) {
                $parts[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }
        $parts[] = $current;

        $others = array_values(array_filter($parts, static fn (string $part): bool => 'null' !== $part));

        return 2 === \count($parts) && 1 === \count($others) && !str_starts_with($others[0], '?') ? '?'.$others[0] : $type;
    }

    /**
     * @return list<int|string>|null the cases of an enum, their values when it is backed
     */
    private static function enumValues(string $type): ?array
    {
        $name = ltrim(1 === preg_match('/^\??([^|?]+)(\|null)?$/', $type, $match) ? $match[1] : '', '\\');
        if ('' === $name || !enum_exists($name)) {
            return null;
        }

        return array_map(static fn (\UnitEnum $case): int|string => $case instanceof \BackedEnum ? $case->value : $case->name, $name::cases());
    }

    /**
     * The `@param type $name description` tags of a method, by parameter name.
     *
     * @return array<string, ParamTagValueNode>
     */
    private function paramTags(\ReflectionMethod $method): array
    {
        $comment = $method->getDocComment();
        if (false === $comment) {
            return [];
        }

        $config = new ParserConfig([]);
        $constants = new ConstExprParser($config);
        $parser = new PhpDocParser($config, new TypeParser($config, $constants), $constants);
        $node = $parser->parse(new TokenIterator((new Lexer($config))->tokenize($comment)));

        $tags = [];
        foreach ($node->getParamTagValues() as $tag) {
            $tags[ltrim($tag->parameterName, '$')] = $tag;
        }

        return $tags;
    }

    /**
     * Gives a PHPDoc type the full names of its classes, read from the namespace and the `use` of the file.
     *
     * @param \ReflectionClass<object> $class
     */
    private static function qualify(string $type, \ReflectionClass $class): string
    {
        $uses = [];
        $file = $class->getFileName();
        $code = false === $file ? false : file_get_contents($file);
        if (false !== $code && preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $code, $matches, \PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $uses[$match[2] ?? substr($match[1], (int) strrpos($match[1], '\\') + 1)] = $match[1];
            }
        }

        return preg_replace_callback(
            '/(?<![\w\\\\$\'"])\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*(?!\s*\??:)(?![\w\\\\\'"-])/',
            static function (array $match) use ($uses, $class): string {
                $name = $match[0];
                if ('\\' === $name[0]) {
                    return substr($name, 1);
                }
                $first = strstr($name, '\\', true) ?: $name;
                if (!ctype_upper($first[0])) {
                    return $name;
                }
                if (isset($uses[$first])) {
                    return $uses[$first].substr($name, \strlen($first));
                }
                $candidate = ('' === $class->getNamespaceName() ? '' : $class->getNamespaceName().'\\').$name;

                return class_exists($candidate) || interface_exists($candidate) || enum_exists($candidate) ? $candidate : $name;
            },
            $type,
        ) ?? $type;
    }

    /**
     * @param \ReflectionClass<object> $class
     *
     * @return array<string, true> names of the properties of the traits of symfony/ux-*
     */
    private function frameworkProperties(\ReflectionClass $class): array
    {
        $names = [];
        for ($current = $class; false !== $current; $current = $current->getParentClass()) {
            foreach ($this->traits($current) as $trait) {
                if (str_starts_with($trait->getName(), 'Symfony\\UX\\')) {
                    foreach ($trait->getProperties() as $property) {
                        $names[$property->getName()] = true;
                    }
                }
            }
        }

        return $names;
    }

    /**
     * @param \ReflectionClass<object> $class
     *
     * @return list<\ReflectionClass<object>>
     */
    private function traits(\ReflectionClass $class): array
    {
        $traits = [];
        foreach ($class->getTraits() as $trait) {
            $traits[] = $trait;
            array_push($traits, ...$this->traits($trait));
        }

        return $traits;
    }

    /**
     * The first paragraph of a docblock, without its tags.
     */
    private function summary(string|false $docblock): string
    {
        if (false === $docblock) {
            return '';
        }

        $lines = [];
        foreach (preg_split('/\R/', $docblock) ?: [] as $line) {
            $line = trim(preg_replace('/^\s*(\/\*\*|\*\/|\*)\s?|\*\/\s*$/', '', $line) ?? '');
            if (str_starts_with($line, '@') || ('' === $line && [] !== $lines)) {
                break;
            }
            if ('' !== $line) {
                $lines[] = $line;
            }
        }

        return implode(' ', $lines);
    }
}

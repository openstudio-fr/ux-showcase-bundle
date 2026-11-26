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

namespace OpenStudio\UxShowcaseBundle\Example;

/**
 * The code shown for an example, generated from its data: a `<twig:Name prop="…" :prop="…">` snippet. Props keep the
 * order they were declared in. A string without special characters is a plain attribute, anything else a Twig literal.
 *
 * @api
 */
final readonly class ExampleCodeGenerator
{
    private const LINE_WIDTH = 100;
    private const INDENT = '    ';

    public function generate(Example $example): string
    {
        if (null !== $example->source) {
            return $example->source;
        }

        $name = $example->component;
        \assert(null !== $name);

        $attributes = [];
        foreach ($example->props as $prop => $value) {
            $attributes[] = $this->attribute((string) $prop, $value);
        }

        $closing = null === $example->content ? ' />' : '>';
        $inline = '<twig:'.$name.($attributes ? ' '.implode(' ', $attributes) : '').$closing;
        $opening = !str_contains($inline, "\n") && \strlen($inline) <= self::LINE_WIDTH
            ? $inline
            : '<twig:'.$name."\n".self::INDENT.implode("\n".self::INDENT, $attributes)."\n".$closing;
        // A closing bracket alone on its line reads better than ` />` hanging after the last attribute.
        $opening = str_replace("\n />", "\n/>", $opening);

        if (null === $example->content) {
            return $opening;
        }

        return $opening."\n".self::INDENT.str_replace("\n", "\n".self::INDENT, $example->content)."\n</twig:".$name.'>';
    }

    /**
     * The snippet of an example whose props are not literals but the keys of a Twig variable: `:label="props['label']"`.
     * What the preview of tried values is drawn from; the markup of the example is kept as it is.
     *
     * @param list<int|string> $names
     *
     * @internal
     */
    public function generateBound(Example $example, array $names, string $variable): string
    {
        $component = $example->component;
        \assert(null !== $component && null !== $example->content);

        $attributes = array_map(static fn (int|string $name): string => ':'.$name.'="'.$variable.'['.self::literal((string) $name).']"', $names);
        $opening = '<twig:'.$component.($attributes ? ' '.implode(' ', $attributes) : '').'>';

        return $opening."\n".$example->content."\n</twig:".$component.'>';
    }

    private function attribute(string $name, mixed $value): string
    {
        if (\is_string($value) && 1 === preg_match('/^[^"\'\\\\{}\n]*$/', $value)) {
            return $name.'="'.$value.'"';
        }

        return ':'.$name.'="'.self::literal($value).'"';
    }

    public static function literal(mixed $value): string
    {
        return match (true) {
            null === $value => 'null',
            \is_bool($value) => $value ? 'true' : 'false',
            \is_int($value) => (string) $value,
            \is_float($value) => str_contains($text = var_export($value, true), '.') || str_contains($text, 'E') ? $text : $text.'.0',
            \is_string($value) => "'".strtr($value, ['\\' => '\\\\', "'" => "\\'", '"' => '\\x22', "\n" => '\\n', "\r" => '\\r'])."'",
            \is_array($value) => self::collection($value),
            default => throw new \InvalidArgumentException(\sprintf('Cannot write a %s as a Twig literal.', get_debug_type($value))),
        };
    }

    /**
     * @param array<mixed> $value
     */
    private static function collection(array $value): string
    {
        if (array_is_list($value)) {
            return '['.implode(', ', array_map(self::literal(...), $value)).']';
        }

        $pairs = [];
        foreach ($value as $key => $item) {
            $key = (string) $key;
            $pairs[] = (1 === preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) ? $key : self::literal($key)).': '.self::literal($item);
        }

        return '{ '.implode(', ', $pairs).' }';
    }
}

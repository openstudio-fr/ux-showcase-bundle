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

/**
 * Reads the source of a method with the PHP tokenizer, never executing it: the properties it assigns and the allowed
 * values it declares on an OptionsResolver, when they are literals.
 *
 * @internal
 */
final class MethodSourceScanner
{
    private const ASSIGNMENTS = [
        \T_PLUS_EQUAL, \T_MINUS_EQUAL, \T_MUL_EQUAL, \T_DIV_EQUAL, \T_CONCAT_EQUAL, \T_MOD_EQUAL, \T_POW_EQUAL,
        \T_AND_EQUAL, \T_OR_EQUAL, \T_XOR_EQUAL, \T_SL_EQUAL, \T_SR_EQUAL, \T_COALESCE_EQUAL,
    ];

    /**
     * @return list<string> names of the properties of `$this` the method assigns
     */
    public static function assignedProperties(\ReflectionMethod $method): array
    {
        $tokens = self::tokens($method);
        $assigned = [];
        for ($i = 0, $count = \count($tokens) - 3; $i < $count; ++$i) {
            if ('$this' !== self::text($tokens[$i]) || \T_OBJECT_OPERATOR !== self::id($tokens[$i + 1]) || \T_STRING !== self::id($tokens[$i + 2])) {
                continue;
            }

            $next = $i + 3;
            // `$this->items[] = …`, `$this->items['a']['b'] = …`
            while ('[' === self::text($tokens[$next] ?? null)) {
                $depth = 0;
                do {
                    $depth += match (self::text($tokens[$next] ?? null)) {
                        '[' => 1,
                        ']' => -1,
                        default => 0,
                    };
                    ++$next;
                } while ($depth > 0 && isset($tokens[$next]));
            }

            $operator = $tokens[$next] ?? null;
            if ('=' === self::text($operator) || \in_array(self::id($operator), self::ASSIGNMENTS, true)) {
                $assigned[] = self::text($tokens[$i + 2]);
            }
        }

        return array_values(array_unique($assigned));
    }

    /**
     * `$resolver->setAllowedValues('type', ['a', 'b'])`: the values of a literal list; a call whose values are computed
     * (a constant, `Enum::cases()`, a closure) is left out.
     *
     * @return array<string, list<bool|int|float|string|null>> by option name
     */
    public static function allowedValues(\ReflectionMethod $method): array
    {
        $tokens = self::tokens($method);
        $allowed = [];
        for ($i = 0, $count = \count($tokens); $i < $count; ++$i) {
            if (\T_STRING !== self::id($tokens[$i]) || 'setallowedvalues' !== strtolower(self::text($tokens[$i])) || '(' !== self::text($tokens[$i + 1] ?? null)) {
                continue;
            }

            $position = $i + 2;
            try {
                $name = self::value($tokens, $position);
                if (!\is_string($name) || ',' !== self::text($tokens[$position] ?? null)) {
                    continue;
                }
                ++$position;
                $values = self::value($tokens, $position);
            } catch (\UnexpectedValueException) {
                continue;
            }

            if (\is_array($values) && array_is_list($values) && [] === array_filter($values, static fn (mixed $value): bool => null !== $value && !\is_scalar($value))) {
                /** @var list<bool|int|float|string|null> $values */
                $allowed[$name] = $values;
            }
        }

        return $allowed;
    }

    /**
     * @return list<array{int, string, int}|string>
     */
    private static function tokens(\ReflectionMethod $method): array
    {
        $file = $method->getFileName();
        if (false === $file || !is_readable($file)) {
            return [];
        }

        $lines = file($file);
        if (false === $lines) {
            return [];
        }

        $source = implode('', \array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
        $tokens = [];
        foreach (token_get_all('<?php '.$source) as $token) {
            if (\is_array($token) && \in_array($token[0], [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT], true)) {
                continue;
            }
            $tokens[] = $token;
        }

        return $tokens;
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     */
    private static function value(array $tokens, int &$i): mixed
    {
        $token = $tokens[$i] ?? throw new \UnexpectedValueException('End of source.');
        ++$i;
        $text = self::text($token);

        switch (self::id($token)) {
            case \T_CONSTANT_ENCAPSED_STRING:
                return stripcslashes(substr($text, 1, -1));
            case \T_LNUMBER:
                return (int) $text;
            case \T_DNUMBER:
                return (float) $text;
            case \T_ARRAY:
                if ('(' !== self::text($tokens[$i] ?? null)) {
                    throw new \UnexpectedValueException('Not a list.');
                }
                ++$i;

                return self::items($tokens, $i, ')');
            case \T_STRING:
                return match (strtolower($text)) {
                    'true' => true,
                    'false' => false,
                    'null' => null,
                    default => throw new \UnexpectedValueException('A constant.'),
                };
        }

        if ('[' === $text) {
            return self::items($tokens, $i, ']');
        }

        if ('-' === $text && \in_array(self::id($tokens[$i] ?? null), [\T_LNUMBER, \T_DNUMBER], true)) {
            $number = self::value($tokens, $i);
            \assert(\is_int($number) || \is_float($number));

            return -$number;
        }

        throw new \UnexpectedValueException('Not a literal.');
    }

    /**
     * @param list<array{int, string, int}|string> $tokens
     *
     * @return array<array-key, mixed>
     */
    private static function items(array $tokens, int &$i, string $close): array
    {
        $items = [];
        while ($close !== self::text($tokens[$i] ?? throw new \UnexpectedValueException('Unclosed list.'))) {
            $value = self::value($tokens, $i);
            if (\T_DOUBLE_ARROW === self::id($tokens[$i] ?? null)) {
                ++$i;
                \assert(\is_string($value) || \is_int($value));
                $items[$value] = self::value($tokens, $i);
            } else {
                $items[] = $value;
            }
            if (',' === self::text($tokens[$i] ?? null)) {
                ++$i;
            }
        }
        ++$i;

        return $items;
    }

    /**
     * @param array{int, string, int}|string|null $token
     */
    private static function id(array|string|null $token): ?int
    {
        return \is_array($token) ? $token[0] : null;
    }

    /**
     * @param array{int, string, int}|string|null $token
     */
    private static function text(array|string|null $token): string
    {
        return \is_array($token) ? $token[1] : ($token ?? '');
    }
}

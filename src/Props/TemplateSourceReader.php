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

use Twig\Environment;
use Twig\Lexer;
use Twig\Source;
use Twig\Token;

/**
 * Reads the source of a component template: the defaults of `{% props %}`, the `##` comments above each prop (read from the
 * tokens of Twig, which attaches them to the token that follows: the same path on every TwigComponent version), the
 * older `{# @prop #}`, `{# @description #}` and `{# @block #}` comments, and the blocks the template defines.
 *
 * @internal
 */
final readonly class TemplateSourceReader
{
    public function __construct(private Environment $twig)
    {
    }

    public function read(string $component, Source $source): ComponentDoc
    {
        $props = [];
        $blocks = [];

        $tokens = $this->tokens($source);
        for ($i = 0, $count = \count($tokens); $i < $count; ++$i) {
            if (!$tokens[$i]->test(Token::BLOCK_START_TYPE) || !isset($tokens[$i + 1])) {
                continue;
            }

            if ($tokens[$i + 1]->test(Token::NAME_TYPE, 'props')) {
                $i += 2;
                foreach ($this->propsTag($tokens, $i) as $prop) {
                    $props[$prop->name] = isset($props[$prop->name]) ? $props[$prop->name]->mergedWith($prop) : $prop;
                }
            } elseif ($tokens[$i + 1]->test(Token::NAME_TYPE, 'block') && isset($tokens[$i + 2]) && $tokens[$i + 2]->test(Token::NAME_TYPE)) {
                $name = self::text($tokens[$i + 2]);
                $blocks[$name] ??= new BlockDoc($name);
            }
        }

        $description = '';
        $annotations = $this->annotations($source->getCode());
        foreach ($annotations as [$tag, $text]) {
            if ('description' === $tag && '' === $description) {
                $description = TypeSplitter::oneLine($text);
            } elseif ('block' === $tag) {
                $parts = preg_split('/\s+/', trim($text), 2) ?: [];
                if ('' !== ($parts[0] ?? '')) {
                    $blocks[$parts[0]] = new BlockDoc($parts[0], TypeSplitter::oneLine($parts[1] ?? ''));
                }
            } elseif ('prop' === $tag) {
                $props = $this->withAnnotation($props, $text);
            }
        }

        return new ComponentDoc($component, $description, array_values($props), array_values($blocks));
    }

    /**
     * @return list<Token>
     */
    private function tokens(Source $source): array
    {
        $stream = (new Lexer($this->twig))->tokenize($source);

        $tokens = [];
        while (!$stream->isEOF()) {
            $tokens[] = $stream->getCurrent();
            $stream->next();
        }

        return $tokens;
    }

    /**
     * @param list<Token> $tokens
     * @param int         $i      on the first token after `props`; left on the end of the tag
     *
     * @return list<PropDoc>
     */
    private function propsTag(array $tokens, int &$i): array
    {
        $props = [];
        for ($count = \count($tokens); $i < $count && !$tokens[$i]->test(Token::BLOCK_END_TYPE); ++$i) {
            $token = $tokens[$i];
            if (!$token->test(Token::NAME_TYPE)) {
                continue;
            }

            $documentation = method_exists($token, 'getDocumentation') ? $token->getDocumentation() : null;
            [$type, $description] = null === $documentation ? [PropDoc::UNKNOWN_TYPE, ''] : TypeSplitter::split($documentation);
            $sources = [PropDoc::SOURCE_PROPS_TAG];
            if (null !== $documentation) {
                $sources[] = PropDoc::SOURCE_DOC_COMMENT;
            }

            $hasDefault = false;
            $default = null;
            if (isset($tokens[$i + 1]) && $tokens[$i + 1]->test(Token::OPERATOR_TYPE, '=')) {
                $hasDefault = true;
                $i += 2;
                $expression = [];
                for ($depth = 0; $i < $count && !$tokens[$i]->test(Token::BLOCK_END_TYPE); ++$i) {
                    if (self::is($tokens[$i], ',') && 0 === $depth) {
                        break;
                    }
                    if (self::is($tokens[$i], ['[', '{', '('])) {
                        ++$depth;
                    } elseif (self::is($tokens[$i], [']', '}', ')'])) {
                        --$depth;
                    }
                    $expression[] = $tokens[$i];
                }
                $default = $this->literal($expression);
                if ($i < $count && $tokens[$i]->test(Token::BLOCK_END_TYPE)) {
                    --$i;
                }
            }

            $props[] = PropDoc::create(self::text($token), '' === $type ? PropDoc::UNKNOWN_TYPE : $type, $hasDefault, $default, $description, null, $sources);
        }

        return $props;
    }

    /**
     * The value of a default written as a literal (string, number, true, false, null, list, map); null for any other
     * expression, which the table shows as "has a default".
     *
     * @param list<Token> $tokens
     */
    private function literal(array $tokens): mixed
    {
        $i = 0;
        try {
            $value = $this->literalValue($tokens, $i);
        } catch (\UnexpectedValueException) {
            return null;
        }

        return $i === \count($tokens) ? $value : null;
    }

    /**
     * @param list<Token> $tokens
     */
    private function literalValue(array $tokens, int &$i): mixed
    {
        $token = $tokens[$i] ?? throw new \UnexpectedValueException('End of expression.');
        ++$i;

        if ($token->test(Token::STRING_TYPE) || $token->test(Token::NUMBER_TYPE)) {
            return $token->getValue();
        }

        if ($token->test(Token::OPERATOR_TYPE, '-') && isset($tokens[$i]) && $tokens[$i]->test(Token::NUMBER_TYPE)) {
            $number = $tokens[$i]->getValue();
            ++$i;
            \assert(\is_int($number) || \is_float($number));

            return -$number;
        }

        if ($token->test(Token::NAME_TYPE, ['true', 'false', 'null', 'TRUE', 'FALSE', 'NULL', 'none', 'NONE'])) {
            return match (strtolower(self::text($token))) {
                'true' => true,
                'false' => false,
                default => null,
            };
        }

        if (self::is($token, '[')) {
            $list = [];
            while (!self::is($tokens[$i] ?? throw new \UnexpectedValueException('Unclosed list.'), ']')) {
                $list[] = $this->literalValue($tokens, $i);
                if (isset($tokens[$i]) && self::is($tokens[$i], ',')) {
                    ++$i;
                }
            }
            ++$i;

            return $list;
        }

        if (self::is($token, '{')) {
            $map = [];
            while (!self::is($tokens[$i] ?? throw new \UnexpectedValueException('Unclosed map.'), '}')) {
                $key = $tokens[$i];
                if (!$key->test(Token::NAME_TYPE) && !$key->test(Token::STRING_TYPE) && !$key->test(Token::NUMBER_TYPE)) {
                    throw new \UnexpectedValueException('Computed key.');
                }
                ++$i;
                if (!isset($tokens[$i]) || !self::is($tokens[$i], ':')) {
                    throw new \UnexpectedValueException('Shorthand key.');
                }
                ++$i;
                $keyValue = $key->getValue();
                \assert(\is_string($keyValue) || \is_int($keyValue) || \is_float($keyValue));
                $map[\is_float($keyValue) ? (string) $keyValue : $keyValue] = $this->literalValue($tokens, $i);
                if (isset($tokens[$i]) && self::is($tokens[$i], ',')) {
                    ++$i;
                }
            }
            ++$i;

            return $map;
        }

        throw new \UnexpectedValueException('Not a literal.');
    }

    private static function text(Token $token): string
    {
        $value = $token->getValue();

        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * `[` and `]` are operators on recent Twig and punctuation on older ones.
     *
     * @param string|list<string> $values
     */
    private static function is(Token $token, string|array $values): bool
    {
        return $token->test(Token::PUNCTUATION_TYPE, $values) || $token->test(Token::OPERATOR_TYPE, $values);
    }

    /**
     * The `{# @tag text #}` comments (not the `{## #}` documentation ones), in order.
     *
     * @return list<array{string, string}>
     */
    private function annotations(string $code): array
    {
        preg_match_all('/\{#(?!#)[-~]?\s*@(prop|description|block)\b(.*?)[-~]?#\}/s', $code, $matches, \PREG_SET_ORDER);

        return array_map(static fn (array $match): array => [$match[1], $match[2]], $matches);
    }

    /**
     * @param array<string, PropDoc> $props
     *
     * @return array<string, PropDoc>
     */
    private function withAnnotation(array $props, string $text): array
    {
        if (1 !== preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)(?:\.([A-Za-z_][A-Za-z0-9_]*))?\s+(\S.*)$/s', $text, $match)) {
            return $props;
        }

        [$type, $description] = TypeSplitter::split($match[3]);
        $type = '' === $type ? PropDoc::UNKNOWN_TYPE : $type;
        $documented = PropDoc::create('' !== $match[2] ? $match[2] : $match[1], $type, false, null, $description, null, [PropDoc::SOURCE_ANNOTATION]);

        if ('' !== $match[2]) {
            // A field of the rows of a list: attached to the list, whatever reads it.
            $parent = $props[$match[1]] ?? PropDoc::create($match[1], PropDoc::UNKNOWN_TYPE, false, null, '', null, [PropDoc::SOURCE_ANNOTATION]);
            $props[$match[1]] = $parent->mergedWith(PropDoc::create($match[1], PropDoc::UNKNOWN_TYPE, false, null, '', null, [PropDoc::SOURCE_ANNOTATION], [$documented]));

            return $props;
        }

        // The tag knows the default, the comment the type and the meaning: keep the default of the tag.
        $props[$documented->name] = isset($props[$documented->name]) ? $props[$documented->name]->mergedWith($documented) : $documented;

        return $props;
    }
}

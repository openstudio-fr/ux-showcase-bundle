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

namespace OpenStudio\UxShowcaseBundle\Twig;

use Twig\Error\SyntaxError;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\Unary\NegUnary;
use Twig\Node\Expression\Unary\PosUnary;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;
use Twig\TokenStream;

/**
 * {% showcase_example 'slug' [titled 'Title'] [with { prop: 'value' }] [options { background: 'dark' }] %}…{% end_showcase_example %}
 * {% showcase_free 'slug' [titled 'Title'] [options { wrapper: false }] %}…{% end_showcase_free %}.
 *
 * The parts after the slug come in any order, each at most once. Props and options are literals: strings, numbers,
 * true, false, null, lists and hashes of them.
 *
 * @internal
 */
final class ExampleTokenParser extends AbstractTokenParser
{
    private const OPTIONS = ['background' => 'string', 'body_class' => 'string', 'wrapper' => 'boolean', 'height' => 'integer', 'needs_data' => 'boolean'];

    public function __construct(private readonly bool $free)
    {
    }

    public function getTag(): string
    {
        return $this->free ? 'showcase_free' : 'showcase_example';
    }

    public function parse(Token $token): Node
    {
        $stream = $this->parser->getStream();
        $line = $token->getLine();

        $slugExpression = $this->parser->parseExpression();
        $slug = $slugExpression instanceof ConstantExpression ? $slugExpression->getAttribute('value') : null;
        if (!\is_string($slug)) {
            throw new SyntaxError(\sprintf('The slug of "%s" must be a string literal.', $this->getTag()), $line, $stream->getSourceContext());
        }

        $title = null;
        $props = [];
        $options = [];
        $seen = [];
        while (!$stream->test(Token::BLOCK_END_TYPE)) {
            $keyword = $stream->expect(Token::NAME_TYPE)->getValue();
            \assert(\is_string($keyword));
            $allowed = $this->free ? ['titled', 'options'] : ['titled', 'with', 'options'];
            if (!\in_array($keyword, $allowed, true) || isset($seen[$keyword])) {
                throw new SyntaxError(\sprintf('Unexpected "%s" in "%s": expected %s, each once.', $keyword, $this->getTag(), implode(', ', $allowed)), $stream->getCurrent()->getLine(), $stream->getSourceContext());
            }
            $seen[$keyword] = true;

            $value = $this->literal($this->parser->parseExpression());
            switch ($keyword) {
                case 'titled':
                    if (!\is_string($value)) {
                        throw new SyntaxError('"titled" expects a string literal.', $line, $stream->getSourceContext());
                    }
                    $title = $value;
                    break;
                case 'with':
                    if (!\is_array($value) || array_is_list($value) && [] !== $value) {
                        throw new SyntaxError('"with" expects a hash of props, such as { label: \'Save\' }.', $line, $stream->getSourceContext());
                    }
                    /** @var array<string, mixed> $value */
                    $props = $value;
                    break;
                default:
                    $options = $this->options($value, $line);
            }
        }

        $stream->expect(Token::BLOCK_END_TYPE);
        $this->skipBody($stream);

        return new ExampleNode($this->free, $slug, $title, $props, $options, $line);
    }

    /**
     * The body is markup for the component, rendered later by the application: it is not parsed here, so the blocks it
     * holds (`<twig:block>` becomes `{% block %}`) never meet the ones of another example of the same file.
     */
    private function skipBody(TokenStream $stream): void
    {
        $end = 'end_'.$this->getTag();
        $line = $stream->getCurrent()->getLine();
        while (!$stream->isEOF()) {
            if ($stream->test(Token::BLOCK_START_TYPE) && $stream->look()->test(Token::NAME_TYPE, $end)) {
                $stream->next();
                $stream->next();
                $stream->expect(Token::BLOCK_END_TYPE);

                return;
            }
            $stream->next();
        }

        throw new SyntaxError(\sprintf('Unexpected end of file: "%s" needs its "%s" tag.', $this->getTag(), $end), $line, $stream->getSourceContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function options(mixed $value, int $line): array
    {
        $context = $this->parser->getStream()->getSourceContext();
        if (!\is_array($value)) {
            throw new SyntaxError('"options" expects a hash, such as { background: \'dark\' }.', $line, $context);
        }

        foreach ($value as $name => $option) {
            $type = self::OPTIONS[$name] ?? null;
            if (null === $type) {
                throw new SyntaxError(\sprintf('Unknown option "%s": expected %s.', $name, implode(', ', array_keys(self::OPTIONS))), $line, $context);
            }
            if (get_debug_type($option) !== ['string' => 'string', 'boolean' => 'bool', 'integer' => 'int'][$type]) {
                throw new SyntaxError(\sprintf('The option "%s" expects a %s.', $name, $type), $line, $context);
            }
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    private function literal(AbstractExpression $expression): mixed
    {
        $context = $this->parser->getStream()->getSourceContext();

        if ($expression instanceof ConstantExpression) {
            return $expression->getAttribute('value');
        }

        if ($expression instanceof NegUnary || $expression instanceof PosUnary) {
            $operandNode = $expression->getNode('node');
            \assert($operandNode instanceof AbstractExpression);
            $operand = $this->literal($operandNode);
            if (!\is_int($operand) && !\is_float($operand)) {
                throw new SyntaxError('A sign applies to a number only.', $expression->getTemplateLine(), $context);
            }

            return $expression instanceof NegUnary ? -$operand : $operand;
        }

        if ($expression instanceof ArrayExpression) {
            $data = [];
            foreach ($expression->getKeyValuePairs() as $pair) {
                /** @var array{key: AbstractExpression, value: AbstractExpression} $pair */
                $value = $this->literal($pair['value']);
                $key = $this->literal($pair['key']);
                if (!\is_string($key) && !\is_int($key)) {
                    throw new SyntaxError('A hash key is a string or a number.', $expression->getTemplateLine(), $context);
                }
                $data[$key] = $value;
            }

            return $data;
        }

        throw new SyntaxError('Props and options are literals (strings, numbers, true, false, null, lists, hashes): the form and the generated code are built from them.', $expression->getTemplateLine(), $context);
    }
}

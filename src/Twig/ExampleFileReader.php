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

use OpenStudio\UxShowcaseBundle\Example\Example;
use OpenStudio\UxShowcaseBundle\Example\PreviewOptions;
use OpenStudio\UxShowcaseBundle\Exception\ShowcaseException;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Loader\ArrayLoader;
use Twig\Node\Node;
use Twig\Source;

/**
 * Reads the examples of one Twig example file.
 *
 * The tags are parsed by Twig, on a private environment, with an empty body: what a body holds (components, filters of
 * the application) is not known to that environment, and is rendered later by the application one. The raw body is cut
 * from the source.
 *
 * @internal
 */
final class ExampleFileReader
{
    private const TAG_PATTERN = '/\{%-?\s*(?<tag>showcase_example|showcase_free)\b(?<header>.*?)-?%\}(?<body>.*?)\{%-?\s*end_\k<tag>\s*-?%\}/s';

    private readonly Environment $twig;

    public function __construct()
    {
        $this->twig = new Environment(new ArrayLoader(), ['cache' => false]);
        $this->twig->addExtension(new ShowcaseExampleExtension());
    }

    /**
     * @return list<Example> a component example needs $component; a documentation page needs $group instead
     */
    public function read(string $source, string $name, ?string $component, ?string $group = null): array
    {
        preg_match_all(self::TAG_PATTERN, $source, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);

        $examples = [];
        foreach ($matches as $match) {
            $line = substr_count($source, "\n", 0, $match[0][1]) + 1;
            $tag = $match['tag'][0];
            if (null === $component && 'showcase_free' !== $tag) {
                throw new ShowcaseException(\sprintf('%s line %d: a documentation page holds showcase_free tags only.', $name, $line));
            }

            // The header goes through Twig alone, on its own line number, so an error points into the real file.
            $header = str_repeat("\n", $line - 1).'{% '.$tag.$match['header'][0].' %}{% end_'.$tag.' %}';
            try {
                $node = $this->find($this->twig->parse($this->twig->tokenize(new Source($header, $name))));
            } catch (Error $error) {
                throw new ShowcaseException($error->getMessage(), 0, $error);
            }

            if (null === $node) {
                throw new ShowcaseException(\sprintf('%s line %d: the %s tag produced no example.', $name, $line, $tag));
            }

            $slug = $node->getAttribute('slug');
            $title = $node->getAttribute('title');
            \assert(\is_string($slug) && (null === $title || \is_string($title)));

            $body = self::dedent($match['body'][0]);
            $free = 'showcase_free' === $tag;
            if ($free && '' === $body) {
                throw new ShowcaseException(\sprintf('%s line %d: the free example "%s" has no body.', $name, $line, \is_string($node->getAttribute('slug')) ? $node->getAttribute('slug') : ''));
            }

            $options = $node->getAttribute('options');
            \assert(\is_array($options));
            $preview = new PreviewOptions(
                \is_string($options['background'] ?? null) ? $options['background'] : null,
                \is_string($options['body_class'] ?? null) ? $options['body_class'] : null,
                \is_bool($options['wrapper'] ?? null) ? $options['wrapper'] : null,
                \is_int($options['height'] ?? null) ? $options['height'] : null,
            );
            $props = $node->getAttribute('props');
            \assert(\is_array($props));
            /** @var array<string, mixed> $props */
            try {
                $examples[] = new Example(
                    component: $component,
                    slug: $slug,
                    title: $title,
                    props: $props,
                    content: $free || '' === $body ? null : $body,
                    source: $free ? $body : null,
                    group: $group,
                    preview: $preview,
                );
            } catch (\InvalidArgumentException $exception) {
                throw new ShowcaseException(\sprintf('%s line %d: %s', $name, $line, $exception->getMessage()), 0, $exception);
            }
        }

        if (1 === preg_match('/\{%-?\s*(end_)?showcase_/', (string) preg_replace(self::TAG_PATTERN, '', $source))) {
            throw new ShowcaseException(\sprintf('%s holds a showcase tag that is not a complete showcase_example / showcase_free ... end_ pair.', $name));
        }

        return $examples;
    }

    private function find(Node $node): ?ExampleNode
    {
        if ($node instanceof ExampleNode) {
            return $node;
        }
        foreach ($node as $child) {
            if (null !== $found = $this->find($child)) {
                return $found;
            }
        }

        return null;
    }

    private static function dedent(string $body): string
    {
        $lines = explode("\n", str_replace("\r\n", "\n", trim($body, "\r\n")));
        $indent = null;
        foreach ($lines as $line) {
            if ('' === trim($line)) {
                continue;
            }
            $width = \strlen($line) - \strlen(ltrim($line, " \t"));
            $indent = null === $indent ? $width : min($indent, $width);
        }

        return rtrim(implode("\n", array_map(static fn (string $line): string => substr($line, $indent ?? 0), $lines)));
    }
}

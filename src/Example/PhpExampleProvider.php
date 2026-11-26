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

use Psr\Container\ContainerInterface;

/**
 * @internal
 */
final readonly class PhpExampleProvider implements ExampleProviderInterface
{
    /**
     * @param list<array{service: string, method: string, component: string, slug: string, title: string|null, content: string|null, background: string|null, body_class: string|null, wrapper: bool|null, height: int|null, needs_data: bool|null}> $definitions
     */
    public function __construct(
        private ContainerInterface $examples,
        private array $definitions = [],
    ) {
    }

    public function getExamples(): iterable
    {
        foreach ($this->definitions as $definition) {
            $source = $this->examples->get($definition['service']);
            $props = \is_object($source) ? $source->{$definition['method']}() : null;
            if (!\is_array($props)) {
                throw new \UnexpectedValueException(\sprintf('The example "%s" of the component "%s" must return its props as an array.', $definition['slug'], $definition['component']));
            }

            /** @var array<string, mixed> $props */
            try {
                yield new Example(
                    component: $definition['component'],
                    slug: $definition['slug'],
                    title: $definition['title'],
                    props: $props,
                    content: $definition['content'],
                    preview: new PreviewOptions($definition['background'], $definition['body_class'], $definition['wrapper'], $definition['height'], $definition['needs_data']),
                );
            } catch (\InvalidArgumentException $exception) {
                throw new \InvalidArgumentException(\sprintf('The example "%s" of the component "%s": %s', $definition['slug'], $definition['component'], $exception->getMessage()), 0, $exception);
            }
        }
    }
}

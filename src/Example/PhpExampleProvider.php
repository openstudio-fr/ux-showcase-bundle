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
     * @param list<array{service: string, method: string, component: string, slug: string, title: string|null, content: string|null}> $definitions
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
            yield new Example($definition['component'], $definition['slug'], $definition['title'], $props, $definition['content']);
        }
    }
}

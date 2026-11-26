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
 * What is known about a component: what each reader of the chain found, or the merge of all of them.
 *
 * @api
 */
final readonly class ComponentDoc
{
    /**
     * @param list<PropDoc>  $props
     * @param list<BlockDoc> $blocks
     */
    public function __construct(
        public string $name,
        public string $description = '',
        public array $props = [],
        public array $blocks = [],
    ) {
    }

    public function prop(string $name): ?PropDoc
    {
        foreach ($this->props as $prop) {
            if ($prop->name === $name) {
                return $prop;
            }
        }

        return null;
    }

    /**
     * Props and blocks keep the order of the first reader that knows them; see PropDoc::mergedWith() for the rule.
     */
    public function mergedWith(self $later): self
    {
        $props = [];
        foreach ([...$this->props, ...$later->props] as $prop) {
            $props[$prop->name] = isset($props[$prop->name]) ? $props[$prop->name]->mergedWith($prop) : $prop;
        }

        $blocks = [];
        foreach ([...$this->blocks, ...$later->blocks] as $block) {
            if (!isset($blocks[$block->name]) || '' === $blocks[$block->name]->description) {
                $blocks[$block->name] = $block;
            }
        }

        return new self($this->name, '' !== $this->description ? $this->description : $later->description, array_values($props), array_values($blocks));
    }
}

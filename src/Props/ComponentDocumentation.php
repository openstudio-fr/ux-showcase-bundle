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

use OpenStudio\UxShowcaseBundle\Exception\UnknownComponentException;
use OpenStudio\UxShowcaseBundle\Library\ComponentCatalog;

/**
 * The documentation of a component: what every reader of the chain knows, merged. See PropDoc::mergedWith() for the
 * merge rule.
 *
 * @api
 */
final class ComponentDocumentation
{
    /** @var array<string, ComponentDoc> */
    private array $cache = [];

    /**
     * @param iterable<PropsReaderInterface> $readers highest priority first
     */
    public function __construct(
        private readonly ComponentCatalog $catalog,
        private readonly iterable $readers,
    ) {
    }

    /**
     * @throws UnknownComponentException when TwigComponent does not know the name
     */
    public function get(string $component): ComponentDoc
    {
        if (isset($this->cache[$component])) {
            return $this->cache[$component];
        }

        if (!$this->catalog->exists($component)) {
            throw UnknownComponentException::forComponent($component);
        }

        $doc = new ComponentDoc($component);
        foreach ($this->readers as $reader) {
            $doc = $doc->mergedWith($reader->read($component));
        }

        return $this->cache[$component] = $doc;
    }
}

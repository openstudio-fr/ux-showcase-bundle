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

namespace OpenStudio\UxShowcaseBundle\Screen;

use OpenStudio\UxShowcaseBundle\Props\PropDoc;

/**
 * The field types, highest priority first: the first that supports a prop draws it.
 *
 * @internal
 */
final class TryFieldRegistry
{
    /** @var list<TryFieldTypeInterface>|null */
    private ?array $types = null;

    /**
     * @param iterable<TryFieldTypeInterface> $source
     */
    public function __construct(private readonly iterable $source)
    {
    }

    public function resolve(PropDoc $prop): ?TryFieldTypeInterface
    {
        foreach ($this->all() as $type) {
            if ($type->supports($prop)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @return list<TryFieldTypeInterface>
     */
    public function all(): array
    {
        if (null !== $this->types) {
            return $this->types;
        }

        $types = [];
        $names = [];
        foreach ($this->source as $type) {
            if (isset($names[$type->getName()])) {
                throw new \LogicException(\sprintf('Two try field types are named "%s" (%s and %s): the name is the identifier in the markup.', $type->getName(), $names[$type->getName()], $type::class));
            }
            $names[$type->getName()] = $type::class;
            $types[] = $type;
        }

        return $this->types = $types;
    }
}

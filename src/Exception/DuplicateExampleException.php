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

namespace OpenStudio\UxShowcaseBundle\Exception;

/**
 * @internal
 */
final class DuplicateExampleException extends ShowcaseException
{
    public static function create(string $library, ?string $component, string $slug): self
    {
        return new self(null === $component
            ? \sprintf('The documentation page "%s" of the library "%s" is declared twice: slugs are unique within a library.', $slug, $library)
            : \sprintf('The example "%s" of the component "%s" is declared twice in the library "%s": slugs are unique within a component.', $slug, $component, $library));
    }
}

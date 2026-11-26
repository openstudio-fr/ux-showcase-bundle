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
final class UnknownComponentException extends ShowcaseException
{
    public static function forComponent(string $component): self
    {
        return new self(\sprintf('The component "%s" is not a TwigComponent of this application.', $component));
    }

    public static function forExample(string $slug, string $component, ?string $library): self
    {
        return new self(null === $library
            ? \sprintf('The example "%s" targets the component "%s", which no library lists.', $slug, $component)
            : \sprintf('The example "%s" targets the component "%s", which the library "%s" does not list.', $slug, $component, $library));
    }
}

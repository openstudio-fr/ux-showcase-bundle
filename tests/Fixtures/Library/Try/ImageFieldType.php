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

namespace OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Try;

use OpenStudio\UxShowcaseBundle\Props\PropDoc;
use OpenStudio\UxShowcaseBundle\Screen\TryFieldTypeInterface;

// What a host brings: a picker for the props named "picture", with a module of its own.
final class ImageFieldType implements TryFieldTypeInterface
{
    public function getName(): string
    {
        return 'image';
    }

    public function supports(PropDoc $prop): bool
    {
        return 'picture' === $prop->name;
    }

    public function getTemplate(): string
    {
        return 'showcase/try-image.html.twig';
    }

    public function getModuleUrl(): string
    {
        return '/assets/image-field.js';
    }
}

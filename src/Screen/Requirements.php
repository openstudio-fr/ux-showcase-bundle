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

/**
 * Routing requirements. A component name keeps its `:` (legal in a path); neither a library nor a component starts with
 * an underscore, which keeps `_docs` and `_assets` for the bundle.
 *
 * @internal
 */
final class Requirements
{
    public const LIBRARY = '[a-z0-9]+(?:-[a-z0-9]+)*';
    public const COMPONENT = '[A-Za-z0-9][A-Za-z0-9_-]*(?::[A-Za-z0-9_-]+)*';
    public const SLUG = '[a-z0-9]+(?:-[a-z0-9]+)*';
}

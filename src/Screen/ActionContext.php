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

use OpenStudio\UxShowcaseBundle\Example\Example;
use OpenStudio\UxShowcaseBundle\Library\ComponentReference;

/**
 * Where an action is asked for: a component of a library, and one of its examples, or none for the whole component.
 * A documentation page has no component.
 *
 * @api
 */
final readonly class ActionContext
{
    public function __construct(
        public string $library,
        public ?ComponentReference $component,
        public ?Example $example = null,
    ) {
    }
}

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

namespace OpenStudio\UxShowcaseBundle\Library;

use OpenStudio\UxShowcaseBundle\Example\Example;

/**
 * @api
 */
final readonly class ComponentGroup
{
    /**
     * @param list<ComponentReference> $components visible components only
     * @param list<Example>            $docPages
     */
    public function __construct(
        public string $name,
        public array $components,
        public array $docPages,
    ) {
    }
}

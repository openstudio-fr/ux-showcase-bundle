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

namespace OpenStudio\UxShowcaseBundle\Preview;

use OpenStudio\UxShowcaseBundle\Example\Example;
use OpenStudio\UxShowcaseBundle\Props\ComponentDoc;
use Symfony\Component\HttpFoundation\Request;

/**
 * What a PostedValuesNormalizerInterface knows about the preview being asked.
 *
 * @api
 */
final readonly class PostedValuesContext
{
    public function __construct(
        public string $library,
        public Example $example,
        public ComponentDoc $doc,
        public Request $request,
        public bool $fromForm,
    ) {
    }
}

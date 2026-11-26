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

/**
 * Gives the address the values a visitor tries on an example are posted to. The package implements it with a signed
 * address (SignedPreviewUrlGenerator) and a host replaces it by aliasing this interface to its own service, which then
 * owns the route it points to.
 *
 * @api
 */
interface PreviewUrlGeneratorInterface
{
    /**
     * @param string $library the name of the library of the example
     * @param string $locale  the locale the example is drawn in
     *
     * @return string a path with its query, valid for a limited time
     */
    public function generate(string $library, Example $example, string $locale): string;
}

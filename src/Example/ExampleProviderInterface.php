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

namespace OpenStudio\UxShowcaseBundle\Example;

/**
 * Autoconfigured (tag `ux_showcase.example_provider`). A provider returned by a library
 * (ComponentLibraryInterface::getExampleProviders()) feeds that library; a provider registered as a service feeds every
 * library that lists the component of an example.
 *
 * @api
 */
interface ExampleProviderInterface
{
    /**
     * @return iterable<Example>
     */
    public function getExamples(): iterable;
}

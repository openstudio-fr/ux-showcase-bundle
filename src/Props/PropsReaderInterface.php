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

namespace OpenStudio\UxShowcaseBundle\Props;

/**
 * One reader of the chain: it adds what it knows of a component, and nothing else.
 *
 * Autoconfigured (tag `ux_showcase.props_reader`); the priority of the tag orders the chain, the highest first. A
 * reader returns a partial ComponentDoc, empty when it knows nothing; ComponentDocumentation merges the answers.
 *
 * @api
 */
interface PropsReaderInterface
{
    public function read(string $component): ComponentDoc;
}

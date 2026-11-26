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

namespace OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library;

use OpenStudio\UxShowcaseBundle\Props\ComponentDoc;
use OpenStudio\UxShowcaseBundle\Props\PropDoc;
use OpenStudio\UxShowcaseBundle\Props\PropsReaderInterface;

// A reader written by an application, found by autoconfiguration alone (priority 0, after the built-in ones).
final class ChainReader implements PropsReaderInterface
{
    public function read(string $component): ComponentDoc
    {
        if ('Demo:Form:Input' !== $component) {
            return new ComponentDoc($component);
        }

        return new ComponentDoc($component, 'A text field.', [PropDoc::create('name', 'string', false, null, 'Name of the field', null, ['custom']), PropDoc::create('extra', 'int', true, 1, '', null, ['custom'])]);
    }
}

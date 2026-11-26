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

/**
 * @api
 */
enum ComponentStatus: string
{
    case Ready = 'ready';
    case Waiting = 'waiting';
    /** A hidden component is not listed; its examples are not loaded for display. */
    case Hidden = 'hidden';
}

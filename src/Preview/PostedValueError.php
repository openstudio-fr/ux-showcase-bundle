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

/**
 * One reason a posted value is refused: the path of the prop (`vehicle.price`, `vehicles[0].name`) and what is wrong.
 *
 * @api
 */
final readonly class PostedValueError
{
    public function __construct(public string $path, public string $message)
    {
    }
}

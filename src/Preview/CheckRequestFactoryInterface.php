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

use Symfony\Component\HttpFoundation\Request;

/**
 * Builds the requests `ux-showcase:check` renders examples with: once the main request on `/`,
 * then one request per preview path. An application whose pages need its own request or session
 * class implements it; without one, the command uses a plain request with an in-memory session.
 *
 * @api
 */
interface CheckRequestFactoryInterface
{
    public function createRequest(string $uri): Request;
}

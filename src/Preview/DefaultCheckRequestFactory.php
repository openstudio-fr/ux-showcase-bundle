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
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * A plain request with an in-memory session: what the check uses when the application provides no factory.
 *
 * @internal
 */
final class DefaultCheckRequestFactory implements CheckRequestFactoryInterface
{
    public function createRequest(string $uri): Request
    {
        $request = Request::create($uri);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}

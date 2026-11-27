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

namespace OpenStudio\UxShowcaseBundle\Tests\Fixtures\Preview;

use OpenStudio\UxShowcaseBundle\Preview\CheckRequestFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class HostRequestFactory implements CheckRequestFactoryInterface
{
    /** @var list<Request> */
    public static array $created = [];

    public static function reset(): void
    {
        self::$created = [];
    }

    public function createRequest(string $uri): Request
    {
        $request = HostRequest::create($uri);
        $request->setSession(new HostSession(new MockArraySessionStorage()));

        return self::$created[] = $request;
    }
}

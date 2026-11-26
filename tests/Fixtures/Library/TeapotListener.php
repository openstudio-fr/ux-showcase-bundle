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

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Makes the preview of the example "teapot" answer with another status than 200.
 */
#[AsEventListener(event: 'kernel.response')]
final class TeapotListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if ('teapot' === $event->getRequest()->attributes->get('slug')) {
            $event->getResponse()->setStatusCode(418);
        }
    }
}

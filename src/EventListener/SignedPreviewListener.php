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

namespace OpenStudio\UxShowcaseBundle\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Scoped to the routes of the signed preview: whatever the answer is (the page, the reasons it cannot be drawn, a 403 or
 * a 404), the address and the page hold values a visitor tried and a token, so nothing keeps them, indexes them or
 * passes them on in a Referer.
 *
 * @internal
 */
final readonly class SignedPreviewListener
{
    public const ROUTE_ATTRIBUTE = '_ux_showcase_signed';

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || true !== $event->getRequest()->attributes->get(self::ROUTE_ATTRIBUTE)) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Robots-Tag', 'noindex, nofollow');
        $headers->set('Cache-Control', 'no-store');
        $headers->set('Referrer-Policy', 'no-referrer');
    }
}

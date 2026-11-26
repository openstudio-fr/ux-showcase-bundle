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

use OpenStudio\UxShowcaseBundle\Access\ShowcaseAccessInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * The one place where access is decided, for every route flagged by the routes file of the bundle.
 * A route added later cannot forget the check, and a refusal is a 404 so the showcase does not advertise itself.
 */
final readonly class ShowcaseAccessListener
{
    public const string ROUTE_ATTRIBUTE = '_ux_showcase';

    /**
     * Set by ux-showcase:check on the sub-requests it makes, to render every example without a logged-in user. It is
     * honoured on a sub-request only: a request that comes from the network is always the main one, so no query,
     * header or cookie can lift the check.
     */
    public const string CHECK_ATTRIBUTE = '_ux_showcase_check';

    public function __construct(private ShowcaseAccessInterface $access)
    {
    }

    public function onController(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (true !== $request->attributes->get(self::ROUTE_ATTRIBUTE)) {
            return;
        }

        if (HttpKernelInterface::SUB_REQUEST === $event->getRequestType() && true === $request->attributes->get(self::CHECK_ATTRIBUTE)) {
            return;
        }

        if (!$this->access->isGranted($request)) {
            throw new NotFoundHttpException();
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (true === $event->getRequest()->attributes->get(self::ROUTE_ATTRIBUTE)) {
            $event->getResponse()->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
    }
}

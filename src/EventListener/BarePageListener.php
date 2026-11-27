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

use OpenStudio\UxShowcaseBundle\Screen\Assets;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Profiler\Profiler;

/**
 * Scoped to the bare page of a preview: switches the profiler off (no toolbar inside the frame) and adds the script that
 * reports the height of the document to the page around the frame. The template of the host does not have to know it.
 *
 * The frame is as tall as its document, so it has no use for a scrollbar: a theme that forces one (`html { overflow-y:
 * scroll }`, `scrollbar-gutter: stable`) would leave an empty strip the width of a scrollbar at the right of the page,
 * which the background of the example does not cover. The style added here gives the gutter back.
 *
 * @internal
 */
final readonly class BarePageListener
{
    public const ROUTE_ATTRIBUTE = '_ux_showcase_bare';

    public function __construct(private Assets $assets, private ?Profiler $profiler = null)
    {
    }

    public function onRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest() && true === $event->getRequest()->attributes->get(self::ROUTE_ATTRIBUTE)) {
            $this->profiler?->disable();
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || true !== $event->getRequest()->attributes->get(self::ROUTE_ATTRIBUTE)) {
            return;
        }

        $this->profiler?->disable();

        $response = $event->getResponse();
        $content = $response->getContent();
        if (!$response->isSuccessful() || false === $content || !str_contains((string) $response->headers->get('Content-Type'), 'html')) {
            return;
        }

        $style = '<style data-ux-showcase>html { overflow-y: auto !important; scrollbar-gutter: auto !important; }</style>';
        $head = stripos($content, '</head>');
        if (false !== $head) {
            $content = substr($content, 0, $head).$style."\n".substr($content, $head);
        }

        $script = \sprintf('<script src="%s" defer></script>', htmlspecialchars($this->assets->url('bare.js'), \ENT_QUOTES));
        $position = strripos($content, '</body>');
        $response->setContent(false === $position ? $content.$script : substr($content, 0, $position).$script."\n".substr($content, $position));
    }
}

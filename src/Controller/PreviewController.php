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

namespace OpenStudio\UxShowcaseBundle\Controller;

use OpenStudio\UxShowcaseBundle\EventListener\BarePageListener;
use OpenStudio\UxShowcaseBundle\Example\Example;
use OpenStudio\UxShowcaseBundle\Library\ComponentLibraryInterface;
use OpenStudio\UxShowcaseBundle\Preview\BarePage;
use OpenStudio\UxShowcaseBundle\Screen\Requirements;
use OpenStudio\UxShowcaseBundle\ShowcaseRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The bare page one example is shown in, loaded by the frame of the component page: the template of the library with the
 * example in it, and nothing else. BarePageListener adds the height script and turns the profiler off.
 */
final readonly class PreviewController
{
    public const DEFAULT_TEMPLATE = BarePage::DEFAULT_TEMPLATE;
    public const REQUIRED_BLOCKS = BarePage::REQUIRED_BLOCKS;

    public function __construct(
        private ShowcaseRegistry $registry,
        private BarePage $page,
    ) {
    }

    #[Route(
        '/{library}/{component}/{slug}/preview',
        name: 'ux_showcase_preview',
        requirements: ['library' => Requirements::LIBRARY, 'component' => Requirements::COMPONENT, 'slug' => Requirements::SLUG],
        defaults: [BarePageListener::ROUTE_ATTRIBUTE => true],
        methods: ['GET'],
    )]
    public function component(Request $request, string $library, string $component, string $slug): Response
    {
        $showcaseLibrary = $this->registry->library($library) ?? throw new NotFoundHttpException(\sprintf('Unknown library "%s".', $library));
        $example = $this->registry->findExample($library, $component, $slug) ?? throw new NotFoundHttpException(\sprintf('Unknown example "%s" of "%s".', $slug, $component));

        return $this->render($request, $showcaseLibrary, $example);
    }

    #[Route(
        '/{library}/_docs/{slug}/preview',
        name: 'ux_showcase_doc_preview',
        requirements: ['library' => Requirements::LIBRARY, 'slug' => Requirements::SLUG],
        defaults: [BarePageListener::ROUTE_ATTRIBUTE => true],
        methods: ['GET'],
    )]
    public function docPage(Request $request, string $library, string $slug): Response
    {
        $showcaseLibrary = $this->registry->library($library) ?? throw new NotFoundHttpException(\sprintf('Unknown library "%s".', $library));
        $example = $this->registry->findExample($library, null, $slug) ?? throw new NotFoundHttpException(\sprintf('Unknown documentation page "%s".', $slug));

        return $this->render($request, $showcaseLibrary, $example);
    }

    private function render(Request $request, ComponentLibraryInterface $library, Example $example): Response
    {
        return $this->page->render($request, $library, $example);
    }
}

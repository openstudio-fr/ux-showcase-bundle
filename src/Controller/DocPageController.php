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

use OpenStudio\UxShowcaseBundle\Screen\ActionContext;
use OpenStudio\UxShowcaseBundle\Screen\ActionResolver;
use OpenStudio\UxShowcaseBundle\Screen\ExampleViewFactory;
use OpenStudio\UxShowcaseBundle\Screen\Requirements;
use OpenStudio\UxShowcaseBundle\Screen\ScreenState;
use OpenStudio\UxShowcaseBundle\ShowcaseConfiguration;
use OpenStudio\UxShowcaseBundle\ShowcaseRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * A documentation page: a free example tied to a group, no component.
 */
final readonly class DocPageController
{
    public function __construct(
        private Environment $twig,
        private ShowcaseConfiguration $configuration,
        private ShowcaseRegistry $registry,
        private ExampleViewFactory $views,
        private ActionResolver $actions,
    ) {
    }

    #[Route(
        '/{library}/_docs/{slug}',
        name: 'ux_showcase_doc',
        requirements: ['library' => Requirements::LIBRARY, 'slug' => Requirements::SLUG],
        methods: ['GET'],
    )]
    public function __invoke(Request $request, string $library, string $slug): Response
    {
        $showcaseLibrary = $this->registry->library($library) ?? throw new NotFoundHttpException(\sprintf('Unknown library "%s".', $library));
        $example = $this->registry->findExample($library, null, $slug) ?? throw new NotFoundHttpException(\sprintf('Unknown documentation page "%s".', $slug));

        $state = ScreenState::fromRequest($request, $this->configuration);

        return new Response($this->twig->render('@OpenStudioUxShowcase/component.html.twig', [
            'configuration' => $this->configuration,
            'library' => $showcaseLibrary,
            'component' => null,
            'documentation' => null,
            'views' => [$this->views->create($showcaseLibrary, null, $example, $state)],
            'state' => $state,
            'page' => $example,
            'self_route' => 'ux_showcase_doc',
            'self_parameters' => ['library' => $library, 'slug' => $slug],
            'actions' => $this->actions->resolve(new ActionContext($library, null)),
        ]));
    }
}

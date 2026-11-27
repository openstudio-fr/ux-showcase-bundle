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

use OpenStudio\UxShowcaseBundle\Exception\UnknownComponentException;
use OpenStudio\UxShowcaseBundle\Props\ComponentDoc;
use OpenStudio\UxShowcaseBundle\Props\ComponentDocumentation;
use OpenStudio\UxShowcaseBundle\Screen\ActionContext;
use OpenStudio\UxShowcaseBundle\Screen\ActionResolver;
use OpenStudio\UxShowcaseBundle\Screen\ExampleViewFactory;
use OpenStudio\UxShowcaseBundle\Screen\LibraryListing;
use OpenStudio\UxShowcaseBundle\Screen\Requirements;
use OpenStudio\UxShowcaseBundle\Screen\ScreenState;
use OpenStudio\UxShowcaseBundle\ShowcaseConfiguration;
use OpenStudio\UxShowcaseBundle\ShowcaseRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final readonly class ComponentController
{
    public function __construct(
        private Environment $twig,
        private ShowcaseConfiguration $configuration,
        private ShowcaseRegistry $registry,
        private ComponentDocumentation $documentation,
        private ExampleViewFactory $views,
        private ActionResolver $actions,
        private LibraryListing $listing,
    ) {
    }

    #[Route(
        '/{library}/{component}',
        name: 'ux_showcase_component',
        requirements: ['library' => Requirements::LIBRARY, 'component' => Requirements::COMPONENT],
        methods: ['GET'],
    )]
    public function __invoke(Request $request, string $library, string $component): Response
    {
        $showcaseLibrary = $this->registry->library($library) ?? throw new NotFoundHttpException(\sprintf('Unknown library "%s".', $library));
        $reference = $this->registry->component($library, $component) ?? throw new NotFoundHttpException(\sprintf('Unknown component "%s".', $component));

        $state = ScreenState::fromRequest($request, $this->configuration);
        $examples = $this->registry->examples($library, $component);

        $documentation = null;
        foreach ($examples as $example) {
            if (!$example->isFree()) {
                $documentation = $this->documentation($component);
                break;
            }
        }

        $views = [];
        foreach ($examples as $example) {
            $views[] = $this->views->create($showcaseLibrary, $reference, $example, $state, $documentation, $request->getLocale());
        }

        return new Response($this->twig->render('@OpenStudioUxShowcase/component.html.twig', [
            'configuration' => $this->configuration,
            'libraries' => $this->listing->libraries(),
            'library' => $showcaseLibrary,
            'component' => $reference,
            'documentation' => $documentation,
            'views' => $views,
            'state' => $state,
            'self_route' => 'ux_showcase_component',
            'self_parameters' => ['library' => $library, 'component' => $component],
            'actions' => $this->actions->resolve(new ActionContext($library, $reference)),
        ]));
    }

    // A library may list a component that TwigComponent does not know: the table is empty, the page still works.
    private function documentation(string $component): ComponentDoc
    {
        try {
            return $this->documentation->get($component);
        } catch (UnknownComponentException) {
            return new ComponentDoc($component);
        }
    }
}

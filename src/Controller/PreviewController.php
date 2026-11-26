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
use OpenStudio\UxShowcaseBundle\Example\ExampleRenderer;
use OpenStudio\UxShowcaseBundle\Example\PreviewOptions;
use OpenStudio\UxShowcaseBundle\Example\PreviewOptionsResolver;
use OpenStudio\UxShowcaseBundle\Exception\ShowcaseException;
use OpenStudio\UxShowcaseBundle\Library\ComponentLibraryInterface;
use OpenStudio\UxShowcaseBundle\Screen\Requirements;
use OpenStudio\UxShowcaseBundle\Screen\ScreenState;
use OpenStudio\UxShowcaseBundle\ShowcaseConfiguration;
use OpenStudio\UxShowcaseBundle\ShowcaseRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;
use Twig\Markup;

/**
 * The bare page one example is shown in, loaded by the frame of the component page: the template of the library with the
 * example in it, and nothing else. BarePageListener adds the height script and turns the profiler off.
 */
final readonly class PreviewController
{
    public const DEFAULT_TEMPLATE = '@OpenStudioUxShowcase/bare.html.twig';
    public const REQUIRED_BLOCKS = ['head_assets', 'body_attributes', 'content'];

    public function __construct(
        private Environment $twig,
        private ShowcaseConfiguration $configuration,
        private ShowcaseRegistry $registry,
        private ExampleRenderer $renderer,
        private PreviewOptionsResolver $previewResolver,
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
        $screen = new PreviewOptions(background: ScreenState::fromRequest($request, $this->configuration)->background);
        $preview = $this->previewResolver->resolve($library, $example, $screen);
        $background = null === $preview->background ? null : $this->configuration->backgrounds[$preview->background] ?? null;

        $templateName = $library->getBareTemplate() ?? self::DEFAULT_TEMPLATE;
        $template = $this->twig->load($templateName);
        foreach (self::REQUIRED_BLOCKS as $block) {
            if (!$template->hasBlock($block)) {
                throw new ShowcaseException(\sprintf('The bare template "%s" of the library "%s" has no block "%s": extend "%s" and override its blocks.', $templateName, $library->getName(), $block, self::DEFAULT_TEMPLATE));
            }
        }

        return new Response($template->render([
            'library' => $library,
            'component' => null === $example->component ? null : $this->registry->component($library->getName(), $example->component),
            'example' => $example,
            'content' => new Markup($this->renderer->render($library, $example, $screen), $this->twig->getCharset()),
            'preview' => $preview,
            'background' => $background,
            'html_attributes' => $background?->toHtmlAttributes() ?? [],
            'body_class' => $preview->bodyClass,
        ]), Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}

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

use OpenStudio\UxShowcaseBundle\Example\Example;
use OpenStudio\UxShowcaseBundle\Example\ExampleRenderer;
use OpenStudio\UxShowcaseBundle\Example\PreviewOptions;
use OpenStudio\UxShowcaseBundle\Example\PreviewOptionsResolver;
use OpenStudio\UxShowcaseBundle\Exception\ShowcaseException;
use OpenStudio\UxShowcaseBundle\Library\ComponentLibraryInterface;
use OpenStudio\UxShowcaseBundle\Screen\ScreenState;
use OpenStudio\UxShowcaseBundle\ShowcaseConfiguration;
use OpenStudio\UxShowcaseBundle\ShowcaseRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Markup;

/**
 * The bare page of an example: the bare template of its library with the example in it. Shared by the preview of the
 * example as it is declared and the preview of the values a visitor tried.
 *
 * @internal
 */
final readonly class BarePage
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

    /**
     * @param array<string, mixed>|null $props the props to draw a component example with instead of its own
     */
    public function render(Request $request, ComponentLibraryInterface $library, Example $example, ?array $props = null): Response
    {
        $screen = $this->screen($request);

        return $this->page($library, $example, new Markup($this->renderer->render($library, $example, $screen, $props), $this->twig->getCharset()), $screen, Response::HTTP_OK);
    }

    /**
     * The page with a fragment of markup in place of the example (the reasons a preview cannot be drawn).
     */
    public function withContent(Request $request, ComponentLibraryInterface $library, Example $example, string $markup, int $status): Response
    {
        return $this->page($library, $example, new Markup($markup, $this->twig->getCharset()), $this->screen($request), $status);
    }

    private function screen(Request $request): PreviewOptions
    {
        return new PreviewOptions(background: ScreenState::fromRequest($request, $this->configuration)->background);
    }

    private function page(ComponentLibraryInterface $library, Example $example, Markup $content, PreviewOptions $screen, int $status): Response
    {
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
            'content' => $content,
            'preview' => $preview,
            'background' => $background,
            'html_attributes' => $background?->toHtmlAttributes() ?? [],
            'body_class' => $preview->bodyClass,
        ]), $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}

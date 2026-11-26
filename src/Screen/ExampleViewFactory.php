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

namespace OpenStudio\UxShowcaseBundle\Screen;

use OpenStudio\UxShowcaseBundle\Example\Example;
use OpenStudio\UxShowcaseBundle\Example\ExampleCodeGenerator;
use OpenStudio\UxShowcaseBundle\Example\PreviewOptions;
use OpenStudio\UxShowcaseBundle\Example\PreviewOptionsResolver;
use OpenStudio\UxShowcaseBundle\Library\ComponentLibraryInterface;
use OpenStudio\UxShowcaseBundle\Library\ComponentReference;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
final readonly class ExampleViewFactory
{
    public function __construct(
        private ExampleCodeGenerator $codeGenerator,
        private PreviewOptionsResolver $previewResolver,
        private ActionResolver $actions,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function create(ComponentLibraryInterface $library, ?ComponentReference $component, Example $example, ScreenState $state): ExampleView
    {
        $query = null === $state->background ? [] : [ScreenState::BACKGROUND_PARAMETER => $state->background];
        $previewUrl = null === $component
            ? $this->urlGenerator->generate('ux_showcase_doc_preview', ['library' => $library->getName(), 'slug' => $example->slug] + $query)
            : $this->urlGenerator->generate('ux_showcase_preview', ['library' => $library->getName(), 'component' => $component->name, 'slug' => $example->slug] + $query);

        $preview = $this->previewResolver->resolve($library, $example, new PreviewOptions(background: $state->background));

        return new ExampleView(
            $example,
            $this->codeGenerator->generate($example),
            $previewUrl,
            $preview->height,
            $this->actions->resolve(new ActionContext($library->getName(), $component, $example)),
        );
    }
}

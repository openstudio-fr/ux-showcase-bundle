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

namespace OpenStudio\UxShowcaseBundle\Example;

use OpenStudio\UxShowcaseBundle\Library\ComponentLibraryInterface;
use Twig\Environment;
use Twig\Markup;

/**
 * Renders one example to HTML with the Twig environment of the application, inside the wrapper of the library when the
 * resolved preview options say so. A live component needs a current request, as it does anywhere.
 *
 * @api
 */
final readonly class ExampleRenderer
{
    public function __construct(
        private Environment $twig,
        private ExampleCodeGenerator $codeGenerator,
        private PreviewOptionsResolver $previewResolver,
    ) {
    }

    /**
     * @param array<string, mixed>|null $props the props to draw a component example with instead of its own: any value,
     *                                         objects included (the preview of values a visitor tried); the content and the
     *                                         blocks of the example are kept, and a free example has no props
     */
    public function render(ComponentLibraryInterface $library, Example $example, PreviewOptions $screen = new PreviewOptions(), ?array $props = null): string
    {
        $preview = $this->previewResolver->resolve($library, $example, $screen);

        $html = $this->renderExample($example, $props);

        $wrapper = $library->getWrapperTemplate();
        if (!$preview->wrapper || null === $wrapper) {
            return $html;
        }

        return $this->twig->render($wrapper, [
            'content' => new Markup($html, $this->twig->getCharset()),
            'example' => $example,
            'library' => $library,
            'preview' => $preview,
        ]);
    }

    /**
     * @param array<string, mixed>|null $props
     */
    private function renderExample(Example $example, ?array $props): string
    {
        $name = 'showcase:'.hash('xxh3', serialize($example));

        if (null !== $props && null !== $example->content && null === $example->source) {
            // The values travel as a variable: no template is built from them, and every key is read as `props['key']`.
            return $this->twig->createTemplate($this->codeGenerator->generateBound($example, array_keys($props), 'props'), $name.':bound')
                ->render(['props' => $props]);
        }

        if (null !== $example->source) {
            return $this->twig->createTemplate($example->source, $name)->render();
        }

        if (null === $example->content) {
            return $this->twig->createTemplate('{{ component(name, props) }}', 'showcase:component')
                ->render(['name' => $example->component, 'props' => $props ?? $example->props]);
        }

        // Content and blocks are markup: the component syntax is what turns them into blocks.
        return $this->twig->createTemplate($this->codeGenerator->generate($example), $name)->render();
    }
}

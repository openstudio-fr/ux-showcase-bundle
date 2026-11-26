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

    public function render(ComponentLibraryInterface $library, Example $example, PreviewOptions $screen = new PreviewOptions()): string
    {
        $preview = $this->previewResolver->resolve($library, $example, $screen);

        $html = $this->renderExample($example);

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

    private function renderExample(Example $example): string
    {
        $name = 'showcase:'.hash('xxh3', serialize($example));

        if (null !== $example->source) {
            return $this->twig->createTemplate($example->source, $name)->render();
        }

        if (null === $example->content) {
            return $this->twig->createTemplate('{{ component(name, props) }}', 'showcase:component')
                ->render(['name' => $example->component, 'props' => $example->props]);
        }

        // Content and blocks are markup: the component syntax is what turns them into blocks.
        return $this->twig->createTemplate($this->codeGenerator->generate($example), $name)->render();
    }
}

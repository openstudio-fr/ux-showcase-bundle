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

namespace OpenStudio\UxShowcaseBundle\Props;

use OpenStudio\UxShowcaseBundle\Library\ComponentCatalog;
use Twig\Environment;

/**
 * Reads the template of a component, found through TwigComponent: see TemplateSourceReader for what it reads.
 *
 * @internal
 */
final readonly class TemplatePropsReader implements PropsReaderInterface
{
    public function __construct(
        private ComponentCatalog $catalog,
        private Environment $twig,
        private TemplateSourceReader $reader,
    ) {
    }

    public function read(string $component): ComponentDoc
    {
        return $this->reader->read($component, $this->twig->getLoader()->getSourceContext($this->catalog->template($component)));
    }
}

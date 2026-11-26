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

use OpenStudio\UxShowcaseBundle\ShowcaseConfiguration;
use OpenStudio\UxShowcaseBundle\ShowcaseRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

final readonly class IndexController
{
    public function __construct(
        private Environment $twig,
        private ShowcaseConfiguration $configuration,
        private ShowcaseRegistry $registry,
    ) {
    }

    // A plain Response, never #[Template]: a host with its own view listener must not see a controller result it did not expect.
    #[Route('/', name: 'ux_showcase_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        $libraries = [];
        foreach ($this->registry->libraries() as $library) {
            $libraries[] = ['library' => $library, 'groups' => $this->registry->groups($library->getName())];
        }

        return new Response($this->twig->render('@OpenStudioUxShowcase/index.html.twig', [
            'configuration' => $this->configuration,
            'libraries' => $libraries,
        ]));
    }
}

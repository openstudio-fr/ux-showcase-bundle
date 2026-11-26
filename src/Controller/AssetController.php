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

use OpenStudio\UxShowcaseBundle\Screen\Assets;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The styles and scripts of the screens, served from the bundle: no dependency on the asset pipeline of the host, and
 * no inline script either, so a strict content security policy lets them through.
 */
final readonly class AssetController
{
    public function __construct(private Assets $assets)
    {
    }

    #[Route('/_assets/{file}', name: 'ux_showcase_asset', requirements: ['file' => '[a-z]+\.(?:css|js)'], methods: ['GET'])]
    public function __invoke(Request $request, string $file): Response
    {
        if (!isset(Assets::FILES[$file])) {
            throw new NotFoundHttpException(\sprintf('Unknown asset "%s".', $file));
        }

        $path = $this->assets->path($file);
        $response = new Response((string) file_get_contents($path));
        $response->headers->set('Content-Type', Assets::FILES[$file].'; charset=UTF-8');
        // The URL carries the hash of the file: an unchanged URL is an unchanged file.
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setEtag($this->assets->hash($file));
        $response->isNotModified($request);

        return $response;
    }
}

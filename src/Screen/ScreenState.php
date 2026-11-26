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

use OpenStudio\UxShowcaseBundle\ShowcaseConfiguration;
use Symfony\Component\HttpFoundation\Request;

/**
 * What the visitor chose on the component page, kept in the query string so that a link shares it: a width (the first
 * one by default) and a background (none: each example keeps its own).
 *
 * @internal
 */
final readonly class ScreenState
{
    public const VIEWPORT_PARAMETER = 'viewport';
    public const BACKGROUND_PARAMETER = 'background';

    public function __construct(
        public string $viewport,
        public ?string $background,
    ) {
    }

    public static function fromRequest(Request $request, ShowcaseConfiguration $configuration): self
    {
        $viewport = $request->query->get(self::VIEWPORT_PARAMETER);
        if (!\is_string($viewport) || !\array_key_exists($viewport, $configuration->viewports)) {
            $viewport = (string) array_key_first($configuration->viewports);
        }

        $background = $request->query->get(self::BACKGROUND_PARAMETER);
        if (!\is_string($background) || !isset($configuration->backgrounds[$background])) {
            $background = null;
        }

        return new self($viewport, $background);
    }
}

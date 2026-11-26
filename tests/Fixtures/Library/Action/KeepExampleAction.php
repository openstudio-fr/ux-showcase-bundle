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

namespace OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Action;

use OpenStudio\UxShowcaseBundle\Screen\ActionContext;
use OpenStudio\UxShowcaseBundle\Screen\ActionTarget;
use OpenStudio\UxShowcaseBundle\Screen\ComponentActionInterface;

/**
 * An example-level action that is a plain link, for the examples of a component (a documentation page has none).
 */
final readonly class KeepExampleAction implements ComponentActionInterface
{
    public function getName(): string
    {
        return 'keep-example';
    }

    public function getLabel(): string
    {
        return 'Keep as example';
    }

    public function isVisible(ActionContext $context): bool
    {
        return null !== $context->example && null !== $context->component && !$context->example->isFree();
    }

    public function getTarget(ActionContext $context): ActionTarget
    {
        return new ActionTarget('/cms/keep?example='.(string) $context->example?->slug);
    }
}

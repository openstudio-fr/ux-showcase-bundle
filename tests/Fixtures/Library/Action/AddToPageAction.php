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
 * A component-level action that posts a form, and only for Demo:Button.
 */
final readonly class AddToPageAction implements ComponentActionInterface
{
    public function getName(): string
    {
        return 'add-to-page';
    }

    public function getLabel(): string
    {
        return 'Add to a page';
    }

    public function isVisible(ActionContext $context): bool
    {
        return null === $context->example && 'Demo:Button' === $context->component?->name;
    }

    public function getTarget(ActionContext $context): ActionTarget
    {
        return new ActionTarget('/cms/add', 'POST', ['component' => (string) $context->component?->name]);
    }
}

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

/**
 * The visible actions for a context, in priority order.
 *
 * @internal
 */
final readonly class ActionResolver
{
    /**
     * @param iterable<ComponentActionInterface> $actions
     */
    public function __construct(private iterable $actions)
    {
    }

    /**
     * @return list<ResolvedAction>
     */
    public function resolve(ActionContext $context): array
    {
        $resolved = [];
        foreach ($this->actions as $action) {
            if ($action->isVisible($context)) {
                $resolved[] = new ResolvedAction($action->getName(), $action->getLabel(), $action->getTarget($context));
            }
        }

        return $resolved;
    }
}

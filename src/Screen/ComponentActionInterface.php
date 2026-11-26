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
 * A button of the component page that a host or an add-on contributes: "Keep as example", "Add to a page". Autoconfigured
 * (tag `ux_showcase.action`, optional `priority`: highest first).
 *
 * It is asked once for the component (`$context->example` is null: header of the page) and once for each example (the
 * toolbar of the example). Say no in `isVisible()` where it does not apply.
 *
 * @api
 */
interface ComponentActionInterface
{
    /**
     * Unique and stable: it is an identifier in the markup (`data-action="…"`).
     */
    public function getName(): string;

    /**
     * Text of the button, already translated.
     */
    public function getLabel(): string;

    public function isVisible(ActionContext $context): bool;

    public function getTarget(ActionContext $context): ActionTarget;
}

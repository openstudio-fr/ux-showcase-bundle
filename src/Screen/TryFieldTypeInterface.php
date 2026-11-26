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

use OpenStudio\UxShowcaseBundle\Props\PropDoc;

/**
 * A kind of field of the "Try" form of an example: text, checkbox, select... or a richer one a host brings (a picked
 * image, a colour). Autoconfigured (tag `ux_showcase.try_field_type`, optional `priority`: highest first; the built-in
 * fields are at -100, so a host type that supports a prop wins over them).
 *
 * For each prop of the component the first type that supports it gives the field; a prop no type supports is shown
 * read-only. The template draws the control only; the label, the hint and the error of the field are drawn around it.
 * Its variables are `field` (TryField) and `try` (TryPanel); the control carries `id="{{ field.id }}"`,
 * `name="{{ field.name }}"` (the form works without JavaScript: it posts that name, so a host maps the value in a
 * PostedValuesNormalizerInterface), `aria-describedby="{{ field.describedBy }}"` and `data-try-control`.
 *
 * With a module the field is driven by JavaScript: the script of the screens imports the module only when a field of this
 * type is on the page and calls its default export (or `init`) with the field, once. See the README for the field API.
 *
 * @api
 */
interface TryFieldTypeInterface
{
    /**
     * Unique and stable: it is `data-field-type="…"` in the markup. `text`, `number`, `checkbox`, `select` and `json` are the built-in ones.
     */
    public function getName(): string;

    public function supports(PropDoc $prop): bool;

    /**
     * Twig template of the control, `@Namespace/path.html.twig`.
     */
    public function getTemplate(): string;

    /**
     * Address of a JavaScript module (ES module, same origin as the page or allowed by its content security policy), or null.
     */
    public function getModuleUrl(): ?string;
}

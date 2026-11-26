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

namespace OpenStudio\UxShowcaseBundle\Preview;

/**
 * A step run on the values a visitor posted, before they are checked and converted to the types the props declare. A
 * host uses it to map its own form fields to props (a picked image id to an object, a split date to one string) and to
 * normalise types. Autoconfigured (tag `ux_showcase.posted_values_normalizer`, optional `priority`, highest first).
 *
 * What a step returns is what the next one gets; what is left at the end must be props of the component. A step takes
 * the fields it knows out of the array, and may put in objects: an object that satisfies the declared type is kept as it
 * is (values that come from the request itself are never turned into an interface, a `mixed` or an `object`).
 *
 * @api
 */
interface PostedValuesNormalizerInterface
{
    /**
     * @param array<string, mixed> $values prop or field name to value, as sent (JSON object or form fields, without the CSRF token)
     *
     * @return array<string, mixed>
     */
    public function normalize(array $values, PostedValuesContext $context): array;
}

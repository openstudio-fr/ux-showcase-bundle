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

namespace OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Preview;

use OpenStudio\UxShowcaseBundle\Preview\PostedValuesContext;
use OpenStudio\UxShowcaseBundle\Preview\PostedValuesNormalizerInterface;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\VehicleCard;

// What a host does with a field of its own form: a picked id of its catalogue becomes the object the prop needs.
final class CatalogueVehicleNormalizer implements PostedValuesNormalizerInterface
{
    public function normalize(array $values, PostedValuesContext $context): array
    {
        if (isset($values['vehicle_id'])) {
            $values['vehicle'] = new VehicleCard('Catalogue car '.(\is_scalar($values['vehicle_id']) ? $values['vehicle_id'] : '?'), 5.0);
        }
        unset($values['vehicle_id']);

        return $values;
    }
}

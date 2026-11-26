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

use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Enums inside an object, by value (backed) or case name (unit), both ways. Replaces BackedEnumNormalizer, which on some versions
 * of the Serializer reports the object and not the field when a case is unknown.
 *
 * @internal
 */
final class EnumDenormalizer implements DenormalizerInterface, NormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): \UnitEnum
    {
        $path = isset($context['deserialization_path']) && \is_string($context['deserialization_path']) ? $context['deserialization_path'] : null;

        if (\is_string($data) || \is_int($data)) {
            foreach ($this->cases($type) as $case) {
                if ((string) ($case instanceof \BackedEnum ? $case->value : $case->name) === (string) $data) {
                    return $case;
                }
            }
        }

        throw NotNormalizableValueException::createForUnexpectedDataType(\sprintf('The data is not a case of %s.', $type), $data, [$type], $path, true);
    }

    /**
     * @return list<\UnitEnum>
     */
    private function cases(string $type): array
    {
        return enum_exists($type) ? $type::cases() : [];
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): string|int
    {
        \assert($data instanceof \UnitEnum);

        return $data instanceof \BackedEnum ? $data->value : $data->name;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof \UnitEnum;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return enum_exists($type);
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }
}

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

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * The Serializer that turns posted data into objects and back, built here and not taken from the application so that
 * what a preview accepts does not depend on the serialization groups, name converters or normalizers the application
 * configured: object properties and constructors typed by reflection and phpdoc (property-info), enums, dates,
 * lists, and the discriminator maps (`#[DiscriminatorMap]`) of interfaces and abstract classes.
 *
 * @internal
 */
final readonly class ValueSerializer
{
    public Serializer $serializer;
    public ClassDiscriminatorResolverInterface $discriminator;

    public function __construct()
    {
        $this->discriminator = new ClassDiscriminatorFromClassMetadata(new ClassMetadataFactory(new AttributeLoader()));

        $reflection = new ReflectionExtractor();
        $propertyInfo = new PropertyInfoExtractor([$reflection], [new PhpStanExtractor(), $reflection], [], [$reflection], [$reflection]);

        $this->serializer = new Serializer([
            new EnumDenormalizer(),
            new DateTimeNormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer(
                classMetadataFactory: new ClassMetadataFactory(new AttributeLoader()),
                propertyAccessor: PropertyAccess::createPropertyAccessor(),
                propertyTypeExtractor: $propertyInfo,
                classDiscriminatorResolver: $this->discriminator,
            ),
        ]);
    }
}

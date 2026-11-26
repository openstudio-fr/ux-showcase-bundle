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

use OpenStudio\UxShowcaseBundle\Example\Example;
use OpenStudio\UxShowcaseBundle\Props\ComponentDocumentation;
use OpenStudio\UxShowcaseBundle\Props\PropDoc;

/**
 * The values to put in the form of a preview: the props of an example, as JSON-safe data. Only the props of the
 * component that can be posted back are given (a prop outside the table, or typed `mixed`, would be refused), and
 * objects are written as arrays, enums as their value (their name for a unit enum) and dates as ISO 8601 text, so that
 * posting the result back renders the example as it was.
 *
 * @api
 */
final readonly class PreviewPrefill
{
    public function __construct(
        private ComponentDocumentation $documentation,
        private TypedValueConverter $converter,
        private ValueSerializer $serializer,
    ) {
    }

    /**
     * @return array<string, mixed> prop name to value, in the order of the example
     */
    public function forExample(Example $example): array
    {
        if (null === $example->component) {
            return [];
        }

        $doc = $this->documentation->get($example->component);

        $values = [];
        foreach ($example->props as $name => $value) {
            $prop = $doc->prop((string) $name);
            if ($prop instanceof PropDoc && $this->converter->isEditable($prop)) {
                $values[(string) $name] = $this->normalize($value);
            }
        }

        return $values;
    }

    /**
     * Whether a visitor can try other values for the prop.
     */
    public function isEditable(PropDoc $prop): bool
    {
        return $this->converter->isEditable($prop);
    }

    public function normalize(mixed $value): mixed
    {
        return match (true) {
            null === $value, \is_scalar($value) => $value,
            \is_array($value) => array_map($this->normalize(...), $value),
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \UnitEnum => $value->name,
            $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
            \is_object($value) => $this->normalize($this->serializer->serializer->normalize($value, 'json')),
            default => null,
        };
    }
}

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

namespace OpenStudio\UxShowcaseBundle\Props;

/**
 * One prop of a component, as the readers know it.
 *
 * @api
 */
final readonly class PropDoc
{
    public const UNKNOWN_TYPE = 'mixed';

    public const SOURCE_PROPS_TAG = 'props-tag';
    public const SOURCE_DOC_COMMENT = 'doc-comment';
    public const SOURCE_ANNOTATION = 'annotation';
    public const SOURCE_PROPERTY = 'property';
    public const SOURCE_MOUNT = 'mount';
    public const SOURCE_PRE_MOUNT = 'pre-mount';

    /**
     * @param string                                $type          as a string (`string`, `?int`, `list<VehicleDto>`, `App\Suit|null`); `mixed` when nothing says
     * @param mixed                                 $default       only meaningful when $hasDefault; null when the default is not a literal
     * @param list<bool|int|float|string|null>|null $allowedValues null when the prop takes any value of its type
     * @param list<string>                          $sources       where it was read, in reading order: one of the SOURCE_* constants
     * @param list<self>                            $fields        fields of the rows of a list, from `{# @prop list.field type description #}`
     */
    public function __construct(
        public string $name,
        public string $type = self::UNKNOWN_TYPE,
        public bool $hasDefault = false,
        public mixed $default = null,
        public string $description = '',
        public bool $required = true,
        public ?array $allowedValues = null,
        public array $sources = [],
        public array $fields = [],
    ) {
    }

    /**
     * `required` follows from the rest: no default and a type that does not accept null.
     *
     * @param list<bool|int|float|string|null>|null $allowedValues
     * @param list<string>                          $sources
     * @param list<self>                            $fields
     */
    public static function create(
        string $name,
        string $type = self::UNKNOWN_TYPE,
        bool $hasDefault = false,
        mixed $default = null,
        string $description = '',
        ?array $allowedValues = null,
        array $sources = [],
        array $fields = [],
    ): self {
        return new self($name, $type, $hasDefault, $default, $description, !$hasDefault && !self::acceptsNull($type), $allowedValues, $sources, $fields);
    }

    public static function acceptsNull(string $type): bool
    {
        return str_starts_with($type, '?') || \in_array('null', preg_split('/[|\s]+/', $type) ?: [], true);
    }

    /**
     * @param list<bool|int|float|string|null> $allowedValues
     */
    public function withAllowedValues(array $allowedValues, string $source): self
    {
        return new self($this->name, $this->type, $this->hasDefault, $this->default, $this->description, $this->required, $allowedValues, array_values(array_unique([...$this->sources, $source])), $this->fields);
    }

    public function isKnownType(): bool
    {
        return self::UNKNOWN_TYPE !== $this->type;
    }

    /**
     * Merge rule, `$this` being what the earlier readers know and `$later` what a later one adds:
     * the description and the allowed values come from the first reader that has some; the type and the default come
     * from the last reader that knows them (reflection is more precise than a comment); sources and fields add up;
     * `required` is worked out again from the merged default and type.
     */
    public function mergedWith(self $later): self
    {
        $type = $later->isKnownType() ? $later->type : $this->type;
        $hasDefault = $later->hasDefault || $this->hasDefault;
        $default = $later->hasDefault ? $later->default : $this->default;

        $fields = [];
        foreach ([...$this->fields, ...$later->fields] as $field) {
            $fields[$field->name] = isset($fields[$field->name]) ? $fields[$field->name]->mergedWith($field) : $field;
        }

        return self::create(
            $this->name,
            $type,
            $hasDefault,
            $default,
            '' !== $this->description ? $this->description : $later->description,
            $this->allowedValues ?? $later->allowedValues,
            array_values(array_unique([...$this->sources, ...$later->sources])),
            array_values($fields),
        );
    }
}

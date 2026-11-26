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

use OpenStudio\UxShowcaseBundle\Preview\PropShape;
use OpenStudio\UxShowcaseBundle\Preview\TypedValueConverter;
use OpenStudio\UxShowcaseBundle\Props\PropDoc;

/**
 * The fields of the bundle: one per kind of control the declared type of an editable prop asks for.
 *
 * @internal
 */
final readonly class BuiltinTryFieldType implements TryFieldTypeInterface
{
    public const NAMES = ['text', 'number', 'checkbox', 'select', 'json'];

    /**
     * @param list<string> $kinds the PropShape kinds this field draws
     */
    public function __construct(
        private string $name,
        private string $template,
        private array $kinds,
        private TypedValueConverter $converter,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function supports(PropDoc $prop): bool
    {
        $shape = $this->converter->shape($prop);

        return $shape instanceof PropShape && \in_array($shape->kind, $this->kinds, true);
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getModuleUrl(): ?string
    {
        return null;
    }
}

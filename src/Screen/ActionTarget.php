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
 * What an action points at: a link (GET, no field) or a form (POST, or GET with hidden fields).
 *
 * @api
 */
final readonly class ActionTarget
{
    /**
     * @param array<string, string> $fields hidden fields of the form
     */
    public function __construct(
        public string $url,
        public string $method = 'GET',
        public array $fields = [],
    ) {
        if (!\in_array($method, ['GET', 'POST'], true)) {
            throw new \InvalidArgumentException(\sprintf('An action target is a GET or a POST, "%s" given.', $method));
        }
    }

    public function isForm(): bool
    {
        return 'GET' !== $this->method || [] !== $this->fields;
    }
}

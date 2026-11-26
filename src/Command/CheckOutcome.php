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

namespace OpenStudio\UxShowcaseBundle\Command;

/**
 * What the check made of one example.
 *
 * @internal
 */
final readonly class CheckOutcome
{
    public const string PASSED = 'passed';
    public const string FAILED = 'failed';
    public const string SKIPPED = 'skipped';

    /**
     * @param self::PASSED|self::FAILED|self::SKIPPED $status
     * @param string|null                             $component null for a documentation page
     */
    public function __construct(
        public string $library,
        public ?string $component,
        public string $slug,
        public string $status,
        public ?string $message = null,
        public float $seconds = 0.0,
    ) {
    }

    public function label(): string
    {
        return \sprintf('%s / %s / %s', $this->library, $this->component ?? '_docs', $this->slug);
    }
}

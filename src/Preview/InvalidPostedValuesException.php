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

use OpenStudio\UxShowcaseBundle\Exception\ShowcaseException;

/**
 * The posted values cannot be used; the preview answers 422 with these errors.
 *
 * @api
 */
final class InvalidPostedValuesException extends ShowcaseException
{
    /**
     * @param list<PostedValueError> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', array_map(static fn (PostedValueError $error): string => '' === $error->path ? $error->message : $error->path.': '.$error->message, $errors)));
    }

    public static function at(string $path, string $message): self
    {
        return new self([new PostedValueError($path, $message)]);
    }
}

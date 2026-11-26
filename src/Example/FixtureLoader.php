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

namespace OpenStudio\UxShowcaseBundle\Example;

/**
 * Loads a JSON fixture by a path relative to ux_showcase.fixtures_directory.
 *
 * @api
 */
final readonly class FixtureLoader
{
    public function __construct(private ?string $directory)
    {
    }

    /**
     * @return array<mixed>
     */
    public function load(string $path): array
    {
        if (null === $this->directory) {
            throw new \LogicException(\sprintf('Cannot load the fixture "%s": set ux_showcase.fixtures_directory.', $path));
        }

        if (str_contains($path, '..') || str_starts_with($path, '/')) {
            throw new \InvalidArgumentException(\sprintf('The fixture path "%s" must be relative to the fixtures directory and stay inside it.', $path));
        }

        $file = rtrim($this->directory, '/').'/'.$path;
        $json = is_file($file) ? file_get_contents($file) : false;
        if (false === $json) {
            throw new \InvalidArgumentException(\sprintf('The fixture "%s" does not exist in "%s".', $path, $this->directory));
        }

        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($data)) {
            throw new \UnexpectedValueException(\sprintf('The fixture "%s" must hold a JSON object or list.', $path));
        }

        return $data;
    }
}

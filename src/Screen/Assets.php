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

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The few files the screens need, served by AssetController: the application does not have to know them, nor to run an
 * asset pipeline for them. The URL carries a hash of the file, so a long cache is safe.
 *
 * @internal
 */
final readonly class Assets
{
    public const FILES = ['showcase.css' => 'text/css', 'showcase.js' => 'text/javascript', 'bare.js' => 'text/javascript'];

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function path(string $file): string
    {
        if (!isset(self::FILES[$file])) {
            throw new \InvalidArgumentException(\sprintf('Unknown showcase asset "%s".', $file));
        }

        return __DIR__.'/../../assets/'.$file;
    }

    public function url(string $file): string
    {
        return $this->urlGenerator->generate('ux_showcase_asset', ['file' => $file, 'v' => substr($this->hash($file), 0, 8)]);
    }

    public function hash(string $file): string
    {
        return hash_file('xxh3', $this->path($file)) ?: throw new \RuntimeException(\sprintf('Cannot read the showcase asset "%s".', $file));
    }
}

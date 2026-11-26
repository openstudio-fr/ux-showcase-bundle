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

use OpenStudio\UxShowcaseBundle\Exception\ShowcaseException;
use OpenStudio\UxShowcaseBundle\Twig\ExampleFileReader;
use Symfony\Component\Finder\Finder;

/**
 * Reads a directory of example files: `Form/Input.showcase.twig` holds the examples of the component `Form:Input`
 * (below the component prefix of the library, if it has one); `_docs/<Group>/<name>.showcase.twig` holds documentation
 * pages of the group `<Group>`.
 *
 * @internal
 */
final readonly class TwigExampleFileProvider implements ExampleProviderInterface
{
    public const EXTENSION = '.showcase.twig';
    public const DOCUMENTATION_DIRECTORY = '_docs';

    public function __construct(
        private string $directory,
        private ?string $componentPrefix = null,
    ) {
    }

    public function getExamples(): iterable
    {
        if (!is_dir($this->directory)) {
            throw new ShowcaseException(\sprintf('The examples directory "%s" does not exist.', $this->directory));
        }

        $reader = new ExampleFileReader();
        $files = (new Finder())->files()->in($this->directory)->name('*'.self::EXTENSION)->sortByName();

        foreach ($files as $file) {
            $relative = str_replace(\DIRECTORY_SEPARATOR, '/', substr($file->getRelativePathname(), 0, -\strlen(self::EXTENSION)));
            $source = file_get_contents($file->getPathname());
            if (false === $source) {
                throw new ShowcaseException(\sprintf('Cannot read the example file "%s".', $file->getPathname()));
            }

            if (str_starts_with($relative, self::DOCUMENTATION_DIRECTORY.'/')) {
                $parts = explode('/', substr($relative, \strlen(self::DOCUMENTATION_DIRECTORY) + 1));
                if (2 !== \count($parts)) {
                    throw new ShowcaseException(\sprintf('The documentation page "%s" must sit in a group directory: %s/<Group>/<name>%s.', $file->getPathname(), self::DOCUMENTATION_DIRECTORY, self::EXTENSION));
                }

                yield from $reader->read($source, $file->getPathname(), null, $parts[0]);

                continue;
            }

            $component = str_replace('/', ':', $relative);
            if (null !== $this->componentPrefix) {
                $component = $this->componentPrefix.':'.$component;
            }

            yield from $reader->read($source, $file->getPathname(), $component);
        }
    }
}

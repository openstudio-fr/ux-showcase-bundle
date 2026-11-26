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

namespace OpenStudio\UxShowcaseBundle\Library;

use Symfony\Component\Finder\Finder;
use Symfony\UX\TwigComponent\ComponentFactory;
use Twig\Loader\FilesystemLoader;

/**
 * Knows which TwigComponents exist. The only class that touches ComponentFactory, which is internal to TwigComponent.
 *
 * A library written in PHP takes it by autowiring to list the components. `names()` and `exists()` are the API.
 *
 * @api
 */
final class ComponentCatalog
{
    /** @var list<string>|null */
    private ?array $names = null;

    /**
     * @param array<class-string, string> $classMap           component class => name, as TwigComponent registered it
     * @param string                      $anonymousDirectory twig_component.anonymous_template_directory
     */
    public function __construct(
        private readonly ComponentFactory $factory,
        private readonly FilesystemLoader $loader,
        private readonly array $classMap,
        private readonly string $anonymousDirectory = 'components',
    ) {
    }

    /**
     * @return list<string> sorted
     */
    public function names(): array
    {
        if (null === $this->names) {
            $names = array_values($this->classMap);
            foreach ($this->anonymousNames() as $name) {
                $names[] = $name;
            }
            $names = array_values(array_unique($names));
            sort($names);
            $this->names = $names;
        }

        return $this->names;
    }

    public function exists(string $name): bool
    {
        try {
            $this->factory->metadataFor($name);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /**
     * @return class-string|null null for an anonymous component
     *
     * @internal exposes TwigComponent metadata
     */
    public function className(string $name): ?string
    {
        $metadata = $this->factory->metadataFor($name);

        // getClass() reads an undefined key on an anonymous component.
        return $metadata->isAnonymous() ? null : $metadata->getClass();
    }

    /**
     * @return string the Twig template of the component, anonymous or not
     *
     * @internal exposes TwigComponent metadata
     */
    public function template(string $name): string
    {
        return $this->factory->metadataFor($name)->getTemplate();
    }

    /**
     * @return list<string>
     */
    private function anonymousNames(): array
    {
        $directories = [];
        foreach ($this->loader->getNamespaces() as $namespace) {
            if (str_starts_with($namespace, '!')) {
                continue;
            }
            foreach ($this->loader->getPaths($namespace) as $path) {
                $directory = $path.'/'.(FilesystemLoader::MAIN_NAMESPACE === $namespace ? $this->anonymousDirectory : 'components');
                if (is_dir($directory)) {
                    $directories[$directory] = $namespace;
                }
            }
        }

        if ([] === $directories) {
            return [];
        }

        $names = [];
        foreach ((new Finder())->files()->in(array_keys($directories))->notPath('/_/')->name('*.html.twig') as $file) {
            $name = substr(str_replace(\DIRECTORY_SEPARATOR, ':', $file->getRelativePathname()), 0, -10);
            $root = $file->getPath();
            if ('' !== $file->getRelativePath()) {
                $root = rtrim(substr($root, 0, -\strlen($file->getRelativePath())), \DIRECTORY_SEPARATOR);
            }
            $namespace = $directories[$root] ?? FilesystemLoader::MAIN_NAMESPACE;
            $names[] = FilesystemLoader::MAIN_NAMESPACE === $namespace ? $name : $namespace.':'.$name;
        }

        return $names;
    }
}

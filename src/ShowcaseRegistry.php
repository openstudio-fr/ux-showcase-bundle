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

namespace OpenStudio\UxShowcaseBundle;

use OpenStudio\UxShowcaseBundle\Example\Example;
use OpenStudio\UxShowcaseBundle\Example\ExampleProviderInterface;
use OpenStudio\UxShowcaseBundle\Exception\DuplicateExampleException;
use OpenStudio\UxShowcaseBundle\Exception\ShowcaseException;
use OpenStudio\UxShowcaseBundle\Exception\UnknownComponentException;
use OpenStudio\UxShowcaseBundle\Library\ComponentGroup;
use OpenStudio\UxShowcaseBundle\Library\ComponentLibraryInterface;
use OpenStudio\UxShowcaseBundle\Library\ComponentReference;
use OpenStudio\UxShowcaseBundle\Library\ComponentStatus;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Libraries, their components, their examples. Built on first use, and validated as a whole: a duplicate slug or an
 * example for an unknown component fails here, with a message that names it.
 *
 * @phpstan-type Entry array{library: ComponentLibraryInterface, components: array<string, ComponentReference>}
 * @phpstan-type Data array{components: array<string, array<string, ComponentReference>>, examples: array<string, array<string, list<Example>>>, docPages: array<string, list<Example>>}
 *
 * @api
 */
final class ShowcaseRegistry
{
    /** @var array<string, Entry>|null */
    private ?array $libraries = null;

    /** @var array<string, array<string, list<Example>>> library => component => examples */
    private array $examples = [];

    /** @var array<string, list<Example>> library => documentation pages */
    private array $docPages = [];

    /**
     * @param iterable<ComponentLibraryInterface> $libraryServices
     * @param iterable<ExampleProviderInterface>  $sharedExampleProviders
     * @param CacheItemPoolInterface|null         $cache                  keeps the result of the build outside debug
     */
    public function __construct(
        private readonly iterable $libraryServices,
        private readonly iterable $sharedExampleProviders,
        private readonly ?CacheItemPoolInterface $cache = null,
        private readonly bool $debug = false,
    ) {
    }

    /**
     * @return list<ComponentLibraryInterface> by name
     */
    public function libraries(): array
    {
        return array_values(array_map(static fn (array $entry): ComponentLibraryInterface => $entry['library'], $this->load()));
    }

    public function library(string $name): ?ComponentLibraryInterface
    {
        return $this->load()[$name]['library'] ?? null;
    }

    /**
     * Visible components (not hidden), in the display order of the library.
     *
     * @return list<ComponentReference>
     */
    public function components(string $library): array
    {
        return array_values(array_filter(
            $this->entry($library)['components'],
            static fn (ComponentReference $reference): bool => ComponentStatus::Hidden !== $reference->status,
        ));
    }

    public function component(string $library, string $name): ?ComponentReference
    {
        $reference = $this->entry($library)['components'][$name] ?? null;

        return null === $reference || ComponentStatus::Hidden === $reference->status ? null : $reference;
    }

    /**
     * @return list<ComponentGroup> in the order the library gives; groups it does not name come after, alphabetically
     */
    public function groups(string $library): array
    {
        $entry = $this->entry($library);
        $components = [];
        foreach ($this->components($library) as $reference) {
            $components[$reference->group][] = $reference;
        }
        $docPages = [];
        foreach ($this->docPages[$library] ?? [] as $page) {
            \assert(null !== $page->group);
            $docPages[$page->group][] = $page;
        }

        $names = array_keys($components + $docPages);
        $order = array_flip($entry['library']->getGroups());
        usort($names, static fn (string $left, string $right): int => [$order[$left] ?? \PHP_INT_MAX, $left] <=> [$order[$right] ?? \PHP_INT_MAX, $right]);

        return array_map(static fn (string $name): ComponentGroup => new ComponentGroup($name, $components[$name] ?? [], $docPages[$name] ?? []), $names);
    }

    /**
     * @return list<Example> in the order the providers gave them; empty for a hidden component
     */
    public function examples(string $library, string $component): array
    {
        return null === $this->component($library, $component) ? [] : $this->examples[$library][$component] ?? [];
    }

    /**
     * @return list<Example>
     */
    public function docPages(string $library): array
    {
        $this->entry($library);

        return $this->docPages[$library] ?? [];
    }

    /**
     * A documentation page has no component: pass null.
     */
    public function findExample(string $library, ?string $component, string $slug): ?Example
    {
        $candidates = null === $component ? $this->docPages($library) : $this->examples($library, $component);
        foreach ($candidates as $example) {
            if ($example->slug === $slug) {
                return $example;
            }
        }

        return null;
    }

    /**
     * @return Entry
     */
    private function entry(string $library): array
    {
        return $this->load()[$library] ?? throw new ShowcaseException(\sprintf('Unknown library "%s": known libraries are %s.', $library, implode(', ', array_keys($this->load())) ?: 'none'));
    }

    /**
     * @return array<string, Entry>
     */
    private function load(): array
    {
        if (null !== $this->libraries) {
            return $this->libraries;
        }

        $services = [];
        foreach ($this->libraryServices as $library) {
            $name = $library->getName();
            if (1 !== preg_match(Example::SLUG_PATTERN, $name)) {
                throw new ShowcaseException(\sprintf('The library name "%s" is not valid: lowercase letters, digits and single dashes only.', $name));
            }
            if (isset($services[$name])) {
                throw new ShowcaseException(\sprintf('The library "%s" is registered twice.', $name));
            }
            $services[$name] = $library;
        }
        // The order of tagged services depends on the container: libraries are listed by name.
        ksort($services);

        $data = $this->cached($services);
        $this->examples = $data['examples'];
        $this->docPages = $data['docPages'];

        $loaded = [];
        foreach ($services as $name => $library) {
            $loaded[$name] = ['library' => $library, 'components' => $data['components'][$name]];
        }

        return $this->libraries = $loaded;
    }

    /**
     * The expensive part (scanning the components, parsing the example files) is kept in the cache pool outside debug;
     * in debug it is always rebuilt, so an edited example shows at once. `cache:clear` empties it.
     *
     * @param array<string, ComponentLibraryInterface> $services
     *
     * @return Data
     */
    private function cached(array $services): array
    {
        if (null === $this->cache || $this->debug) {
            return $this->build($services);
        }

        $key = 'registry_'.hash('xxh3', implode("\0", array_map(static fn (ComponentLibraryInterface $library): string => $library::class.':'.$library->getName(), $services)));
        $item = $this->cache->getItem($key);
        $data = $item->get();
        if ($item->isHit() && \is_array($data)) {
            /** @var Data $data */
            return $data;
        }

        $data = $this->build($services);
        $this->cache->save($item->set($data));

        return $data;
    }

    /**
     * @param array<string, ComponentLibraryInterface> $services
     *
     * @return Data
     */
    private function build(array $services): array
    {
        $this->examples = $this->docPages = [];
        $components = [];
        foreach ($services as $name => $library) {
            $components[$name] = [];
            foreach ($library->getComponents() as $reference) {
                if (isset($components[$name][$reference->name])) {
                    throw new ShowcaseException(\sprintf('The component "%s" is listed twice by the library "%s".', $reference->name, $name));
                }
                $components[$name][$reference->name] = $reference;
            }
        }

        foreach ($services as $name => $library) {
            foreach ($library->getExampleProviders() as $provider) {
                foreach ($provider->getExamples() as $example) {
                    if (null !== $example->component && !isset($components[$name][$example->component])) {
                        throw UnknownComponentException::forExample($example->slug, $example->component, $name);
                    }
                    $this->add($name, $example);
                }
            }
        }

        foreach ($this->sharedExampleProviders as $provider) {
            foreach ($provider->getExamples() as $example) {
                if (null === $example->component) {
                    throw new ShowcaseException(\sprintf('The example "%s" has no component and comes from a shared provider: a documentation page belongs to one library, declare it in its provider.', $example->slug));
                }
                $listed = false;
                foreach ($components as $name => $references) {
                    if (isset($references[$example->component])) {
                        $this->add($name, $example);
                        $listed = true;
                    }
                }
                if (!$listed) {
                    throw UnknownComponentException::forExample($example->slug, $example->component, null);
                }
            }
        }

        return ['components' => $components, 'examples' => $this->examples, 'docPages' => $this->docPages];
    }

    private function add(string $library, Example $example): void
    {
        $siblings = null === $example->component ? $this->docPages[$library] ?? [] : $this->examples[$library][$example->component] ?? [];
        foreach ($siblings as $sibling) {
            if ($sibling->slug === $example->slug) {
                throw DuplicateExampleException::create($library, $example->component, $example->slug);
            }
        }

        if (null === $example->component) {
            $this->docPages[$library][] = $example;
        } else {
            $this->examples[$library][$example->component][] = $example;
        }
    }
}

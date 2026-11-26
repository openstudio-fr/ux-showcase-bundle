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

namespace OpenStudio\UxShowcaseBundle\Tests\Functional;

use OpenStudio\UxShowcaseBundle\Library\ComponentCatalog;
use OpenStudio\UxShowcaseBundle\ShowcaseRegistry;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\CatalogLibrary\CatalogLibrary;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Configuration;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * A host library written in PHP reuses the component catalog and the Twig example files.
 */
final class CatalogLibraryTest extends TestCase
{
    private ?TestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testComponentsCarryTheGroupAndLabelOfTheLibrary(): void
    {
        $registry = $this->registry();

        $components = [];
        foreach ($registry->components('native') as $reference) {
            $components[$reference->name] = [$reference->group, $reference->label];
        }

        self::assertSame([
            'Native:Badge' => ['General', 'Badge'],
            'Native:Button:Base' => ['General', 'Button'],
            'Native:Form:Input' => ['Forms', 'Input'],
        ], $components);
        self::assertSame(['General', 'Forms'], array_map(static fn ($group): string => $group->name, $registry->groups('native')));
    }

    public function testTheTwigExampleFilesAreFound(): void
    {
        $registry = $this->registry();

        foreach (['Native:Badge', 'Native:Button:Base', 'Native:Form:Input'] as $component) {
            self::assertSame(['default'], array_map(static fn ($example): string => $example->slug, $registry->examples('native', $component)), $component);
        }
    }

    private function registry(): ShowcaseRegistry
    {
        $this->kernel = Configuration::kernel();
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        \assert($container instanceof ContainerInterface);
        $catalog = $container->get(ComponentCatalog::class);
        \assert($catalog instanceof ComponentCatalog);

        return new ShowcaseRegistry([new CatalogLibrary($catalog)], []);
    }
}

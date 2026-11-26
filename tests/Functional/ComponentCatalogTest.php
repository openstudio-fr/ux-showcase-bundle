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
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Component\Card as CardComponent;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Component\Counter;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Configuration;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ComponentCatalogTest extends TestCase
{
    private ?TestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testClassComponentsAndAnonymousComponentsAreBothDiscovered(): void
    {
        $names = $this->catalog()->names();

        self::assertContains('Demo:Button', $names, 'anonymous');
        self::assertContains('Demo:Layout:Card', $names, 'class component');
        self::assertContains('Demo:Form:Counter', $names, 'live component');
    }

    public function testAnAnonymousComponentHasNoClass(): void
    {
        self::assertNull($this->catalog()->className('Demo:Button'));
    }

    public function testAClassComponentGivesItsClass(): void
    {
        self::assertSame(CardComponent::class, $this->catalog()->className('Demo:Layout:Card'));
        self::assertSame(Counter::class, $this->catalog()->className('Demo:Form:Counter'));
    }

    public function testExists(): void
    {
        self::assertTrue($this->catalog()->exists('Demo:Button'));
        self::assertFalse($this->catalog()->exists('Demo:Nope'));
    }

    private function catalog(): ComponentCatalog
    {
        $this->kernel = Configuration::kernel();
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        \assert($container instanceof ContainerInterface);
        $catalog = $container->get(ComponentCatalog::class);
        \assert($catalog instanceof ComponentCatalog);

        return $catalog;
    }
}

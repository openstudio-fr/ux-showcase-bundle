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

namespace OpenStudio\UxShowcaseBundle\Tests;

use OpenStudio\UxShowcaseBundle\ShowcaseConfiguration;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $configuration = $this->configuration([]);

        self::assertSame('Components', $configuration->title);
        self::assertNull($configuration->logo);
        self::assertSame(['responsive' => null, 'mobile' => 375, 'tablet' => 768, 'desktop' => 1280], $configuration->viewports);
        self::assertSame(['light', 'white', 'dark'], array_keys($configuration->backgrounds));
    }

    public function testViewportsAndBackgroundsReplaceTheDefaultsAndKeepTheirOrder(): void
    {
        $configuration = $this->configuration([
            'title' => 'Kit',
            'logo' => '/logo.svg',
            'viewports' => ['wide' => 1600, 'fluid' => null, 'small' => 320],
            'backgrounds' => ['sand' => '#eee'],
        ]);

        self::assertSame('Kit', $configuration->title);
        self::assertSame('/logo.svg', $configuration->logo);
        self::assertSame(['wide' => 1600, 'fluid' => null, 'small' => 320], $configuration->viewports);
        self::assertSame(['sand'], array_keys($configuration->backgrounds));
        self::assertSame('#eee', $configuration->backgrounds['sand']->color);
        self::assertSame([], $configuration->backgrounds['sand']->htmlAttributes);
    }

    public function testABackgroundIsAColourOrAColourWithHtmlAttributes(): void
    {
        $configuration = $this->configuration(['backgrounds' => [
            'sand' => '#eee',
            'night' => ['color' => '#212529', 'html_attributes' => ['data-bs-theme' => 'dark']],
            'bare' => ['color' => '#fff'],
        ]]);

        self::assertSame(['sand', 'night', 'bare'], array_keys($configuration->backgrounds));
        self::assertSame(['data-bs-theme' => 'dark'], $configuration->backgrounds['night']->htmlAttributes);
        self::assertSame('#212529', $configuration->backgrounds['night']->color);
        self::assertSame([], $configuration->backgrounds['bare']->htmlAttributes);
    }

    public function testDefaultBackgroundsKeepTheirColoursAndOrder(): void
    {
        $backgrounds = $this->configuration([])->backgrounds;

        self::assertSame(['light' => '#f8f9fa', 'white' => '#ffffff', 'dark' => '#212529'], array_map(static fn ($background): string => $background->color, $backgrounds));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function configuration(array $values): ShowcaseConfiguration
    {
        $kernel = new TestKernel(true, $values);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $configuration = $container->get(ShowcaseConfiguration::class);
        $kernel->shutdown();
        self::assertInstanceOf(ShowcaseConfiguration::class, $configuration);

        return $configuration;
    }
}

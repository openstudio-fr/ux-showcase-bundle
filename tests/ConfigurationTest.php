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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $configuration = $this->configuration([]);

        self::assertSame('Components', $configuration->title);
        self::assertNull($configuration->logo);
        self::assertNull($configuration->primaryColor);
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

    public function testTheViewportsOfALaterConfigurationReplaceTheEarlierOnesInsteadOfMergingWithThem(): void
    {
        $kernel = new TestKernel(true, [], extraConfigurations: [
            ['viewports' => ['theme-wide' => 1600, 'theme-small' => 320]],
            ['viewports' => ['app' => 900]],
        ]);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $configuration = $container->get(ShowcaseConfiguration::class);
        $kernel->shutdown();

        self::assertInstanceOf(ShowcaseConfiguration::class, $configuration);
        self::assertSame(['app' => 900], $configuration->viewports);
    }

    public function testTheBackgroundsOfALaterConfigurationReplaceTheEarlierOnesInsteadOfMergingWithThem(): void
    {
        $kernel = new TestKernel(true, [], extraConfigurations: [
            ['backgrounds' => ['theme-dark' => ['color' => '#212529', 'html_attributes' => ['data-bs-theme' => 'dark']], 'theme-light' => '#fff']],
            ['backgrounds' => ['app' => '#eee']],
        ]);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $configuration = $container->get(ShowcaseConfiguration::class);
        $kernel->shutdown();

        self::assertInstanceOf(ShowcaseConfiguration::class, $configuration);
        self::assertSame(['app'], array_keys($configuration->backgrounds));
    }

    public function testAnEmptyListOfBackgroundsStaysEmptyEvenAfterAThemeDeclaredSome(): void
    {
        self::assertSame([], $this->configuration(['backgrounds' => []])->backgrounds);
        self::assertNotSame([], $this->configuration([])->backgrounds, 'an absent key keeps the default backgrounds');

        $kernel = new TestKernel(true, [], extraConfigurations: [
            ['backgrounds' => ['theme-dark' => '#212529']],
            ['backgrounds' => []],
        ]);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $configuration = $container->get(ShowcaseConfiguration::class);
        $kernel->shutdown();

        self::assertInstanceOf(ShowcaseConfiguration::class, $configuration);
        self::assertSame([], $configuration->backgrounds);
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

    /**
     * @return iterable<string, array{string}>
     */
    public static function safeColours(): iterable
    {
        yield 'hex' => ['#7b2cbf'];
        yield 'short hex' => ['#fff'];
        yield 'name' => ['rebeccapurple'];
        yield 'rgb' => ['rgb(10, 20, 30)'];
    }

    #[DataProvider('safeColours')]
    public function testThePrimaryColourAcceptsASafeCssColour(string $colour): void
    {
        self::assertSame($colour, $this->configuration(['theme' => ['primary_color' => $colour]])->primaryColor);
    }

    public function testAPercentageInThePrimaryColourIsWrittenAsAnEscapedPercentSign(): void
    {
        self::assertSame('hsla(120, 50%, 40%, 0.5)', $this->configuration(['theme' => ['primary_color' => 'hsla(120, 50%%, 40%%, 0.5)']])->primaryColor);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeColours(): iterable
    {
        yield 'attribute injection' => ['red" onload="alert(1)'];
        yield 'declaration injection' => ['red; background: url(//evil.example/x)'];
        yield 'url' => ['url(//evil.example/x)'];
        yield 'tag' => ['<script>'];
        yield 'bad hex' => ['#12'];
        yield 'empty' => [''];
    }

    #[DataProvider('unsafeColours')]
    public function testThePrimaryColourRefusesAnythingThatIsNotASafeCssColour(string $colour): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->configuration(['theme' => ['primary_color' => $colour]]);
    }

    public function testThePageBackgroundIsNoneByDefaultAndAcceptsASafeCssColour(): void
    {
        self::assertNull($this->configuration([])->pageBackground);
        self::assertSame('#fff5f1', $this->configuration(['theme' => ['page_background' => '#fff5f1']])->pageBackground);
    }

    #[DataProvider('unsafeColours')]
    public function testThePageBackgroundRefusesAnythingThatIsNotASafeCssColour(string $colour): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->configuration(['theme' => ['page_background' => $colour]]);
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

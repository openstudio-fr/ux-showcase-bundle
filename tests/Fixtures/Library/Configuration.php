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

namespace OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library;

use OpenStudio\UxShowcaseBundle\Tests\Fixtures\TestKernel;

/**
 * What a project writes under ux_showcase to get the test library, as the kernel boots it.
 */
final class Configuration
{
    /**
     * @param array<string, mixed> $library overrides of ux_showcase.libraries.demo
     *
     * @return array<string, mixed>
     */
    public static function demo(array $library = []): array
    {
        return [
            'fixtures_directory' => __DIR__.'/fixtures',
            'libraries' => [
                'demo' => $library + [
                    'label' => 'Demo kit',
                    'prefix' => 'Demo',
                    'examples' => __DIR__.'/examples',
                    'groups' => ['General', 'Form', 'Layout', 'Foundations'],
                    'wrapper' => 'showcase/wrapper.html.twig',
                    'bare' => 'showcase/bare.html.twig',
                    'statuses' => ['Demo:Experimental:Chart' => 'waiting', 'Demo:Legacy:Old' => 'hidden'],
                ],
            ],
        ];
    }

    /**
     * The test library and a second one with the `Props:` components, with examples of every kind of prop (object, list,
     * enum, date, union) to try.
     *
     * @return array<string, mixed>
     */
    public static function props(): array
    {
        $demo = self::demo();
        /** @var array<string, array<string, mixed>> $libraries */
        $libraries = $demo['libraries'];

        return ['libraries' => $libraries + ['props' => [
            'label' => 'Props',
            'prefix' => 'Props',
            'examples' => __DIR__.'/examples_props',
            'bare' => 'showcase/bare.html.twig',
        ]]] + $demo;
    }

    /**
     * @param array<string, mixed> $configuration the test library when empty
     */
    public static function kernel(bool $debug = true, array $configuration = []): TestKernel
    {
        return new TestKernel($debug, $configuration ?: self::demo(), withLibrary: true);
    }
}

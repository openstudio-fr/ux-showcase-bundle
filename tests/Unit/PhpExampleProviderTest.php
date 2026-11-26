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

namespace OpenStudio\UxShowcaseBundle\Tests\Unit;

use OpenStudio\UxShowcaseBundle\Example\PhpExampleProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class PhpExampleProviderTest extends TestCase
{
    public function testAnInvalidPreviewOptionIsReportedWithTheNameOfTheExample(): void
    {
        $source = new class {
            /** @return array<string, mixed> */
            public function __invoke(): array
            {
                return [];
            }
        };
        $provider = new PhpExampleProvider(new ServiceLocator(['example' => static fn (): object => $source]), [[
            'service' => 'example',
            'method' => '__invoke',
            'component' => 'Demo:Button',
            'slug' => 'tall',
            'title' => null,
            'content' => null,
            'background' => null,
            'body_class' => null,
            'wrapper' => null,
            'height' => 0,
            'needs_data' => null,
        ]]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The example "tall" of the component "Demo:Button": The preview height is a positive number of pixels.');

        iterator_to_array($provider->getExamples());
    }
}

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

use OpenStudio\UxShowcaseBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TranslationTest extends TestCase
{
    #[DataProvider('translations')]
    public function testScreenIsTranslated(string $locale, string $expected): void
    {
        $kernel = new TestKernel(true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $translator = $container->get('translator');
        self::assertInstanceOf(TranslatorInterface::class, $translator);

        $translated = $translator->trans('index.empty', [], 'UxShowcaseBundle', $locale);
        $kernel->shutdown();

        self::assertSame($expected, $translated);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function translations(): iterable
    {
        yield 'english' => ['en', 'No component library is registered yet.'];
        yield 'french' => ['fr', "Aucune bibliothèque de composants n'est enregistrée pour l'instant."];
    }
}

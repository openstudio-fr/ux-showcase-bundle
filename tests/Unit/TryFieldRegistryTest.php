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

use OpenStudio\UxShowcaseBundle\Props\PropDoc;
use OpenStudio\UxShowcaseBundle\Screen\TryFieldRegistry;
use OpenStudio\UxShowcaseBundle\Screen\TryFieldTypeInterface;
use PHPUnit\Framework\TestCase;

final class TryFieldRegistryTest extends TestCase
{
    public function testTheFirstTypeThatSupportsThePropDrawsIt(): void
    {
        $registry = new TryFieldRegistry([self::type('image', 'picture'), self::type('text', null)]);

        self::assertSame('image', $registry->resolve(PropDoc::create('picture', 'string'))?->getName());
        self::assertSame('text', $registry->resolve(PropDoc::create('title', 'string'))?->getName());
    }

    public function testAPropNoTypeSupportsHasNoField(): void
    {
        self::assertNull((new TryFieldRegistry([self::type('image', 'picture')]))->resolve(PropDoc::create('title', 'string')));
    }

    public function testTwoTypesWithTheSameNameAreRefused(): void
    {
        $registry = new TryFieldRegistry([self::type('image', 'a'), self::type('image', 'b')]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Two try field types are named "image"');
        $registry->resolve(PropDoc::create('a', 'string'));
    }

    private static function type(string $name, ?string $prop): TryFieldTypeInterface
    {
        return new class($name, $prop) implements TryFieldTypeInterface {
            public function __construct(private readonly string $name, private readonly ?string $prop)
            {
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function supports(PropDoc $prop): bool
            {
                return null === $this->prop || $prop->name === $this->prop;
            }

            public function getTemplate(): string
            {
                return 'x.html.twig';
            }

            public function getModuleUrl(): ?string
            {
                return null;
            }
        };
    }
}

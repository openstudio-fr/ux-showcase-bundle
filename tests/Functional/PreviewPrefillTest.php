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

use OpenStudio\UxShowcaseBundle\Example\Example;
use OpenStudio\UxShowcaseBundle\Preview\PreviewPrefill;
use OpenStudio\UxShowcaseBundle\Props\PropDoc;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Configuration;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\Align;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\Layout;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\VehicleCard;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\TestCase;

final class PreviewPrefillTest extends TestCase
{
    private ?TestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testObjectsEnumsAndDatesBecomeJsonSafeValues(): void
    {
        $values = $this->prefill()->normalize([
            'vehicle' => new VehicleCard('Golf', 12.5, ['new'], Layout::Rows, new \DateTimeImmutable('2026-01-02T10:30:00+00:00')),
            'align' => Align::Left,
            'layout' => Layout::Grid,
            'at' => new \DateTime('2026-03-04T05:06:07+01:00'),
            'rows' => [new VehicleCard('Polo')],
            'plain' => ['a' => 1, 'b' => null, 'c' => true],
        ]);

        self::assertSame([
            'vehicle' => ['name' => 'Golf', 'price' => 12.5, 'tags' => ['new'], 'layout' => 'rows', 'since' => '2026-01-02T10:30:00+00:00'],
            'align' => 'Left',
            'layout' => 'grid',
            'at' => '2026-03-04T05:06:07+01:00',
            'rows' => [['name' => 'Polo', 'price' => 0.0, 'tags' => [], 'layout' => 'grid', 'since' => null]],
            'plain' => ['a' => 1, 'b' => null, 'c' => true],
        ], $values);
        self::assertSame($values, json_decode(json_encode($values, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION), true), 'what JSON gives back is the same');
    }

    public function testOnlyThePropsThatCanBePostedBackAreGiven(): void
    {
        $example = new Example('Props:Listing', 'try', props: ['title' => 'T', 'payload' => 'x', 'onclick' => 'alert(1)', 'limit' => 3]);

        self::assertSame(['title' => 'T', 'limit' => 3], $this->prefill()->forExample($example));
    }

    public function testAFreeExampleHasNothingToFill(): void
    {
        self::assertSame([], $this->prefill()->forExample(new Example(null, 'colours', source: '<p>x</p>', group: 'Foundations')));
    }

    public function testWhichPropsAreEditable(): void
    {
        self::assertTrue($this->prefill()->isEditable(PropDoc::create('vehicle', VehicleCard::class)));
        self::assertFalse($this->prefill()->isEditable(PropDoc::create('payload', 'mixed')));
    }

    private function prefill(): PreviewPrefill
    {
        $this->kernel = Configuration::kernel(true, Configuration::props());
        $this->kernel->boot();
        $prefill = $this->kernel->getContainer()->get('test.'.PreviewPrefill::class);
        self::assertInstanceOf(PreviewPrefill::class, $prefill);

        return $prefill;
    }
}

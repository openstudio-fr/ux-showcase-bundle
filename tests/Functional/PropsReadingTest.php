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

use OpenStudio\UxShowcaseBundle\Exception\UnknownComponentException;
use OpenStudio\UxShowcaseBundle\Props\BlockDoc;
use OpenStudio\UxShowcaseBundle\Props\ComponentDoc;
use OpenStudio\UxShowcaseBundle\Props\ComponentDocumentation;
use OpenStudio\UxShowcaseBundle\Props\PropDoc;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Configuration;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class PropsReadingTest extends TestCase
{
    private const DTO = 'OpenStudio\\UxShowcaseBundle\\Tests\\Fixtures\\Library\\Dto\\';

    private ?TestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testAnAnonymousComponentDocumentedWithDoubleHash(): void
    {
        $tag = [PropDoc::SOURCE_PROPS_TAG, PropDoc::SOURCE_DOC_COMMENT];

        self::assertEquals(new ComponentDoc('Props:Documented', '', [
            PropDoc::create('label', 'string', false, null, 'Text of the button', null, $tag),
            PropDoc::create('hint', '?string', true, null, 'Smaller text under the label', null, $tag),
            PropDoc::create('variant', 'string', true, 'primary', 'Visual weight, `primary` or `danger`', null, $tag),
            PropDoc::create('tags', 'list<string>', true, ['new', 'sale'], 'Tags shown as badges, one per line', null, $tag),
            PropDoc::create('stock', 'array<string, int>', true, [], 'Quantity by size', null, $tag),
            PropDoc::create('offset', 'int', true, -2, '', null, $tag),
            PropDoc::create('year', 'mixed', true, null, '', null, [PropDoc::SOURCE_PROPS_TAG]),
            PropDoc::create('plain', 'mixed', false, null, '', null, [PropDoc::SOURCE_PROPS_TAG]),
        ]), $this->documentation()->get('Props:Documented'));
    }

    public function testAnAnonymousComponentDocumentedWithAnnotations(): void
    {
        $annotated = [PropDoc::SOURCE_PROPS_TAG, PropDoc::SOURCE_ANNOTATION];

        self::assertEquals(new ComponentDoc(
            'Props:Annotated',
            'A table of rows, with a header and a footer.',
            [
                PropDoc::create('caption', 'string', false, null, 'Text above the table', null, $annotated),
                PropDoc::create('count', '?int', true, 10, 'How many rows to show', null, $annotated),
                PropDoc::create('rows', 'list<array{name: string, id: int}>', true, [], 'The rows of the table', null, $annotated, [
                    PropDoc::create('name', 'string', false, null, 'Name shown in the first column', null, [PropDoc::SOURCE_ANNOTATION]),
                    PropDoc::create('id', 'int', false, null, 'Identifier of the row', null, [PropDoc::SOURCE_ANNOTATION]),
                ]),
                PropDoc::create('other', 'mixed', true, true, '', null, [PropDoc::SOURCE_PROPS_TAG]),
            ],
            [new BlockDoc('header', 'Content above the rows'), new BlockDoc('footer')],
        ), $this->documentation()->get('Props:Annotated'));
    }

    public function testAnAnonymousComponentWithoutComments(): void
    {
        self::assertEquals(new ComponentDoc('Demo:Button', '', [
            PropDoc::create('label', 'mixed', false, null, '', null, [PropDoc::SOURCE_PROPS_TAG]),
            PropDoc::create('variant', 'mixed', true, 'primary', '', null, [PropDoc::SOURCE_PROPS_TAG]),
        ], [new BlockDoc('content')]), $this->documentation()->get('Demo:Button'));
    }

    public function testAClassComponentWithMountAPreMountAndATemplate(): void
    {
        $template = [PropDoc::SOURCE_PROPS_TAG, PropDoc::SOURCE_DOC_COMMENT];

        self::assertEquals(new ComponentDoc('Props:Alert', 'A box that shows a message.', [
            // Description from the template (first reader), type from the class (last reader that knows it): html then string.
            PropDoc::create('message', 'string', false, null, 'The message, as markup', null, [...$template, PropDoc::SOURCE_PROPERTY]),
            PropDoc::create('type', 'string', true, 'info', 'Colour of the box', ['info', 'warning', 'error'], [...$template, PropDoc::SOURCE_PROPERTY, PropDoc::SOURCE_PRE_MOUNT]),
            PropDoc::create('icon', '?string', true, null, '', null, [PropDoc::SOURCE_PROPERTY]),
            PropDoc::create('heading', 'string', false, null, 'Title of the box', null, [PropDoc::SOURCE_MOUNT]),
            PropDoc::create('closable', 'bool', true, true, 'shows a close button', null, [PropDoc::SOURCE_MOUNT]),
            PropDoc::create('related', '?list<'.self::DTO.'VehicleDto>', true, null, 'Related vehicles', null, [PropDoc::SOURCE_MOUNT]),
        ]), $this->documentation()->get('Props:Alert'));
    }

    public function testAClassComponentWithAListOfObjectsAndEnums(): void
    {
        $property = [PropDoc::SOURCE_PROPERTY];

        self::assertEquals(new ComponentDoc('Props:Gallery', '', [
            PropDoc::create('vehicles', 'list<'.self::DTO.'VehicleDto>', true, [], '', null, $property),
            PropDoc::create('layout', self::DTO.'Layout', true, \OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\Layout::Grid, '', ['grid', 'rows'], $property),
            PropDoc::create('align', '?'.self::DTO.'Align', true, null, '', ['Left', 'Right'], $property),
            PropDoc::create('limit', 'int|string', true, 12, '', null, $property),
            PropDoc::create('named', '?array<string, '.self::DTO.'VehicleDto>', false, null, '', null, $property),
        ]), $this->documentation()->get('Props:Gallery'));
    }

    public function testALiveComponentKeepsItsOwnPropsAndNotTheStateOfItsTraits(): void
    {
        $property = [PropDoc::SOURCE_PROPERTY];

        self::assertEquals(new ComponentDoc('Props:Rating', '', [
            PropDoc::create('value', 'int', true, 0, '', null, $property),
            PropDoc::create('comment', '?string', true, null, '', null, $property),
            PropDoc::create('max', 'int', true, 5, '', null, $property),
        ]), $this->documentation()->get('Props:Rating'));
    }

    public function testAClassWithoutAnyDocumentationGivesATableFromReflectionAlone(): void
    {
        $doc = $this->documentation()->get('Demo:Layout:Card');

        self::assertSame(['title', 'items'], array_map(static fn (PropDoc $prop): string => $prop->name, $doc->props));
        self::assertSame('string', $doc->prop('title')?->type);
        self::assertSame("list<array{'label': string, 'meta': array{'price': float}}>", $doc->prop('items')?->type);
        self::assertFalse($doc->prop('items')->required);
    }

    public function testAReaderOfTheApplicationJoinsTheChain(): void
    {
        $doc = $this->documentation()->get('Demo:Form:Input');

        self::assertSame('A text field.', $doc->description);
        // The template knows the prop but not its type nor its meaning: the custom reader adds both.
        self::assertEquals(
            PropDoc::create('name', 'string', false, null, 'Name of the field', null, [PropDoc::SOURCE_PROPS_TAG, 'custom']),
            $doc->prop('name'),
        );
        self::assertSame(['name', 'value', 'disabled', 'extra'], array_map(static fn (PropDoc $prop): string => $prop->name, $doc->props));
    }

    public function testAnUnknownComponentIsRefused(): void
    {
        $this->expectException(UnknownComponentException::class);
        $this->expectExceptionMessage('The component "Nope:Nothing" is not a TwigComponent of this application.');

        $this->documentation()->get('Nope:Nothing');
    }

    private function documentation(): ComponentDocumentation
    {
        $this->kernel = Configuration::kernel();
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        \assert($container instanceof ContainerInterface);
        $documentation = $container->get(ComponentDocumentation::class);
        \assert($documentation instanceof ComponentDocumentation);

        return $documentation;
    }
}

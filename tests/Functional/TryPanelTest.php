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

use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Configuration;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

final class TryPanelTest extends TestCase
{
    private ?TestKernel $kernel = null;
    private ?KernelBrowser $client = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        $this->client = null;
    }

    public function testEachPropGetsTheFieldOfItsTypeFilledWithTheExample(): void
    {
        $crawler = $this->page('Props:Tryout');

        $label = $this->field($crawler, 'label', 'text');
        $text = $label->filterXPath('.//input[@type="text"]');
        self::assertSame('Hello', $text->attr('value'));
        self::assertSame($text->attr('id'), $label->filterXPath('.//label')->attr('for'), 'the label points at the control');

        $enabled = $this->field($crawler, 'enabled', 'checkbox');
        self::assertNotNull($enabled->filterXPath('.//input[@type="checkbox"]')->attr('checked'));
        self::assertSame('0', $enabled->filterXPath('.//input[@type="hidden"]')->attr('value'), 'an unticked box posts false');

        $count = $this->field($crawler, 'count', 'number')->filterXPath('.//input[@type="number"]');
        self::assertSame(['3', '1'], [$count->attr('value'), $count->attr('step')]);
        $ratio = $this->field($crawler, 'ratio', 'number')->filterXPath('.//input[@type="number"]');
        self::assertSame(['0.5', 'any'], [$ratio->attr('value'), $ratio->attr('step')]);

        $size = $this->field($crawler, 'size', 'select');
        self::assertSame(['sm', 'md', 'lg'], $size->filterXPath('.//option')->each(static fn (Crawler $option): string => (string) $option->attr('value')), 'the allowed values');
        self::assertSame('lg', $size->filterXPath('.//option[@selected]')->attr('value'));

        $layout = $this->field($crawler, 'layout', 'select');
        self::assertSame(['', 'grid', 'rows'], $layout->filterXPath('.//option')->each(static fn (Crawler $option): string => (string) $option->attr('value')), 'the cases of the enum, and none for a nullable prop');

        $tags = $this->field($crawler, 'tags', 'json')->filterXPath('.//textarea');
        self::assertSame("[\n    \"a\",\n    \"b\"\n]", $tags->getNode(0)?->textContent);
    }

    public function testFieldsAreLabelledAndDescribedForAssistiveTechnology(): void
    {
        $crawler = $this->page('Props:Tryout');
        $control = $this->field($crawler, 'label', 'text')->filterXPath('.//input[@type="text"]');

        $describedBy = explode(' ', (string) $control->attr('aria-describedby'));
        self::assertCount(2, $describedBy, 'the hint, then the error');
        foreach ($describedBy as $id) {
            self::assertCount(1, $crawler->filterXPath(\sprintf('//*[@id="%s"]', $id)), $id);
        }
        self::assertSame('Text shown in the box.', trim($crawler->filterXPath(\sprintf('//*[@id="%s"]', $describedBy[0]))->text()));
        self::assertNotNull($crawler->filterXPath(\sprintf('//*[@id="%s"]', $describedBy[1]))->attr('hidden'), 'no error until there is one');

        $ids = $crawler->filterXPath('//*[@id]')->each(static fn (Crawler $node): string => (string) $node->attr('id'));
        self::assertSame($ids, array_values(array_unique($ids)), 'ids are unique');
    }

    public function testAPropTheExampleDoesNotSetShowsItsDefaultAndIsNotSentUntilChanged(): void
    {
        $crawler = $this->page('Props:Tryout');

        self::assertSame('3', $this->field($crawler, 'count', 'number')->filterXPath('.//input')->attr('value'));
        self::assertNull($this->field($crawler, 'count', 'number')->attr('data-sent'));
        self::assertNotNull($this->field($crawler, 'label', 'text')->attr('data-sent'));
    }

    public function testAPropThatCannotBeWrittenInAFormIsReadOnlyWithTheReason(): void
    {
        $crawler = $this->page('Props:Tryout');

        $readOnly = $crawler->filterXPath('//*[@id="example-default"]//*[contains(@class, "showcase-try-readonly")]');
        self::assertCount(1, $readOnly);
        self::assertStringContainsString('handler', $readOnly->text());
        self::assertStringContainsString('its type is ?object', $readOnly->text());
        self::assertCount(0, $crawler->filterXPath('//*[@id="example-default"]//*[@name="handler"]'), 'no control');

        $listing = $this->page('Props:Listing', 'props');
        $listingReadOnly = $listing->filterXPath('//*[contains(@class, "showcase-try-readonly")]')->first();
        self::assertStringContainsString('payload', $listingReadOnly->text());
        self::assertStringContainsString('not declared', $listingReadOnly->text());
    }

    public function testAFreeExampleHasNoPanel(): void
    {
        $crawler = $this->page('Props:Tryout');

        self::assertCount(1, $crawler->filterXPath('//*[@id="example-default"]//*[contains(concat(" ", @class, " "), " showcase-try ")]'));
        self::assertCount(1, $crawler->filterXPath('//*[@id="example-free"]'));
        self::assertCount(0, $crawler->filterXPath('//*[@id="example-free"]//*[contains(concat(" ", @class, " "), " showcase-try ")]'));
        self::assertCount(0, $crawler->filterXPath('//*[@id="example-free"]//iframe[@name]'), 'a free example frame is not a target');
    }

    public function testWithoutJavaScriptTheFormPostsTheSignedAddressWithItsTokenToTheFrame(): void
    {
        $crawler = $this->page('Props:Tryout');
        $form = $crawler->filterXPath('//form[@id="example-default-try-form"]');

        self::assertSame('post', $form->attr('method'));
        self::assertMatchesRegularExpression('#^/_showcase/props/Props:Tryout/default/preview\?locale=en&expires=\d+&signature=[a-f0-9]{64}$#', (string) $form->attr('action'));
        self::assertSame('example-default-frame', $form->attr('target'));
        self::assertSame('example-default-frame', $crawler->filterXPath('//*[@id="example-default"]//iframe')->attr('name'));
        self::assertSame($form->attr('data-token'), $form->filterXPath('.//input[@name="_token"]')->attr('value'));
        self::assertSame('Show', trim($form->filterXPath('.//button[@type="submit"]')->text()));
        self::assertNull($form->filterXPath('.//button[@type="submit"]')->attr('hidden'), 'visible: only the script hides it');
    }

    public function testTheFormAsItIsPostedDrawsTheExample(): void
    {
        $crawler = $this->page('Props:Tryout');
        $form = $crawler->filterXPath('//form[@id="example-default-try-form"]')->form();

        $this->client()->submit($form, ['label' => 'From the form', 'count' => '9', 'size' => 'sm', 'layout' => 'rows', 'tags' => '["x","y"]'], ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertSame(200, $this->client()->getResponse()->getStatusCode(), (string) $this->client()->getResponse()->getContent());
        self::assertStringContainsString('From the form on 9 0.5 sm rows', (string) $this->client()->getResponse()->getContent());
        self::assertStringContainsString('[x,y]', (string) $this->client()->getResponse()->getContent());
    }

    public function testAnUntickedBoxPostsFalse(): void
    {
        $form = $this->page('Props:Tryout')->filterXPath('//form[@id="example-default-try-form"]')->form();
        // What a browser posts for an unticked box: the hidden field that comes before it, same name.
        $values = ['enabled' => '0'] + $form->getPhpValues();

        $this->client()->request('POST', $form->getUri(), $values, [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded', 'HTTP_ORIGIN' => 'http://localhost']);

        self::assertStringContainsString('Hello off 3', (string) $this->client()->getResponse()->getContent());
    }

    public function testAJsonFieldOfTheFormThatIsNotJsonIsRefusedAtThatField(): void
    {
        $form = $this->page('Props:Tryout')->filterXPath('//form[@id="example-default-try-form"]')->form();

        $this->client()->submit($form, ['tags' => '[nope'], ['HTTP_ORIGIN' => 'http://localhost', 'HTTP_ACCEPT' => 'application/json']);

        self::assertSame(422, $this->client()->getResponse()->getStatusCode());
        $body = json_decode((string) $this->client()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertIsArray($body['errors']);
        $error = $body['errors'][0];
        self::assertIsArray($error);
        self::assertSame('tags', $error['path']);
        self::assertIsString($error['message']);
        self::assertStringStartsWith('Not valid JSON', $error['message']);
    }

    public function testTheJsonOfAnObjectInTheFormKeepsTheTypesOfJson(): void
    {
        $form = $this->page('Props:Listing', 'props')->filterXPath('//form[@id="example-default-try-form"]')->form();

        $this->client()->submit($form, ['vehicle' => '{"name":"Beetle","price":3,"tags":[]}'], ['HTTP_ORIGIN' => 'http://localhost']);

        self::assertSame(200, $this->client()->getResponse()->getStatusCode(), (string) $this->client()->getResponse()->getContent());
        self::assertStringContainsString('Beetle 3.00', (string) $this->client()->getResponse()->getContent());
    }

    public function testAFieldTypeOfTheHostIsRenderedForItsPropAndItsModuleIsListed(): void
    {
        $crawler = $this->page('Props:Tryout');

        $field = $this->field($crawler, 'picture', 'image');
        self::assertSame('/assets/image-field.js', $field->attr('data-module'));
        self::assertCount(1, $field->filterXPath('.//button[@id="example-default-try-picture"][@data-try-control][contains(@class, "host-image")]'));
        self::assertSame('picture', $field->filterXPath('.//input[@type="hidden"]')->attr('name'), 'the field posts under the name of the prop');
        self::assertCount(0, $crawler->filterXPath('//*[@data-prop="picture"][@data-field-type="text"]'), 'it wins over the built-in text field');
    }

    public function testAModuleIsOnlyListedByThePagesWithAFieldThatNeedsIt(): void
    {
        self::assertCount(0, $this->page('Props:Listing', 'props')->filterXPath('//*[@data-module]'));
        self::assertCount(1, $this->page('Props:Tryout')->filterXPath('//*[@data-module]'));
    }

    public function testTheScriptOfTheScreensCarriesTheFieldApi(): void
    {
        $this->client()->request('GET', '/_showcase/_assets/showcase.js');
        $script = (string) $this->client()->getResponse()->getContent();

        foreach (['field.handle = function', 'field.getValue = function', 'field.setValue = function', 'field.notify = function', 'X-CSRF-Token', 'DEBOUNCE_MS = 400'] as $needle) {
            self::assertStringContainsString($needle, $script);
        }
    }

    private function field(Crawler $crawler, string $prop, string $type): Crawler
    {
        $field = $crawler->filterXPath(\sprintf('//*[@id="example-default"]//*[contains(@class, "showcase-try-field")][@data-prop="%s"][@data-field-type="%s"]', $prop, $type));
        self::assertCount(1, $field, \sprintf('The field "%s" of type "%s".', $prop, $type));

        return $field;
    }

    private function page(string $component, string $library = 'props'): Crawler
    {
        $crawler = $this->client()->request('GET', \sprintf('/_showcase/%s/%s', $library, $component));
        self::assertSame(200, $this->client()->getResponse()->getStatusCode(), (string) $this->client()->getResponse()->getContent());

        return $crawler;
    }

    private function client(): KernelBrowser
    {
        if (null === $this->kernel) {
            $this->kernel = Configuration::kernel(true, Configuration::props());
            $this->kernel->boot();
        }
        if (null === $this->client) {
            $client = $this->kernel->getContainer()->get('test.client');
            self::assertInstanceOf(KernelBrowser::class, $client);
            $this->client = $client;
        }

        return $this->client;
    }
}

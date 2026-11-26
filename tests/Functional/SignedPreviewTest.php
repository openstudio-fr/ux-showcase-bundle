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
use OpenStudio\UxShowcaseBundle\Preview\SignedPreviewUrlGenerator;
use OpenStudio\UxShowcaseBundle\ShowcaseRegistry;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Configuration;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

final class SignedPreviewTest extends TestCase
{
    private const PREVIEW = '/_showcase/props/Props:Listing/default/preview';

    private ?TestKernel $kernel = null;
    private ?KernelBrowser $client = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        $this->client = null;
    }

    public function testRenewalGivesASignedAddressAndATokenThatWork(): void
    {
        [$url, $token] = $this->link();

        self::assertStringStartsWith(self::PREVIEW.'?', $url);
        self::assertSame(['locale', 'expires', 'signature'], array_keys($this->query($url)));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $this->query($url)['signature']);
        $json = $this->json($this->client()->getResponse()->getContent());
        self::assertIsInt($json['expires']);
        self::assertEqualsWithDelta(time() + 3600, $json['expires'], 5);

        $this->post($url, $token, []);

        self::assertSame(200, $this->code());
        self::assertStringContainsString('Golf', $this->content());
    }

    public function testRenewalIsUnderTheAccessCheck(): void
    {
        $this->client(Configuration::kernel(false, Configuration::props()))->request('GET', '/_showcase/props/Props:Listing/default/preview-link');

        self::assertSame(404, $this->code());
        self::assertSame('noindex, nofollow', $this->header('X-Robots-Tag'));
        self::assertStringNotContainsString('signature', $this->content());
    }

    public function testRenewalOfAnUnknownExampleIs404(): void
    {
        $this->client()->request('GET', '/_showcase/props/Props:Listing/nope/preview-link');

        self::assertSame(404, $this->code());
    }

    public function testTheBarePageOfTheExampleIsStillThereWithoutASignature(): void
    {
        $this->client()->request('GET', self::PREVIEW);

        self::assertSame(200, $this->code());
        self::assertStringContainsString('Polo', $this->content());
    }

    public function testATamperedSignatureIs404(): void
    {
        [$url, $token] = $this->link();
        $query = $this->query($url);
        $query['signature'] = strrev($query['signature']);

        $this->post(self::PREVIEW.'?'.http_build_query($query), $token, []);

        self::assertSame(404, $this->code());
    }

    public function testASignatureIsForOneExampleAndOneLocale(): void
    {
        [$url, $token] = $this->link();
        $query = $this->query($url);

        $this->post('/_showcase/props/Props:Listing/minimal/preview?'.http_build_query($query), $token, []);
        self::assertSame(404, $this->code(), 'another example');

        $this->post(self::PREVIEW.'?'.http_build_query(['locale' => 'fr'] + $query), $token, []);
        self::assertSame(404, $this->code(), 'another locale');

        $this->post(self::PREVIEW.'?'.http_build_query(['expires' => (int) $query['expires'] + 3600] + $query), $token, []);
        self::assertSame(404, $this->code(), 'a later expiry');
    }

    public function testAnAddressWithoutItsParametersIs404(): void
    {
        [, $token] = $this->link();

        $this->post(self::PREVIEW, $token, []);

        self::assertSame(404, $this->code());
    }

    public function testAnExpiredAddressIs404(): void
    {
        [, $token] = $this->link();
        $signer = $this->service(SignedPreviewUrlGenerator::class);
        $example = $this->example();

        $expired = $signer->generate('props', $example, 'en', new \DateTimeImmutable('-2 hours'));
        $this->post($expired, $token, []);
        self::assertSame(404, $this->code());

        $valid = $signer->generate('props', $example, 'en', new \DateTimeImmutable('-30 minutes'));
        $this->post($valid, $token, []);
        self::assertSame(200, $this->code());
    }

    public function testWithoutTheCsrfTokenOrWithAWrongOneIs403(): void
    {
        [$url] = $this->link();

        $this->post($url, null, []);
        self::assertSame(403, $this->code(), 'no token');

        $this->post($url, 'not-the-token', []);
        self::assertSame(403, $this->code(), 'wrong token');
    }

    public function testACrossOriginPostIs403EvenWithTheToken(): void
    {
        [$url, $token] = $this->link();

        $this->post($url, $token, [], ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        self::assertSame(403, $this->code(), 'Sec-Fetch-Site cross-site');

        $this->post($url, $token, [], ['HTTP_SEC_FETCH_SITE' => 'same-site']);
        self::assertSame(403, $this->code(), 'Sec-Fetch-Site same-site');

        $this->post($url, $token, [], ['HTTP_SEC_FETCH_SITE' => null, 'HTTP_ORIGIN' => 'https://evil.example']);
        self::assertSame(403, $this->code(), 'Origin of another host');

        $this->post($url, $token, [], ['HTTP_SEC_FETCH_SITE' => null, 'HTTP_ORIGIN' => null]);
        self::assertSame(403, $this->code(), 'no Origin at all');

        $this->post($url, $token, [], ['HTTP_SEC_FETCH_SITE' => null, 'HTTP_ORIGIN' => 'http://localhost']);
        self::assertSame(200, $this->code(), 'Origin of this host');
    }

    public function testAnUnknownKeyIs422AndNamesItAndNothingIsDrawn(): void
    {
        [$url, $token] = $this->link();

        $this->post($url, $token, ['onclick' => 'alert(1)', 'title' => 'Fine']);

        self::assertSame(422, $this->code());
        self::assertSame(['onclick'], $this->errorPaths());
        self::assertStringContainsString('is not a prop of Props:Listing', $this->content());
        self::assertStringNotContainsString('<article', $this->content());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'wrong type in an object' => [['vehicle' => ['name' => 'X', 'price' => 'abc']], 'vehicle.price'];
        yield 'wrong type of a prop' => [['title' => ['a']], 'title'];
        yield 'unknown enum case' => [['layout' => 'diagonal'], 'layout'];
        yield 'bad date' => [['since' => 'not-a-date'], 'since'];
        yield 'relative date' => [['since' => 'tomorrow'], 'since'];
        yield 'impossible date' => [['since' => '2026-13-45'], 'since'];
        yield 'wrong item of a list' => [['others' => [['name' => 'A'], ['name' => 'B', 'price' => 'x']]], 'others[1].price'];
        yield 'missing field of an object' => [['vehicle' => ['price' => 3]], 'vehicle.name'];
        yield 'unknown field of an object' => [['vehicle' => ['name' => 'A', 'color' => 'red']], 'vehicle.color'];
        yield 'object for a list' => [['others' => ['a' => ['name' => 'A']]], 'others'];
        yield 'scalar for an object' => [['vehicle' => 'Golf'], 'vehicle'];
        yield 'nested enum' => [['vehicle' => ['name' => 'A', 'layout' => 'nope']], 'vehicle.layout'];
        yield 'nested date' => [['vehicle' => ['name' => 'A', 'since' => 'nope']], 'vehicle.since'];
        yield 'neither int nor string' => [['limit' => ['x']], 'limit'];
        yield 'prop typed mixed' => [['payload' => 'anything'], 'payload'];
    }

    /**
     * @param array<string, mixed> $values
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidValues')]
    public function testAnInvalidValueIs422WithThePathOfTheProp(array $values, string $path): void
    {
        [$url, $token] = $this->link();

        $this->post($url, $token, $values);

        self::assertSame(422, $this->code(), $this->content());
        self::assertContains($path, $this->errorPaths());
    }

    public function testTheRefusalIsAPageOfTheLibraryAndAJsonBodyWhenAsked(): void
    {
        [$url, $token] = $this->link();

        $this->post($url, $token, ['vehicle' => ['name' => 'X', 'price' => 'abc']]);
        self::assertStringContainsString('data-demo-theme', $this->content(), 'the bare template of the library');
        self::assertStringContainsString('<code data-path="vehicle.price">vehicle.price</code>', $this->content());

        $this->post($url, $token, ['vehicle' => ['name' => 'X', 'price' => 'abc']], ['HTTP_ACCEPT' => 'application/json']);
        self::assertSame(422, $this->code());
        self::assertSame(['errors' => [['path' => 'vehicle.price', 'message' => 'Expected float, got string.']]], $this->json($this->content()));
    }

    public function testTheErrorsOfSeveralPropsComeTogether(): void
    {
        [$url, $token] = $this->link();

        $this->post($url, $token, ['layout' => 'nope', 'since' => 'nope', 'onclick' => 'x']);

        self::assertEqualsCanonicalizing(['layout', 'since', 'onclick'], $this->errorPaths());
    }

    public function testABodyThatIsNotAnObjectIs422AndNotJsonIs422(): void
    {
        [$url, $token] = $this->link();

        $this->post($url, $token, [], [], '[1, 2]');
        self::assertSame(422, $this->code());
        self::assertStringContainsString('JSON object', $this->content());

        $this->post($url, $token, [], [], '{broken');
        self::assertSame(422, $this->code());
        self::assertStringContainsString('not valid JSON', $this->content());
    }

    public function testABodyThatIsNeitherJsonNorAFormIs415(): void
    {
        [$url, $token] = $this->link();

        $this->post($url, $token, [], ['CONTENT_TYPE' => 'text/plain'], 'x');

        self::assertSame(415, $this->code());
    }

    public function testPostingOneKeyKeepsTheOtherPropsOfTheExample(): void
    {
        [$url, $token] = $this->link();

        $this->post($url, $token, ['title' => 'Changed']);

        self::assertSame(200, $this->code());
        $article = $this->article();
        self::assertStringContainsString('<h2>Changed</h2>', $article);
        self::assertStringContainsString('Golf 12.50 [new,diesel] rows 2026-01-02 10:30', $article);
        self::assertStringContainsString('<li>Polo</li><li>Passat</li>', $article);
        self::assertStringContainsString('<time>2026-03-04 08:00</time>', $article);
    }

    public function testValuesOfEveryKindAreConvertedToTheirDeclaredTypes(): void
    {
        [$url, $token] = $this->link();

        $this->post($url, $token, [
            'vehicle' => ['name' => 'Beetle', 'price' => 9, 'tags' => ['a', 'b'], 'layout' => 'grid', 'since' => '2025-12-24T00:00:00+00:00'],
            'others' => [['name' => 'Fox']],
            'layout' => 'grid',
            'since' => '2026-05-06T07:08:00+02:00',
            'limit' => 40,
        ]);

        self::assertSame(200, $this->code(), $this->content());
        $article = $this->article();
        self::assertStringContainsString('Beetle 9.00 [a,b] grid 2025-12-24 00:00', $article);
        self::assertStringContainsString('<ul><li>Fox</li></ul>', $article);
        self::assertStringContainsString('<i>grid</i><time>2026-05-06 05:08</time>', $article);
        self::assertStringContainsString('data-limit="40"', $article);
    }

    public function testNullClearsANullableProp(): void
    {
        [$url, $token] = $this->link();

        $this->post($url, $token, ['since' => null]);

        self::assertStringContainsString('<time>-</time>', $this->article());
    }

    public function testADtoPropMakesTheRoundTripFromPrefillToRender(): void
    {
        $this->client()->request('GET', self::PREVIEW);
        $drawn = $this->content();

        $prefill = $this->service(PreviewPrefill::class)->forExample($this->example());
        self::assertSame(['title', 'vehicle', 'others', 'layout', 'since'], array_keys($prefill));
        self::assertSame(['name' => 'Golf', 'price' => 12.5, 'tags' => ['new', 'diesel'], 'layout' => 'rows', 'since' => '2026-01-02T10:30:00+00:00'], $prefill['vehicle']);

        [$url, $token] = $this->link();
        $this->post($url, $token, $prefill);

        self::assertSame(200, $this->code(), $this->content());
        self::assertSame($drawn, $this->content());
    }

    public function testTheRoundTripHoldsWhenTheValuesPassThroughJsonAndTheForm(): void
    {
        $this->client()->request('GET', self::PREVIEW);
        $drawn = $this->content();

        [$url, $token] = $this->link();
        $form = [
            'title' => 'Stock',
            'vehicle' => ['name' => 'Golf', 'price' => '12.5', 'tags' => ['new', 'diesel'], 'layout' => 'rows', 'since' => '2026-01-02T10:30:00+00:00'],
            'others' => [['name' => 'Polo'], ['name' => 'Passat', 'price' => '30']],
            'layout' => 'rows',
            'since' => '2026-03-04T08:00:00+00:00',
            '_token' => $token,
        ];

        $this->client()->request('POST', $url, $form, [], $this->server([], 'application/x-www-form-urlencoded'));

        self::assertSame(200, $this->code(), $this->content());
        self::assertSame($drawn, $this->content());
    }

    public function testAFormWorksWithTheTokenInItsFieldAndAnEmptyFieldIsNullWhereTheTypeAllowsIt(): void
    {
        [$url, $token] = $this->link();

        $this->client()->request('POST', $url, ['_token' => $token, 'since' => '', 'title' => '', 'limit' => '7'], [], $this->server([], 'application/x-www-form-urlencoded'));

        self::assertSame(200, $this->code(), $this->content());
        $article = $this->article();
        self::assertStringContainsString('<time>-</time>', $article, 'empty nullable date');
        self::assertStringContainsString('<h2></h2>', $article, 'an empty text stays text');
        self::assertStringContainsString('data-limit="7"', $article);
    }

    public function testAFormValueOfTheWrongTypeIs422(): void
    {
        [$url, $token] = $this->link();

        $this->client()->request('POST', $url, ['_token' => $token, 'vehicle' => ['name' => 'A', 'price' => 'abc'], 'layout' => 'diagonal'], [], $this->server([], 'application/x-www-form-urlencoded'));

        self::assertSame(422, $this->code());
        self::assertEqualsCanonicalizing(['vehicle.price', 'layout'], $this->errorPaths());
    }

    public function testTheTokenInTheFieldIsNotAProp(): void
    {
        [$url, $token] = $this->link();

        $this->client()->request('POST', $url, ['_token' => $token], [], $this->server([], 'application/x-www-form-urlencoded'));

        self::assertSame(200, $this->code());
    }

    public function testTheContentOfTheExampleIsKeptWhenPropsAreTried(): void
    {
        [$url, $token] = $this->link('with-content');

        $this->post($url, $token, ['title' => 'Tried', 'vehicle' => ['name' => 'Panda']]);

        self::assertSame(200, $this->code(), $this->content());
        $article = $this->article();
        self::assertStringContainsString('<h2>Tried</h2>', $article);
        self::assertStringContainsString('Panda', $article);
        self::assertStringContainsString('<p class="body">Kept</p>', $article);
    }

    public function testTheContentCannotBePosted(): void
    {
        [$url, $token] = $this->link('with-content');

        $this->post($url, $token, ['content' => '<script>alert(1)</script>', 'footer' => 'x']);

        self::assertSame(422, $this->code());
        self::assertEqualsCanonicalizing(['content', 'footer'], $this->errorPaths());
        self::assertStringNotContainsString('<script>alert', $this->content());
    }

    public function testAValueIsNeverRenderedAsTwig(): void
    {
        [$url, $token] = $this->link();

        $this->post($url, $token, ['title' => '{{ 7 * 7 }}{% include "x" %}']);

        self::assertSame(200, $this->code());
        self::assertStringContainsString('{{ 7 * 7 }}', html_entity_decode($this->article()));
        self::assertStringNotContainsString('49', $this->article());
    }

    public function testAnHtmlValueIsEscapedWhereTheComponentEscapes(): void
    {
        [$url, $token] = $this->link();

        $this->post($url, $token, ['title' => '<img src=x onerror=alert(1)>']);

        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $this->article());
    }

    public function testTheLocaleOfTheSignedAddressIsTheOneTheExampleIsDrawnIn(): void
    {
        $this->client()->request('GET', self::PREVIEW.'-link', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);
        $example = $this->example();
        $url = $this->service(SignedPreviewUrlGenerator::class)->generate('props', $example, 'fr');
        [, $token] = $this->link();

        $this->post($url, $token, []);

        self::assertSame(200, $this->code());
        self::assertMatchesRegularExpression('/<html lang="fr"/', $this->content());

        $this->post($this->service(SignedPreviewUrlGenerator::class)->generate('props', $example, 'en'), $token, ['layout' => 'nope']);
        self::assertSame(422, $this->code());
    }

    public function testTheRefusalIsInTheLanguageOfTheSignedAddress(): void
    {
        [, $token] = $this->link();
        $url = $this->service(SignedPreviewUrlGenerator::class)->generate('props', $this->example(), 'fr');

        $this->post($url, $token, ['layout' => 'nope']);

        self::assertSame(422, $this->code());
        self::assertStringContainsString('Ces valeurs ne peuvent pas être prévisualisées', $this->content());
    }

    public function testEveryAnswerCarriesTheHeadersThatKeepTheValuesPrivate(): void
    {
        [$url, $token] = $this->link();
        $expired = $this->service(SignedPreviewUrlGenerator::class)->generate('props', $this->example(), 'en', new \DateTimeImmutable('-2 hours'));

        foreach ([
            200 => fn () => $this->post($url, $token, []),
            422 => fn () => $this->post($url, $token, ['onclick' => 'x']),
            403 => fn () => $this->post($url, null, []),
            404 => fn () => $this->post($expired, $token, []),
        ] as $status => $send) {
            $send();
            self::assertSame($status, $this->code(), (string) $status);
            self::assertSame('noindex, nofollow', $this->header('X-Robots-Tag'), (string) $status);
            self::assertStringContainsString('no-store', $this->header('Cache-Control'), (string) $status);
            self::assertSame('no-referrer', $this->header('Referrer-Policy'), (string) $status);
        }

        $this->client()->request('GET', self::PREVIEW.'-link');
        self::assertStringContainsString('no-store', $this->header('Cache-Control'), 'renewal');
    }

    public function testTheBarePageScriptIsAddedToTheDrawnPageAndTheProfilerIsOff(): void
    {
        [$url, $token] = $this->link();

        $this->post($url, $token, []);

        self::assertMatchesRegularExpression('#<script src="[^"]*bare\.js[^"]*" defer></script>\s*</body>#', $this->content());
        self::assertNull($this->client()->getResponse()->headers->get('X-Debug-Token'));
    }

    public function testAHostNormalizerMapsItsOwnFieldsToProps(): void
    {
        [$url, $token] = $this->link();

        $this->post($url, $token, ['vehicle_id' => '42']);

        self::assertSame(200, $this->code(), $this->content());
        self::assertStringContainsString('Catalogue car 42 5.00', $this->article());
    }

    public function testAFieldNoNormalizerTookIsAnUnknownKey(): void
    {
        [$url, $token] = $this->link();

        $this->post($url, $token, ['picked_image' => '3']);

        self::assertSame(422, $this->code());
        self::assertSame(['picked_image'], $this->errorPaths());
    }

    public function testAComponentThatRefusesTheValuesItsOwnWayIsA422NotA500(): void
    {
        [$url, $token] = $this->link();

        $this->post($url, $token, ['limit' => -1]);

        self::assertSame(422, $this->code());
        self::assertStringContainsString('The component cannot be drawn with these values: The limit must not be negative.', html_entity_decode($this->content()));
        self::assertSame([], $this->errorPaths());
    }

    public function testOutsideDebugEveryRouteIs404(): void
    {
        $client = $this->client(Configuration::kernel(false, Configuration::props()));
        $signer = $this->service(SignedPreviewUrlGenerator::class);
        $url = $signer->generate('props', $this->example(), 'en');

        $client->request('GET', self::PREVIEW.'-link');
        self::assertSame(404, $client->getResponse()->getStatusCode(), 'renewal');

        $client->request('POST', $url, [], [], $this->server(['token' => 'x']), '{}');
        self::assertSame(404, $client->getResponse()->getStatusCode(), 'post');
        self::assertSame('noindex, nofollow', $client->getResponse()->headers->get('X-Robots-Tag'));
    }

    private function client(?TestKernel $kernel = null): KernelBrowser
    {
        if (null === $this->kernel) {
            $this->kernel = $kernel ?? Configuration::kernel(true, Configuration::props());
            $this->kernel->boot();
        }
        if (null === $this->client) {
            $client = $this->kernel->getContainer()->get('test.client');
            self::assertInstanceOf(KernelBrowser::class, $client);
            $this->client = $client;
        }

        return $this->client;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $this->client();
        $service = $this->kernel?->getContainer()->get('test.'.$id);
        self::assertInstanceOf($id, $service);

        return $service;
    }

    private function example(string $slug = 'default'): Example
    {
        $example = $this->service(ShowcaseRegistry::class)->findExample('props', 'Props:Listing', $slug);
        self::assertNotNull($example);

        return $example;
    }

    /**
     * The renewal endpoint, as a page does: the signed address and the token of its session.
     *
     * @return array{string, string}
     */
    private function link(string $slug = 'default'): array
    {
        return $this->linkOf('Props:Listing', $slug);
    }

    /**
     * @return array{string, string}
     */
    private function linkOf(string $component, string $slug): array
    {
        $this->client()->request('GET', \sprintf('/_showcase/props/%s/%s/preview-link', $component, $slug));
        self::assertSame(200, $this->code(), $this->content());
        $json = $this->json($this->content());
        self::assertIsString($json['url']);
        self::assertIsString($json['token']);

        return [$json['url'], $json['token']];
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $server
     */
    private function post(string $url, ?string $token, array $values, array $server = [], ?string $body = null): void
    {
        $this->client()->request('POST', $url, [], [], $this->server($server + (null === $token ? [] : ['token' => $token])), $body ?? json_encode([] === $values ? new \stdClass() : $values, \JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, string>
     */
    private function server(array $overrides, string $contentType = 'application/json'): array
    {
        $server = ['CONTENT_TYPE' => $contentType, 'HTTP_SEC_FETCH_SITE' => 'same-origin'];
        if (isset($overrides['token'])) {
            $server['HTTP_X_CSRF_TOKEN'] = $overrides['token'];
        }
        unset($overrides['token']);

        /** @var array<string, string> $headers */
        $headers = array_filter($overrides + $server, static fn (mixed $value): bool => null !== $value);

        return $headers;
    }

    private function code(): int
    {
        return $this->client()->getResponse()->getStatusCode();
    }

    private function content(): string
    {
        return (string) $this->client()->getResponse()->getContent();
    }

    private function header(string $name): string
    {
        return (string) $this->client()->getResponse()->headers->get($name);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string|false $content): array
    {
        $decoded = json_decode((string) $content, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return array<string, string>
     */
    private function query(string $url): array
    {
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);

        /** @var array<string, string> $query */
        return $query;
    }

    /**
     * @return list<string>
     */
    private function errorPaths(): array
    {
        $crawler = new Crawler($this->content());

        return array_values(array_map(static fn (\DOMNode $node): string => $node->textContent, iterator_to_array($crawler->filterXPath('//li/code[@data-path]'))));
    }

    private function article(): string
    {
        self::assertSame(1, preg_match('#<article.*?</article>#s', $this->content(), $matches), $this->content());

        return $matches[0] ?? self::fail('No article.');
    }
}

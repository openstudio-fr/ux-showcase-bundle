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

use OpenStudio\UxShowcaseBundle\Tests\Fixtures\AllowAllShowcaseAccess;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class ShowcaseAccessTest extends TestCase
{
    private ?TestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testIndexIsNotFoundOutsideDebug(): void
    {
        $client = $this->client(debug: false);
        $client->request('GET', '/_showcase/');

        self::assertSame(404, $client->getResponse()->getStatusCode());
        self::assertSame('noindex, nofollow', $client->getResponse()->headers->get('X-Robots-Tag'));
    }

    public function testIndexIsServedInDebug(): void
    {
        $client = $this->client(debug: true, configuration: ['title' => 'My components']);
        $client->request('GET', '/_showcase/');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('<h1>My components</h1>', (string) $client->getResponse()->getContent());
        self::assertSame('noindex, nofollow', $client->getResponse()->headers->get('X-Robots-Tag'));
    }

    public function testScreenIsTranslated(): void
    {
        $client = $this->client(debug: true);
        $client->request('GET', '/_showcase/');

        self::assertStringContainsString('No component library is registered yet.', (string) $client->getResponse()->getContent());
    }

    public function testReplacedAccessServiceOpensTheShowcaseOutsideDebug(): void
    {
        $client = $this->client(debug: false, configuration: ['access' => ['service' => AllowAllShowcaseAccess::class]]);
        $client->request('GET', '/_showcase/');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertSame('noindex, nofollow', $client->getResponse()->headers->get('X-Robots-Tag'));
    }

    public function testRoleOpensTheShowcaseForAUserWhoHasIt(): void
    {
        $client = $this->client(debug: false, configuration: ['access' => ['role' => 'ROLE_SHOWCASE']], withSecurity: true);
        $client->loginUser(new InMemoryUser('dev', 'x', ['ROLE_SHOWCASE']), 'main');
        $client->request('GET', '/_showcase/');

        self::assertSame(200, $client->getResponse()->getStatusCode());
    }

    #[DataProvider('usersWithoutTheRole')]
    public function testRoleDoesNotOpenTheShowcaseWithoutIt(?InMemoryUser $user): void
    {
        $client = $this->client(debug: false, configuration: ['access' => ['role' => 'ROLE_SHOWCASE']], withSecurity: true);
        if (null !== $user) {
            $client->loginUser($user, 'main');
        }
        $client->request('GET', '/_showcase/');

        self::assertSame(404, $client->getResponse()->getStatusCode());
        self::assertSame('noindex, nofollow', $client->getResponse()->headers->get('X-Robots-Tag'));
    }

    /**
     * @return iterable<string, array{InMemoryUser|null}>
     */
    public static function usersWithoutTheRole(): iterable
    {
        yield 'anonymous' => [null];
        yield 'other role' => [new InMemoryUser('other', 'x', ['ROLE_USER'])];
    }

    public function testDebugOpensTheShowcaseEvenWhenARoleIsConfigured(): void
    {
        $client = $this->client(debug: true, configuration: ['access' => ['role' => 'ROLE_SHOWCASE']], withSecurity: true);
        $client->request('GET', '/_showcase/');

        self::assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testRoleWithoutSecurityFailsAtCompilation(): void
    {
        $kernel = $this->kernel = new TestKernel(false, ['access' => ['role' => 'ROLE_SHOWCASE']]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('ux_showcase.access.role');
        $kernel->boot();
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function client(bool $debug, array $configuration = [], bool $withSecurity = false): KernelBrowser
    {
        $this->kernel = new TestKernel($debug, $configuration, $withSecurity);
        $this->kernel->boot();
        $client = $this->kernel->getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);

        return $client;
    }
}

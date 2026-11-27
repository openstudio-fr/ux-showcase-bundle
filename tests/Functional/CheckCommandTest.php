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
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Preview\HostRequest;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Preview\HostRequestFactory;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Preview\HostSession;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Preview\OtherHostRequestFactory;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class CheckCommandTest extends TestCase
{
    private ?TestKernel $kernel = null;
    private ?string $directory = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        if (null !== $this->directory) {
            (new Filesystem())->remove($this->directory);
        }
    }

    public function testEveryExampleOfTheTestLibraryRendersAndTheSummaryCountsThem(): void
    {
        [$status, $display] = $this->check(['--library' => ['demo', 'props', 'other']]);

        self::assertSame(0, $status, $display);
        self::assertMatchesRegularExpression('/^Checked \d+, passed \d+, failed 0, skipped 1\.$/m', $display);
        self::assertStringContainsString('WARN  demo / Demo:Layout:Card / article skipped, needs data: use --with-data to render it', $display);
        self::assertStringNotContainsString('FAIL', $display);
    }

    public function testAPhpExampleThatNeedsDataIsRenderedWithData(): void
    {
        [$status, $display] = $this->check(['--library' => ['demo'], '--with-data' => true]);

        self::assertSame(0, $status, $display);
        self::assertMatchesRegularExpression('/^Checked \d+, passed \d+, failed 0, skipped 0\.$/m', $display);
    }

    public function testEveryBrokenExampleIsListedWithItsNameAndTheExitCodeIsOne(): void
    {
        [$status, $display] = $this->check(['--library' => ['broken']]);

        self::assertSame(1, $status);
        self::assertStringContainsString('FAIL  broken / Check:Plain / missing-include: Twig\Error\LoaderError: Unable to find template "nope.html.twig"', $display);
        self::assertStringContainsString('FAIL  broken / Check:Plain / unknown-filter: ', $display);
        self::assertStringContainsString('no_such_filter', $display);
        self::assertStringNotContainsString('broken / Check:Plain / fine', $display, 'the example that works is not listed as broken');
        self::assertStringContainsString('Checked 4, passed 1, failed 3, skipped 0.', $display);
        self::assertStringContainsString('FAIL  broken / Check:Plain / teapot: HTTP 418 instead of 200', $display);
    }

    public function testAnUndefinedVariableFailsOnlyUnderStrict(): void
    {
        // The same kernel, the lax run first: the strict one must not reuse what the lax one compiled.
        $tester = $this->tester();

        $tester->execute(['--library' => ['strict']]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Checked 2, passed 2, failed 0, skipped 0.', $tester->getDisplay());

        $tester->execute(['--library' => ['strict'], '--strict' => true]);
        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('FAIL  strict / Check:Plain / undefined-variable: ', $tester->getDisplay());
        self::assertStringContainsString('Variable "nothing_here" does not exist', $tester->getDisplay());
        self::assertStringContainsString('Checked 2, passed 1, failed 1, skipped 0.', $tester->getDisplay());

        $tester->execute(['--library' => ['strict']]);
        self::assertSame(0, $tester->getStatusCode(), 'strict variables are off again after the strict run');
    }

    public function testADeprecationFailsOnlyUnderStrict(): void
    {
        [$status, $display] = $this->check(['--library' => ['deprecated'], '--strict' => true]);

        self::assertSame(1, $status);
        self::assertStringContainsString('FAIL  deprecated / Check:Deprecated / old: Deprecation: The component "Check:Deprecated" is deprecated.', $display);
    }

    public function testLibraryFiltersAndIsRepeatable(): void
    {
        [, $one] = $this->check(['--library' => ['strict']]);
        [, $two] = $this->check(['--library' => ['strict', 'data']], verbose: true);
        [, $all] = $this->check([], verbose: true);

        self::assertStringContainsString('Checked 2,', $one);
        self::assertStringContainsString('OK  strict / Check:Plain / fine', $two);
        self::assertStringContainsString('OK  data / Check:Plain / fine', $two);
        self::assertStringNotContainsString('demo /', $two);
        self::assertStringContainsString('OK  demo / Demo:Button / primary', $all);
        self::assertStringContainsString('OK  other / Other:Note / hello', $all);
    }

    public function testAnUnknownLibraryIsRefused(): void
    {
        [$status, $display] = $this->check(['--library' => ['nope']]);

        self::assertSame(2, $status);
        self::assertStringContainsString('Unknown library "nope". Known: broken,', $display);
    }

    public function testExamplesThatNeedDataAreSkippedWithAWarningUnlessWithData(): void
    {
        [$status, $display] = $this->check(['--library' => ['data']]);

        self::assertSame(0, $status, $display);
        self::assertStringContainsString('WARN  data / Check:Plain / reads-database skipped, needs data: use --with-data to render it', $display);
        self::assertStringContainsString('WARN  data / Check:Reads / reads-session skipped, needs data', $display);
        self::assertStringContainsString('Checked 1, passed 1, failed 0, skipped 2.', $display);

        [$status, $display] = $this->check(['--library' => ['data'], '--with-data' => true]);

        self::assertSame(1, $status);
        self::assertStringContainsString('FAIL  data / Check:Plain / reads-database: ', $display);
        self::assertStringContainsString('FAIL  data / Check:Reads / reads-session: ', $display);
        self::assertStringContainsString('Checked 3, passed 1, failed 2, skipped 0.', $display);
    }

    public function testJunitReportHasOneTestcasePerExampleAndTheFailureMessage(): void
    {
        $this->directory = sys_get_temp_dir().'/ux-showcase-junit-'.bin2hex(random_bytes(4));
        $file = $this->directory.'/reports/check.xml';

        [$status, $display] = $this->check(['--library' => ['broken', 'data'], '--format' => 'junit', '--output' => $file]);

        self::assertSame(1, $status);
        self::assertStringContainsString('Checked 5, passed 2, failed 3, skipped 2.', $display, 'the console keeps the human output');
        self::assertFileExists($file);

        $report = new \DOMDocument();
        self::assertTrue($report->load($file), 'well-formed XML');
        $xpath = new \DOMXPath($report);
        self::assertSame(7, \count(self::nodes($xpath, '//testcase')));
        self::assertSame(3, \count(self::nodes($xpath, '//testcase/failure')));
        self::assertSame(2, \count(self::nodes($xpath, '//testcase/skipped')));
        self::assertSame('7', self::nodes($xpath, '/testsuites/@tests')[0]->nodeValue);
        self::assertSame('3', self::nodes($xpath, '/testsuites/@failures')[0]->nodeValue);
        self::assertSame(['broken', 'data'], array_map(static fn (\DOMNode|\DOMNameSpaceNode $node): string => (string) $node->nodeValue, self::nodes($xpath, '//testsuite/@name')));

        $case = self::nodes($xpath, '//testcase[@name="missing-include"]')[0];
        self::assertInstanceOf(\DOMElement::class, $case);
        self::assertSame('broken.Check:Plain', $case->getAttribute('classname'));
        $failure = $case->getElementsByTagName('failure')->item(0);
        self::assertInstanceOf(\DOMElement::class, $failure);
        self::assertStringContainsString('Unable to find template "nope.html.twig"', $failure->getAttribute('message'));
        self::assertStringContainsString('Unable to find template "nope.html.twig"', (string) $failure->textContent);
        self::assertSame(0, \count(self::nodes($xpath, '//testcase[@name="fine" and @classname="broken.Check:Plain"]/*')), 'a passed example has no child');
    }

    public function testJunitWithoutAnOutputFileWritesTheXmlToStandardOutput(): void
    {
        [, $display] = $this->check(['--library' => ['strict'], '--format' => 'junit']);

        $report = new \DOMDocument();
        self::assertTrue($report->loadXML($display));
        self::assertSame(2, \count(self::nodes(new \DOMXPath($report), '//testcase')));
    }

    public function testOutputNeedsTheJunitFormat(): void
    {
        [$status] = $this->check(['--output' => '/tmp/never-written.xml']);

        self::assertSame(2, $status);
        self::assertFileDoesNotExist('/tmp/never-written.xml');
    }

    public function testTheRequestsOfTheCheckComeFromTheFactoryOfTheApplication(): void
    {
        HostRequestFactory::reset();

        [$status, $display] = $this->check(['--library' => ['strict']], factories: [HostRequestFactory::class]);

        self::assertSame(0, $status, $display);
        $created = HostRequestFactory::$created;
        self::assertCount(3, $created, 'the main request, then one request per rendered example');
        self::assertSame('/', $created[0]->getRequestUri());
        self::assertStringStartsWith('/_showcase/strict/', $created[1]->getRequestUri());
        self::assertStringStartsWith('/_showcase/strict/', $created[2]->getRequestUri());
        foreach ($created as $request) {
            self::assertInstanceOf(HostRequest::class, $request);
            self::assertInstanceOf(HostSession::class, $request->getSession());
            self::assertSame($created[0]->getSession(), $request->getSession(), 'a preview takes the session of the main request');
        }
    }

    public function testMoreThanOneRequestFactoryFailsAndNamesThem(): void
    {
        [$status, $display] = $this->check(['--library' => ['strict']], factories: [HostRequestFactory::class, OtherHostRequestFactory::class]);

        self::assertSame(1, $status);
        self::assertStringContainsString(HostRequestFactory::class, $display);
        self::assertStringContainsString(OtherHostRequestFactory::class, $display);
        self::assertStringNotContainsString('Checked', $display);
    }

    public function testWithoutAFactoryThePlainRequestAndSessionAreUsed(): void
    {
        HostRequestFactory::reset();

        [$status, $display] = $this->check(['--library' => ['data'], '--with-data' => true]);

        self::assertSame(1, $status);
        // Unchanged: the session is simulated (the example that reads it at mount fails on its own content, not for lack of one).
        self::assertStringContainsString('Checked 3, passed 1, failed 2, skipped 0.', $display);
        self::assertSame([], HostRequestFactory::$created);
    }

    public function testTheAccessBypassIsNotReachableFromTheOutside(): void
    {
        $client = $this->client();
        $path = '/_showcase/demo/Demo:Button/primary/preview';

        $client->request('GET', $path);
        self::assertSame(404, $client->getResponse()->getStatusCode(), 'outside debug the preview is refused');

        $client->request('GET', $path.'?_ux_showcase_check=1&_ux_showcase_check[]=1', server: ['HTTP_X_UX_SHOWCASE_CHECK' => '1', 'HTTP_X_ATTRIBUTE__UX_SHOWCASE_CHECK' => 'true']);
        self::assertSame(404, $client->getResponse()->getStatusCode(), 'a query or a header does not lift the check');
    }

    public function testTheAccessBypassIsHonouredOnASubRequestOnly(): void
    {
        $this->kernel = $this->kernel();
        $this->kernel->boot();
        $request = Request::create('/_showcase/demo/Demo:Button/primary/preview');
        $request->attributes->set('_ux_showcase_check', true);

        // The attribute on a main request, as if a host or a bad route default had set it: refused all the same.
        self::assertSame(404, $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST)->getStatusCode());
    }

    /**
     * @return list<\DOMNode|\DOMNameSpaceNode>
     */
    private static function nodes(\DOMXPath $xpath, string $query): array
    {
        $result = $xpath->query($query);
        self::assertInstanceOf(\DOMNodeList::class, $result);

        return array_values(iterator_to_array($result));
    }

    /**
     * @param array<string, mixed> $input
     * @param list<class-string>   $factories
     *
     * @return array{int, string}
     */
    private function check(array $input, bool $verbose = false, array $factories = []): array
    {
        $tester = $this->tester($factories);
        $tester->execute($input, ['verbosity' => $verbose ? OutputInterface::VERBOSITY_VERBOSE : OutputInterface::VERBOSITY_NORMAL]);

        return [$tester->getStatusCode(), $tester->getDisplay()];
    }

    /**
     * @param list<class-string> $factories
     */
    private function tester(array $factories = []): CommandTester
    {
        $this->kernel?->shutdown();
        $this->kernel = $this->kernel($factories);
        $application = new Application($this->kernel);

        return new CommandTester($application->find('ux-showcase:check'));
    }

    /**
     * @param list<class-string> $factories
     */
    private function kernel(array $factories = []): TestKernel
    {
        // Outside debug: the access check refuses everyone, and Twig does not enable strict variables on its own.
        return new TestKernel(false, Configuration::check(), withLibrary: true, checkRequestFactories: $factories);
    }

    private function client(): KernelBrowser
    {
        $this->kernel = $this->kernel();

        return new KernelBrowser($this->kernel);
    }
}

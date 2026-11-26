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

use OpenStudio\UxShowcaseBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class ExampleFileLintTest extends TestCase
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

    public function testLintTwigAcceptsAnExampleFile(): void
    {
        [$status, $display] = $this->lint(<<<'TWIG'
            {% showcase_example 'primary' with { label: 'Save' } %}
                <twig:Button label="Save" />
            {% end_showcase_example %}
            {% showcase_free 'page' titled 'Page' options { wrapper: false } %}
                <p>Free</p>
            {% end_showcase_free %}
            TWIG);

        self::assertSame(0, $status, $display);
    }

    public function testLintTwigRejectsAnUnclosedTag(): void
    {
        [$status, $display] = $this->lint("{% showcase_example 'primary' %}\n<p>x</p>\n");

        self::assertSame(1, $status, $display);
        self::assertStringContainsString('Unexpected token "end of template"', $display);
    }

    public function testLintTwigRejectsAPropThatIsNotALiteral(): void
    {
        [$status, $display] = $this->lint("{% showcase_example 'primary' with { label: title } %}{% end_showcase_example %}");

        self::assertSame(1, $status, $display);
        self::assertStringContainsString('Props and options are literals', $display);
    }

    public function testTheApplicationRendersNothingForTheTagsAndNeverEvaluatesTheirBody(): void
    {
        $twig = $this->twig();
        $twig->setLoader(new ArrayLoader(['page' => "a{% showcase_example 'x' with { label: 'Save' } %}{{ 'inside'|upper }}{% end_showcase_example %}b{% showcase_free 'y' %}{{ 'free'|upper }}{% end_showcase_free %}c"]));

        self::assertSame('abc', $twig->render('page'));
    }

    /**
     * @return array{int, string}
     */
    private function lint(string $source): array
    {
        $this->directory = sys_get_temp_dir().'/ux-showcase-lint-'.bin2hex(random_bytes(4));
        (new Filesystem())->dumpFile($this->directory.'/Button.showcase.twig', $source);

        $this->kernel = new TestKernel(false);
        $this->kernel->boot();
        $tester = new CommandTester((new Application($this->kernel))->find('lint:twig'));
        $status = $tester->execute(['filename' => [$this->directory]], ['decorated' => false]);

        return [$status, $tester->getDisplay()];
    }

    private function twig(): Environment
    {
        $this->kernel = new TestKernel(false);
        $this->kernel->boot();
        $twig = $this->kernel->getContainer()->get('test.twig');
        \assert($twig instanceof Environment);

        return $twig;
    }
}

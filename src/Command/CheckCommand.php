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

namespace OpenStudio\UxShowcaseBundle\Command;

use OpenStudio\UxShowcaseBundle\EventListener\ShowcaseAccessListener;
use OpenStudio\UxShowcaseBundle\Example\Example;
use OpenStudio\UxShowcaseBundle\Example\PreviewOptionsResolver;
use OpenStudio\UxShowcaseBundle\Exception\ShowcaseException;
use OpenStudio\UxShowcaseBundle\Library\ComponentLibraryInterface;
use OpenStudio\UxShowcaseBundle\ShowcaseRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Renders every example through a sub-request to its bare page, as the application would serve it, with a simulated
 * session and the access check lifted for those sub-requests only. Lists every broken example, not the first.
 */
#[AsCommand(name: 'ux-showcase:check', description: 'Render every example of the showcase and report the ones that fail')]
final class CheckCommand extends Command
{
    public function __construct(
        private readonly ShowcaseRegistry $registry,
        private readonly PreviewOptionsResolver $previewResolver,
        private readonly HttpKernelInterface $kernel,
        private readonly UrlGeneratorInterface $router,
        private readonly RequestStack $requests,
        private readonly Environment $twig,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('library', 'l', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Check this library only (repeatable)')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Twig strict variables, and PHP notices, warnings and deprecations raised during a render fail it')
            ->addOption('with-data', null, InputOption::VALUE_NONE, 'Render the examples marked as needing data too, instead of skipping them')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'human or junit', 'human')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'File the JUnit report is written to (with --format=junit)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');
        $file = $input->getOption('output');
        if (!\in_array($format, ['human', 'junit'], true)) {
            $output->writeln('<error>The format is "human" or "junit".</error>');

            return Command::INVALID;
        }
        if (null !== $file && 'junit' !== $format) {
            $output->writeln('<error>--output needs --format=junit.</error>');

            return Command::INVALID;
        }

        try {
            $libraries = $this->libraries($input);
            if (!\is_array($libraries)) {
                $output->writeln(\sprintf('<error>Unknown library "%s". Known: %s.</error>', $libraries, implode(', ', array_map(static fn (ComponentLibraryInterface $library): string => $library->getName(), $this->registry->libraries())) ?: 'none'));

                return Command::INVALID;
            }

            $outcomes = $this->checkAll($libraries, true === $input->getOption('strict'), true === $input->getOption('with-data'));
        } catch (ShowcaseException $exception) {
            $output->writeln(\sprintf('<error>The showcase cannot be built: %s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }

        if (\is_string($file) && '' !== $file) {
            $directory = \dirname($file);
            if ((!is_dir($directory) && !@mkdir($directory, 0o777, true) && !is_dir($directory)) || false === @file_put_contents($file, JunitReport::render($outcomes))) {
                $output->writeln(\sprintf('<error>The report cannot be written to "%s".</error>', $file));

                return Command::FAILURE;
            }
        }

        if ('junit' === $format && !\is_string($file)) {
            $output->write(JunitReport::render($outcomes), false, OutputInterface::OUTPUT_RAW);
        } else {
            $this->report($output, $outcomes);
        }

        foreach ($outcomes as $outcome) {
            if (CheckOutcome::FAILED === $outcome->status) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<ComponentLibraryInterface>|string the libraries to check, or the name of the first unknown one
     */
    private function libraries(InputInterface $input): array|string
    {
        $names = $input->getOption('library');
        \assert(\is_array($names));
        if ([] === $names) {
            return $this->registry->libraries();
        }

        $libraries = [];
        foreach ($names as $name) {
            \assert(\is_string($name));
            $library = $this->registry->library($name);
            if (null === $library) {
                return $name;
            }
            $libraries[] = $library;
        }

        return $libraries;
    }

    /**
     * @param list<ComponentLibraryInterface> $libraries
     *
     * @return list<CheckOutcome>
     */
    private function checkAll(array $libraries, bool $strict, bool $withData): array
    {
        $strictBefore = $this->twig->isStrictVariables();
        if ($strict) {
            $this->twig->enableStrictVariables();
        }

        // The main request of a sub-request: what csrf_token() and the session of the application look for.
        $main = Request::create('/');
        $main->setSession(new Session(new MockArraySessionStorage()));
        $this->requests->push($main);

        $outcomes = [];
        try {
            foreach ($libraries as $library) {
                foreach ($this->examples($library) as $example) {
                    $outcomes[] = $this->check($library, $example, $strict, $withData);
                }
            }
        } finally {
            $this->requests->pop();
            if ($strict && !$strictBefore) {
                $this->twig->disableStrictVariables();
            }
        }

        return $outcomes;
    }

    /**
     * @return list<Example>
     */
    private function examples(ComponentLibraryInterface $library): array
    {
        $examples = [];
        foreach ($this->registry->components($library->getName()) as $component) {
            foreach ($this->registry->examples($library->getName(), $component->name) as $example) {
                $examples[] = $example;
            }
        }

        return [...$examples, ...$this->registry->docPages($library->getName())];
    }

    private function check(ComponentLibraryInterface $library, Example $example, bool $strict, bool $withData): CheckOutcome
    {
        $name = $library->getName();
        if (!$withData && $this->previewResolver->resolve($library, $example)->needsData) {
            return new CheckOutcome($name, $example->component, $example->slug, CheckOutcome::SKIPPED, 'needs data: use --with-data to render it');
        }

        $path = null === $example->component
            ? $this->router->generate('ux_showcase_doc_preview', ['library' => $name, 'slug' => $example->slug])
            : $this->router->generate('ux_showcase_preview', ['library' => $name, 'component' => $example->component, 'slug' => $example->slug]);

        $request = Request::create($path);
        $request->setSession($this->requests->getMainRequest()?->getSession() ?? new Session(new MockArraySessionStorage()));
        $request->attributes->set(ShowcaseAccessListener::CHECK_ATTRIBUTE, true);

        $problems = [];
        $started = microtime(true);
        $level = ob_get_level();
        if ($strict) {
            set_error_handler(static function (int $type, string $message, string $file, int $line) use (&$problems): bool {
                $deprecation = \in_array($type, [\E_DEPRECATED, \E_USER_DEPRECATED], true);
                // `@` silences a notice or a warning on purpose; a deprecation is raised silenced by convention.
                if (!$deprecation && 0 === (error_reporting() & $type)) {
                    return false;
                }
                $kind = $deprecation ? 'Deprecation' : (\in_array($type, [\E_NOTICE, \E_USER_NOTICE], true) ? 'Notice' : 'Warning');
                $problems[] = \sprintf('%s: %s (%s:%d)', $kind, $message, basename($file), $line);

                return true;
            });
        }

        try {
            $response = $this->kernel->handle($request, HttpKernelInterface::SUB_REQUEST, false);
            if (200 !== $response->getStatusCode()) {
                array_unshift($problems, \sprintf('HTTP %d instead of 200', $response->getStatusCode()));
            }
        } catch (\Throwable $exception) {
            array_unshift($problems, \sprintf('%s: %s', $exception::class, $exception->getMessage()));
        } finally {
            if ($strict) {
                restore_error_handler();
            }
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }

        $seconds = microtime(true) - $started;
        if ([] === $problems) {
            return new CheckOutcome($name, $example->component, $example->slug, CheckOutcome::PASSED, null, $seconds);
        }

        return new CheckOutcome($name, $example->component, $example->slug, CheckOutcome::FAILED, implode(' | ', array_map(static fn (string $problem): string => trim((string) preg_replace('/\s+/', ' ', $problem)), $problems)), $seconds);
    }

    /**
     * @param list<CheckOutcome> $outcomes
     */
    private function report(OutputInterface $output, array $outcomes): void
    {
        $counts = [CheckOutcome::PASSED => 0, CheckOutcome::FAILED => 0, CheckOutcome::SKIPPED => 0];
        foreach ($outcomes as $outcome) {
            ++$counts[$outcome->status];
            if (CheckOutcome::FAILED === $outcome->status) {
                $output->writeln(\sprintf('<error> FAIL </error> %s: %s', $outcome->label(), OutputFormatter::escape((string) $outcome->message)));
            } elseif (CheckOutcome::SKIPPED === $outcome->status) {
                $output->writeln(\sprintf('<comment> WARN </comment> %s skipped, %s', $outcome->label(), $outcome->message));
            } else {
                $output->writeln(\sprintf('<info> OK </info> %s', $outcome->label()), OutputInterface::VERBOSITY_VERBOSE);
            }
        }

        $output->writeln(\sprintf('Checked %d, passed %d, failed %d, skipped %d.', $counts[CheckOutcome::PASSED] + $counts[CheckOutcome::FAILED], $counts[CheckOutcome::PASSED], $counts[CheckOutcome::FAILED], $counts[CheckOutcome::SKIPPED]));
    }
}

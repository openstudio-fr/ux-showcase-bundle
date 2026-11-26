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

namespace OpenStudio\UxShowcaseBundle\Tests\Fixtures;

use OpenStudio\UxShowcaseBundle\Example\ExampleCodeGenerator;
use OpenStudio\UxShowcaseBundle\Example\ExampleRenderer;
use OpenStudio\UxShowcaseBundle\Example\FixtureLoader;
use OpenStudio\UxShowcaseBundle\OpenStudioUxShowcaseBundle;
use OpenStudio\UxShowcaseBundle\Preview\PreviewPrefill;
use OpenStudio\UxShowcaseBundle\Preview\SignedPreviewUrlGenerator;
use OpenStudio\UxShowcaseBundle\Props\ComponentDocumentation;
use OpenStudio\UxShowcaseBundle\ShowcaseRegistry;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\LiveComponent\LiveComponentBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $showcaseConfiguration
     */
    public function __construct(
        bool $debug,
        private readonly array $showcaseConfiguration = [],
        private readonly bool $withSecurity = false,
        private readonly bool $withLibrary = false,
        bool $cleanCache = true,
    ) {
        parent::__construct('test', $debug);

        // A non-debug container is never rebuilt on its own: start clean so a test never runs on a stale one.
        // The browser front controller keeps its cache between requests.
        if ($cleanCache) {
            (new Filesystem())->remove(sys_get_temp_dir().'/ux-showcase-tests/'.$this->variant());
        }
    }

    private mixed $exceptionHandlerBeforeBoot = null;

    public function boot(): void
    {
        if (!$this->booted) {
            $this->exceptionHandlerBeforeBoot = self::currentExceptionHandler();
        }

        parent::boot();
    }

    // Some versions of the error handler leave their exception handler registered, which PHPUnit reports as risky.
    public function shutdown(): void
    {
        parent::shutdown();

        for ($attempt = 0; $attempt < 10 && self::currentExceptionHandler() !== $this->exceptionHandlerBeforeBoot; ++$attempt) {
            restore_exception_handler();
        }
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigComponentBundle();
        yield new OpenStudioUxShowcaseBundle();

        if ($this->withSecurity) {
            yield new SecurityBundle();
        }

        if ($this->withLibrary) {
            yield new StimulusBundle();
            yield new LiveComponentBundle();
        }
    }

    // Keeps the files a kernel writes next to its configuration (config/reference.php) out of the repository.
    public function getProjectDir(): string
    {
        return sys_get_temp_dir().'/ux-showcase-tests/'.$this->variant().'/project';
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/ux-showcase-tests/'.$this->variant().'/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/ux-showcase-tests/'.$this->variant().'/log';
    }

    protected function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'default_locale' => 'en',
            // Collected in debug, as in a development application: the bare page of a preview must switch it off.
            'profiler' => ['enabled' => $this->debug && $this->withLibrary, 'collect' => true],
        ]);
        $container->extension('ux_showcase', $this->showcaseConfiguration);

        if ($this->withSecurity) {
            $container->extension('security', [
                'providers' => ['users' => ['memory' => ['users' => [
                    'dev' => ['password' => 'x', 'roles' => ['ROLE_SHOWCASE']],
                    'other' => ['password' => 'x', 'roles' => ['ROLE_USER']],
                ]]]],
                'firewalls' => ['main' => ['lazy' => true, 'provider' => 'users']],
            ]);
        }

        // Both keys are required by UX 3.x, and defaults must not be empty on 2.x.
        $container->extension('twig_component', [
            'anonymous_template_directory' => 'components/',
            'defaults' => ['OpenStudio\\UxShowcaseBundle\\Tests\\Fixtures\\Library\\Component\\' => ['template_directory' => 'components/', 'name_prefix' => 'Demo']],
        ]);

        $services = $container->services();
        $services->set(AllowAllShowcaseAccess::class)->public();

        if ($this->withLibrary) {
            $library = __DIR__.'/Library';
            $container->extension('twig', ['paths' => [$library.'/templates']]);

            $services->load('OpenStudio\\UxShowcaseBundle\\Tests\\Fixtures\\Library\\Component\\', $library.'/Component/')->autoconfigure();
            $services->set('logger', NullLogger::class);
            // LiveComponent wires its validator when a component uses ValidatableComponentTrait; nothing validates here.
            $services->set('validator', \stdClass::class);
            $services->set(Library\ServiceLibrary::class)->autoconfigure();
            $services->set(Library\TeapotListener::class)->autoconfigure();
            $services->set(Library\ChainReader::class)->autoconfigure();
            $services->set(Library\Action\AddToPageAction::class)->autoconfigure();
            $services->set(Library\Action\KeepExampleAction::class)->autoconfigure();
            $services->set(Library\Example\ArticleCard::class)->autowire()->autoconfigure();
            $services->set(Library\Preview\CatalogueVehicleNormalizer::class)->autoconfigure();
            $services->set(Library\Try\ImageFieldType::class)->autoconfigure();
            // Public aliases: the services themselves are private and inlined or removed when nothing else uses them.
            foreach ([ComponentDocumentation::class, ShowcaseRegistry::class, ExampleRenderer::class, ExampleCodeGenerator::class, FixtureLoader::class, PreviewPrefill::class, SignedPreviewUrlGenerator::class] as $id) {
                $services->alias('test.'.$id, $id)->public();
            }
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@OpenStudioUxShowcaseBundle/config/routes.php')->prefix('/_showcase');

        if ($this->withLibrary) {
            $routes->import('@LiveComponentBundle/config/routes.php')->prefix('/_components');
        }
    }

    private static function currentExceptionHandler(): mixed
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }

    private function variant(): string
    {
        return hash('xxh3', serialize([$this->debug, $this->showcaseConfiguration, $this->withSecurity, $this->withLibrary]));
    }
}

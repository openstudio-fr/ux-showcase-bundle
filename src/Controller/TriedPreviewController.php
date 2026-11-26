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

namespace OpenStudio\UxShowcaseBundle\Controller;

use OpenStudio\UxShowcaseBundle\EventListener\BarePageListener;
use OpenStudio\UxShowcaseBundle\EventListener\SignedPreviewListener;
use OpenStudio\UxShowcaseBundle\Example\Example;
use OpenStudio\UxShowcaseBundle\Exception\UnknownComponentException;
use OpenStudio\UxShowcaseBundle\Library\ComponentLibraryInterface;
use OpenStudio\UxShowcaseBundle\Preview\BarePage;
use OpenStudio\UxShowcaseBundle\Preview\InvalidPostedValuesException;
use OpenStudio\UxShowcaseBundle\Preview\PostedValueError;
use OpenStudio\UxShowcaseBundle\Preview\PostedValuesResolver;
use OpenStudio\UxShowcaseBundle\Preview\PreviewRequestGuard;
use OpenStudio\UxShowcaseBundle\Preview\PreviewUrlGeneratorInterface;
use OpenStudio\UxShowcaseBundle\Preview\SignedPreviewUrlGenerator;
use OpenStudio\UxShowcaseBundle\Preview\UnsupportedPostException;
use OpenStudio\UxShowcaseBundle\Props\ComponentDocumentation;
use OpenStudio\UxShowcaseBundle\Screen\Requirements;
use OpenStudio\UxShowcaseBundle\ShowcaseRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Twig\Environment;

/**
 * The preview of the values a visitor tried: the example is drawn again in the bare page with the props it was posted,
 * and an address that renews the signed one of a page left open.
 *
 * The POST goes to the path of the bare page of the example (`ux_showcase_preview`, GET) with the query `locale`,
 * `expires` and `signature` that PreviewUrlGeneratorInterface gives. In this order: the access service (every route of
 * the bundle), the signature (404), the origin and the CSRF token (403), then the values (422).
 */
final readonly class TriedPreviewController
{
    public function __construct(
        private ShowcaseRegistry $registry,
        private ComponentDocumentation $documentation,
        private SignedPreviewUrlGenerator $signer,
        private PreviewUrlGeneratorInterface $urls,
        private PreviewRequestGuard $guard,
        private PostedValuesResolver $values,
        private BarePage $page,
        private Environment $twig,
        private ?LocaleAwareInterface $translator = null,
    ) {
    }

    #[Route(
        '/{library}/{component}/{slug}/preview',
        name: SignedPreviewUrlGenerator::ROUTE,
        requirements: ['library' => Requirements::LIBRARY, 'component' => Requirements::COMPONENT, 'slug' => Requirements::SLUG],
        defaults: [BarePageListener::ROUTE_ATTRIBUTE => true, SignedPreviewListener::ROUTE_ATTRIBUTE => true],
        methods: ['POST'],
    )]
    public function try(Request $request, string $library, string $component, string $slug): Response
    {
        $locale = $request->query->get('locale');
        $expires = $request->query->get('expires');
        $signature = $request->query->get('signature');
        if (!\is_string($locale) || !\is_string($expires) || !\is_string($signature) || !$this->signer->isValid($library, $component, $slug, $locale, $expires, $signature)) {
            throw new NotFoundHttpException('The address is not signed, or not any more.');
        }

        $showcaseLibrary = $this->registry->library($library) ?? throw new NotFoundHttpException(\sprintf('Unknown library "%s".', $library));
        $example = $this->registry->findExample($library, $component, $slug) ?? throw new NotFoundHttpException(\sprintf('Unknown example "%s" of "%s".', $slug, $component));

        $this->guard->assertGenuine($request);

        $request->setLocale($locale);
        $this->translator?->setLocale($locale);

        try {
            $doc = $this->documentation->get($component);
        } catch (UnknownComponentException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        try {
            $props = $this->values->resolve($request, $library, $example, $doc);
        } catch (UnsupportedPostException $exception) {
            throw new UnsupportedMediaTypeHttpException($exception->getMessage(), $exception);
        } catch (InvalidPostedValuesException $exception) {
            return $this->refusal($request, $showcaseLibrary, $example, $exception->errors);
        }

        try {
            return $this->page->render($request, $showcaseLibrary, $example, $props);
        } catch (\Throwable $exception) {
            // The values are of the right types and the component still refuses them (a rule of its own): not a server error.
            // Twig wraps what a component threw with the name of its string template: the cause is what the visitor needs.
            while (null !== $exception->getPrevious()) {
                $exception = $exception->getPrevious();
            }

            return $this->refusal($request, $showcaseLibrary, $example, [new PostedValueError('', \sprintf('The component cannot be drawn with these values: %s', $exception->getMessage()))]);
        }
    }

    /**
     * A signed address for the example, with the token to post to it: a page left open longer than the lifetime of its
     * address asks for new ones here.
     */
    #[Route(
        '/{library}/{component}/{slug}/preview-link',
        name: 'ux_showcase_preview_link',
        requirements: ['library' => Requirements::LIBRARY, 'component' => Requirements::COMPONENT, 'slug' => Requirements::SLUG],
        defaults: [SignedPreviewListener::ROUTE_ATTRIBUTE => true],
        methods: ['GET'],
    )]
    public function link(Request $request, string $library, string $component, string $slug): Response
    {
        $this->registry->library($library) ?? throw new NotFoundHttpException(\sprintf('Unknown library "%s".', $library));
        $example = $this->registry->findExample($library, $component, $slug) ?? throw new NotFoundHttpException(\sprintf('Unknown example "%s" of "%s".', $slug, $component));

        $url = $this->urls->generate($library, $example, $request->getLocale());
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);

        return new JsonResponse([
            'url' => $url,
            'token' => $this->guard->token(),
            'expires' => isset($query['expires']) && \is_string($query['expires']) ? (int) $query['expires'] : null,
        ]);
    }

    /**
     * @param list<PostedValueError> $errors
     */
    private function refusal(Request $request, ComponentLibraryInterface $library, Example $example, array $errors): Response
    {
        if (str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return new JsonResponse(['errors' => array_map(static fn (PostedValueError $error): array => ['path' => $error->path, 'message' => $error->message], $errors)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $markup = $this->twig->render('@OpenStudioUxShowcase/preview-errors.html.twig', ['errors' => $errors]);

        return $this->page->withContent($request, $library, $example, $markup, Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}

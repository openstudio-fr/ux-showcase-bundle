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

namespace OpenStudio\UxShowcaseBundle\Preview;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * What a POST of tried values has to prove beyond the signature of its address: it comes from a page of this origin
 * (`Sec-Fetch-Site: same-origin` when the browser sends it, else the host of `Origin` is the host of the request) and it
 * carries the CSRF token, in the `X-CSRF-Token` header or the `_token` field. The access service is checked before, by
 * the listener of the routes of the bundle.
 *
 * @internal
 */
final readonly class PreviewRequestGuard
{
    public const TOKEN_ID = 'ux_showcase_preview';
    public const TOKEN_HEADER = 'X-CSRF-Token';
    public const TOKEN_FIELD = '_token';

    public function __construct(private ?CsrfTokenManagerInterface $tokens = null)
    {
    }

    public function token(): string
    {
        return $this->manager()->getToken(self::TOKEN_ID)->getValue();
    }

    /**
     * @throws AccessDeniedHttpException
     */
    public function assertGenuine(Request $request): void
    {
        $this->assertSameOrigin($request);

        $value = $request->headers->get(self::TOKEN_HEADER) ?? $request->request->get(self::TOKEN_FIELD);
        if (!\is_string($value) || !$this->manager()->isTokenValid(new CsrfToken(self::TOKEN_ID, $value))) {
            throw new AccessDeniedHttpException('The CSRF token is missing or wrong.');
        }
    }

    private function assertSameOrigin(Request $request): void
    {
        $site = $request->headers->get('Sec-Fetch-Site');
        if (null !== $site) {
            if ('same-origin' !== $site) {
                throw new AccessDeniedHttpException('The values come from another origin.');
            }

            return;
        }

        $origin = $request->headers->get('Origin');
        $host = \is_string($origin) ? parse_url($origin, \PHP_URL_HOST) : null;
        if (!\is_string($host) || 0 !== strcasecmp($host, $request->getHost())) {
            throw new AccessDeniedHttpException('The origin of the values is missing or not this one.');
        }
    }

    private function manager(): CsrfTokenManagerInterface
    {
        return $this->tokens ?? throw new AccessDeniedHttpException('CSRF protection is not enabled in the application (framework.csrf_protection).');
    }
}

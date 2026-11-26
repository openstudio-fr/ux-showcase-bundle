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

use OpenStudio\UxShowcaseBundle\Example\Example;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The address of the preview of the tried values, signed: HMAC-SHA256 of the library, the component, the slug, the
 * locale and the expiry, with a key derived from `kernel.secret` for this purpose only, valid for an hour and checked
 * with `hash_equals`. The signature does not replace the access check or the CSRF token: it keeps an address from
 * being edited (another example, another locale, a later expiry).
 *
 * @internal the interface is the API
 */
final readonly class SignedPreviewUrlGenerator implements PreviewUrlGeneratorInterface
{
    public const ROUTE = 'ux_showcase_preview_try';
    public const LIFETIME_IN_SECONDS = 3600;
    public const LOCALE_PATTERN = '/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/';

    private const KEY_CONTEXT = 'openstudio.ux_showcase.preview';

    private string $key;

    public function __construct(private UrlGeneratorInterface $urls, string $secret)
    {
        if ('' === $secret) {
            throw new \LogicException('The signed preview needs a "kernel.secret" that is not empty.');
        }

        $this->key = hash_hmac('sha256', self::KEY_CONTEXT, $secret);
    }

    public function generate(string $library, Example $example, string $locale, ?\DateTimeImmutable $now = null): string
    {
        if (null === $example->component) {
            throw new \InvalidArgumentException(\sprintf('The documentation page "%s" has no props to try.', $example->slug));
        }

        if (1 !== preg_match(self::LOCALE_PATTERN, $locale)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a locale.', $locale));
        }

        $expires = ($now ?? new \DateTimeImmutable())->getTimestamp() + self::LIFETIME_IN_SECONDS;

        return $this->urls->generate(self::ROUTE, [
            'library' => $library,
            'component' => $example->component,
            'slug' => $example->slug,
            'locale' => $locale,
            'expires' => $expires,
            'signature' => $this->sign($library, $example->component, $example->slug, $locale, $expires),
        ]);
    }

    public function isValid(string $library, string $component, string $slug, string $locale, string $expires, string $signature, ?\DateTimeImmutable $now = null): bool
    {
        if (1 !== preg_match('/^[0-9]{1,12}$/', $expires) || 1 !== preg_match(self::LOCALE_PATTERN, $locale)) {
            return false;
        }

        if ((int) $expires < ($now ?? new \DateTimeImmutable())->getTimestamp()) {
            return false;
        }

        return hash_equals($this->sign($library, $component, $slug, $locale, (int) $expires), $signature);
    }

    private function sign(string $library, string $component, string $slug, string $locale, int $expires): string
    {
        // JSON, not a separator: two different tuples never give the same message.
        return hash_hmac('sha256', json_encode([$library, $component, $slug, $locale, $expires], \JSON_THROW_ON_ERROR), $this->key);
    }
}

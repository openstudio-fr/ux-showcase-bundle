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

namespace OpenStudio\UxShowcaseBundle\Screen;

use OpenStudio\UxShowcaseBundle\Example\Example;
use OpenStudio\UxShowcaseBundle\Exception\UnknownComponentException;
use OpenStudio\UxShowcaseBundle\Preview\PreviewPrefill;
use OpenStudio\UxShowcaseBundle\Preview\PreviewRequestGuard;
use OpenStudio\UxShowcaseBundle\Preview\PreviewUrlGeneratorInterface;
use OpenStudio\UxShowcaseBundle\Preview\TypedValueConverter;
use OpenStudio\UxShowcaseBundle\Props\ComponentDoc;
use OpenStudio\UxShowcaseBundle\Props\PropDoc;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds the Try form of an example from the props table of its component and what the example sets. No form (null) for
 * a free example, for a component the documentation does not know, and when the application has no CSRF protection:
 * the posts would all be refused.
 *
 * @internal
 */
final readonly class TryPanelFactory
{
    public function __construct(
        private PreviewPrefill $prefill,
        private TryFieldRegistry $fields,
        private TypedValueConverter $converter,
        private PreviewUrlGeneratorInterface $urls,
        private PreviewRequestGuard $guard,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function create(string $library, Example $example, ComponentDoc $doc, string $locale, string $idPrefix): ?TryPanel
    {
        if (null === $example->component || $example->isFree()) {
            return null;
        }

        try {
            $values = $this->prefill->forExample($example);
            $token = $this->guard->token();
            $url = $this->urls->generate($library, $example, $locale);
        } catch (UnknownComponentException|AccessDeniedHttpException|\InvalidArgumentException) {
            return null;
        }

        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        $expires = isset($query['expires']) && \is_string($query['expires']) ? (int) $query['expires'] : null;

        $fields = [];
        $readOnly = [];
        foreach ($doc->props as $prop) {
            $hasValue = \array_key_exists($prop->name, $values);
            $value = $hasValue ? $values[$prop->name] : null;
            if (!$hasValue && $prop->hasDefault) {
                $hasValue = true;
                $value = $this->prefill->normalize($prop->default);
            }

            $type = $this->fields->resolve($prop);
            if (null === $type) {
                $readOnly[] = new TryReadOnlyProp($prop, PropDoc::UNKNOWN_TYPE === $prop->type ? TryReadOnlyProp::REASON_UNTYPED : TryReadOnlyProp::REASON_TYPE, $hasValue || \array_key_exists($prop->name, $example->props), \array_key_exists($prop->name, $example->props) ? $example->props[$prop->name] : $value);
                continue;
            }

            $fields[] = $this->field($prop, $type, $idPrefix, $hasValue, $value, \array_key_exists($prop->name, $values));
        }

        return new TryPanel(
            $url,
            $this->router->generate('ux_showcase_preview_link', ['library' => $library, 'component' => $example->component, 'slug' => $example->slug]),
            $token,
            $expires,
            $idPrefix.'-frame',
            $fields,
            $readOnly,
        );
    }

    private function json(mixed $value, int $flags): string
    {
        return json_encode($value, $flags | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '';
    }

    private function field(PropDoc $prop, TryFieldTypeInterface $type, string $idPrefix, bool $hasValue, mixed $value, bool $sent): TryField
    {
        $shape = $this->converter->shape($prop);
        $id = $idPrefix.'-try-'.$prop->name;
        $hintId = $id.'-hint';
        $errorId = $id.'-error';

        $choices = [];
        foreach ($shape->choices ?? [] as $choice) {
            $choices[] = [
                'value' => $choice,
                'label' => \is_string($choice) ? $choice : json_encode($choice, \JSON_THROW_ON_ERROR),
                'json' => json_encode($choice, \JSON_THROW_ON_ERROR),
                'selected' => $hasValue && $choice === $value,
            ];
        }

        return new TryField(
            $prop,
            $prop->name,
            $id,
            $errorId,
            $hintId,
            trim(('' !== $prop->description ? $hintId.' ' : '').$errorId),
            $type->getName(),
            $type->getTemplate(),
            $type->getModuleUrl(),
            $shape->kind ?? '',
            $shape->nullable ?? PropDoc::acceptsNull($prop->type),
            $shape->numeric ?? false,
            $choices,
            $hasValue,
            $value,
            $hasValue ? $this->json($value, \JSON_PRETTY_PRINT) : '',
            $hasValue ? $this->json($value, 0) : '',
            $sent,
        );
    }
}

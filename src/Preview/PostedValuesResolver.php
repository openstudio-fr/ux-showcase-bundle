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
use OpenStudio\UxShowcaseBundle\Props\ComponentDoc;
use Symfony\Component\HttpFoundation\Request;

/**
 * From the request of a preview to the props the example is drawn with: the posted values (JSON object or form fields),
 * run through the PostedValuesNormalizerInterface steps of the host, then only the props of the table are let through,
 * each converted to its declared type; the example's own props stand for every key that was not posted.
 *
 * @internal
 */
final readonly class PostedValuesResolver
{
    /**
     * @param iterable<PostedValuesNormalizerInterface> $normalizers highest priority first
     */
    public function __construct(
        private TypedValueConverter $converter,
        private iterable $normalizers,
    ) {
    }

    /**
     * @return array<string, mixed> the props to draw the example with
     *
     * @throws InvalidPostedValuesException with every unknown key and every invalid value
     * @throws UnsupportedPostException     when the body is neither JSON nor a form
     */
    public function resolve(Request $request, string $library, Example $example, ComponentDoc $doc): array
    {
        $fromForm = !$this->isJson($request);
        $values = $fromForm ? $this->form($request) : $this->json($request);

        $context = new PostedValuesContext($library, $example, $doc, $request, $fromForm);
        foreach ($this->normalizers as $normalizer) {
            $values = $normalizer->normalize($values, $context);
        }

        $errors = [];
        $converted = [];
        foreach ($values as $name => $value) {
            $prop = $doc->prop((string) $name);
            if (null === $prop) {
                $errors[] = new PostedValueError((string) $name, \sprintf('"%s" is not a prop of %s.', $name, $doc->name));
                continue;
            }

            try {
                $converted[$prop->name] = $this->converter->convert($value, $prop, $fromForm);
            } catch (InvalidPostedValuesException $exception) {
                array_push($errors, ...$exception->errors);
            }
        }

        if ([] !== $errors) {
            throw new InvalidPostedValuesException($errors);
        }

        return $converted + $example->props;
    }

    private function isJson(Request $request): bool
    {
        return 'json' === $request->getContentTypeFormat();
    }

    /**
     * @return array<string, mixed>
     */
    private function form(Request $request): array
    {
        if (!\in_array($request->getContentTypeFormat(), ['form', null], true)) {
            throw new UnsupportedPostException('Post JSON (application/json) or a form (application/x-www-form-urlencoded).');
        }

        /** @var array<string, mixed> $values the fields of a form are named */
        $values = $request->request->all();
        unset($values[PreviewRequestGuard::TOKEN_FIELD]);

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Request $request): array
    {
        try {
            $values = json_decode($request->getContent(), true, 64, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw InvalidPostedValuesException::at('', 'The body is not valid JSON: '.$exception->getMessage());
        }

        if (!\is_array($values) || ([] !== $values && array_is_list($values))) {
            throw InvalidPostedValuesException::at('', 'The body must be a JSON object of prop names and values.');
        }

        /** @var array<string, mixed> $values */
        return $values;
    }
}

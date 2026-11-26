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

namespace OpenStudio\UxShowcaseBundle\Props;

/**
 * Cuts the text of a comment into a type and a description: `list<{name: string, id: int}> The rows`.
 *
 * A type is one word, unless it opens a `<`, a `{` or a `(` that has to be closed, or continues after a `|`.
 *
 * @internal
 */
final class TypeSplitter
{
    /**
     * @return array{string, string} the type, then the description on one line
     */
    public static function split(string $text): array
    {
        $text = trim($text);
        $length = \strlen($text);
        $depth = 0;

        for ($i = 0; $i < $length; ++$i) {
            $char = $text[$i];
            if (\in_array($char, ['<', '{', '('], true)) {
                ++$depth;
            } elseif (\in_array($char, ['>', '}', ')'], true)) {
                --$depth;
            } elseif (0 >= $depth && ctype_space($char)) {
                $rest = ltrim(substr($text, $i));
                // `string | null`: the union goes on.
                if (!str_starts_with($rest, '|') && !str_ends_with(rtrim(substr($text, 0, $i)), '|')) {
                    break;
                }
            }
        }

        // A type that never closes is the first word.
        if ($depth > 0) {
            $i = strcspn($text, " \t\r\n");
        }

        return [preg_replace('/\s*\|\s*/', '|', substr($text, 0, $i)) ?? '', self::oneLine(substr($text, $i))];
    }

    public static function oneLine(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }
}

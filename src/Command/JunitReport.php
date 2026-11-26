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

/**
 * The outcomes as a JUnit XML report: one testsuite per library, one testcase per example, which is what the test
 * report of GitLab reads.
 *
 * @internal
 */
final class JunitReport
{
    /**
     * @param list<CheckOutcome> $outcomes
     */
    public static function render(array $outcomes): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElement('testsuites');
        $document->appendChild($root);
        $root->setAttribute('name', 'ux-showcase:check');

        $byLibrary = [];
        foreach ($outcomes as $outcome) {
            $byLibrary[$outcome->library][] = $outcome;
        }

        $totals = ['tests' => 0, 'failures' => 0, 'skipped' => 0, 'time' => 0.0];
        foreach ($byLibrary as $library => $libraryOutcomes) {
            $suite = $document->createElement('testsuite');
            $root->appendChild($suite);
            $counts = ['tests' => 0, 'failures' => 0, 'skipped' => 0, 'time' => 0.0];

            foreach ($libraryOutcomes as $outcome) {
                $case = $document->createElement('testcase');
                $suite->appendChild($case);
                $case->setAttribute('classname', $library.'.'.($outcome->component ?? '_docs'));
                $case->setAttribute('name', $outcome->slug);
                $case->setAttribute('time', \sprintf('%.4f', $outcome->seconds));

                ++$counts['tests'];
                $counts['time'] += $outcome->seconds;
                if (CheckOutcome::FAILED === $outcome->status) {
                    ++$counts['failures'];
                    $failure = $document->createElement('failure');
                    $failure->setAttribute('message', self::clean((string) $outcome->message));
                    $failure->appendChild($document->createTextNode(self::clean((string) $outcome->message)));
                    $case->appendChild($failure);
                } elseif (CheckOutcome::SKIPPED === $outcome->status) {
                    ++$counts['skipped'];
                    $skipped = $document->createElement('skipped');
                    $skipped->setAttribute('message', self::clean((string) $outcome->message));
                    $case->appendChild($skipped);
                }
            }

            $suite->setAttribute('name', $library);
            $suite->setAttribute('tests', (string) $counts['tests']);
            $suite->setAttribute('failures', (string) $counts['failures']);
            $suite->setAttribute('skipped', (string) $counts['skipped']);
            $suite->setAttribute('time', \sprintf('%.4f', $counts['time']));
            foreach ($counts as $key => $value) {
                $totals[$key] += $value;
            }
        }

        $root->setAttribute('tests', (string) $totals['tests']);
        $root->setAttribute('failures', (string) $totals['failures']);
        $root->setAttribute('skipped', (string) $totals['skipped']);
        $root->setAttribute('time', \sprintf('%.4f', $totals['time']));

        return (string) $document->saveXML();
    }

    /**
     * XML 1.0 refuses control characters and invalid UTF-8, which an error message can hold.
     */
    private static function clean(string $text): string
    {
        return (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', mb_scrub($text, 'UTF-8'));
    }
}

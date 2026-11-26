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

namespace OpenStudio\UxShowcaseBundle\Tests\Unit;

use OpenStudio\UxShowcaseBundle\Example\Example;
use OpenStudio\UxShowcaseBundle\Example\ExampleProviderInterface;
use OpenStudio\UxShowcaseBundle\Exception\UnknownComponentException;
use OpenStudio\UxShowcaseBundle\ShowcaseRegistry;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\ServiceLibrary;
use PHPUnit\Framework\TestCase;

final class SharedProviderTest extends TestCase
{
    public function testASharedProviderExampleForAComponentNoLibraryListsFails(): void
    {
        $registry = new ShowcaseRegistry([new ServiceLibrary()], [$this->provider(new Example('Nope:Thing', 'one'))]);

        $this->expectException(UnknownComponentException::class);
        $this->expectExceptionMessage('The example "one" targets the component "Nope:Thing", which no library lists.');
        $registry->libraries();
    }

    public function testASharedProviderFeedsEveryLibraryThatListsTheComponent(): void
    {
        $registry = new ShowcaseRegistry([new ServiceLibrary()], [$this->provider(new Example('Other:Note', 'extra', props: ['text' => 'More']))]);

        self::assertSame(['hello', 'extra'], array_map(static fn ($example): string => $example->slug, $registry->examples('other', 'Other:Note')));
    }

    private function provider(Example $example): ExampleProviderInterface
    {
        return new class($example) implements ExampleProviderInterface {
            public function __construct(private readonly Example $example)
            {
            }

            public function getExamples(): iterable
            {
                yield $this->example;
            }
        };
    }
}

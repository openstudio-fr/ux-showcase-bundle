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

namespace OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Example;

use OpenStudio\UxShowcaseBundle\Example\Attribute\AsShowcaseExample;
use OpenStudio\UxShowcaseBundle\Example\FixtureLoader;

#[AsShowcaseExample(component: 'Demo:Layout:Card', slug: 'article', title: 'Article from a fixture')]
final readonly class ArticleCard
{
    public function __construct(private FixtureLoader $fixtures)
    {
    }

    /**
     * @return array<mixed>
     */
    public function __invoke(): array
    {
        return $this->fixtures->load('cards/article.json');
    }
}

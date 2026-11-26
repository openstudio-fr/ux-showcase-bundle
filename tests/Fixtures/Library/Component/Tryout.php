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

namespace OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Component;

use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\Layout;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

#[AsTwigComponent('Props:Tryout', template: 'showcase/tryout.html.twig')]
final class Tryout
{
    /** Text shown in the box. */
    public string $label = '';

    public bool $enabled = false;

    public int $count = 3;

    public float $ratio = 0.5;

    public string $size = 'md';

    public ?Layout $layout = null;

    public ?string $note = null;

    /** @var list<string> */
    public array $tags = [];

    public ?string $picture = null;

    public ?object $handler = null;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    #[PreMount]
    public function validate(array $data): array
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['size' => 'md']);
        $resolver->setAllowedValues('size', ['sm', 'md', 'lg']);
        $resolver->setIgnoreUndefined();

        /** @var array<string, mixed> $resolved */
        $resolved = $resolver->resolve($data);

        return $resolved + $data;
    }
}

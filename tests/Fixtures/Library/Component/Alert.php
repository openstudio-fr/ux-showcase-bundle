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

use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\VehicleDto;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * A box that shows a message.
 */
#[AsTwigComponent('Props:Alert', template: 'showcase/alert.html.twig')]
final class Alert
{
    /** The message shown in the box. */
    public string $message;

    public string $type = 'info';

    public ?string $icon = null;

    // Computed in mount() from $heading: not a prop.
    public string $slug = '';

    #[ExposeInTemplate]
    private string $label = '';

    #[ExposeInTemplate('shout')]
    public function shout(): string
    {
        return strtoupper($this->label);
    }

    /**
     * @param string                $heading  Title of the box
     * @param bool                  $closable shows a close button
     * @param list<VehicleDto>|null $related  Related vehicles
     */
    public function mount(string $heading, bool $closable = true, ?array $related = null): void
    {
        $this->slug = strtolower($heading);
        $this->label = $closable ? $heading : '';
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    #[PreMount]
    public function validate(array $data): array
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(['type' => 'info']);
        $resolver->setAllowedValues('type', ['info', 'warning', 'error']);
        $resolver->setAllowedValues('icon', range(0, 3));
        $resolver->setAllowedValues('message', [self::class, 'unused']);
        $resolver->setIgnoreUndefined();

        /** @var array<string, mixed> $resolved */
        $resolved = $resolver->resolve($data);

        return $resolved + $data;
    }
}

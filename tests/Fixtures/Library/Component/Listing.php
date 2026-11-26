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
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Dto\VehicleCard;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

/**
 * Props of every kind a visitor can try: an object, a list of objects, an enum, a date, a number, a union.
 * An example gives them as plain data; PreMount turns them into objects, as the application would.
 */
#[AsTwigComponent('Props:Listing', template: 'showcase/listing.html.twig')]
final class Listing
{
    public string $title = '';

    public VehicleCard $vehicle;

    /** @var list<VehicleCard> */
    public array $others = [];

    public Layout $layout = Layout::Grid;

    public ?\DateTimeImmutable $since = null;

    public int|string $limit = 12;

    public mixed $payload = null;

    /**
     * @param array{title?: string, vehicle?: mixed, others?: list<mixed>, layout?: mixed, since?: mixed, limit?: mixed} $data
     *
     * @return array<string, mixed>
     */
    #[PreMount]
    public function hydrate(array $data): array
    {
        if (isset($data['limit']) && \is_int($data['limit']) && $data['limit'] < 0) {
            throw new \InvalidArgumentException('The limit must not be negative.');
        }

        $data['vehicle'] = self::card($data['vehicle'] ?? ['name' => '']);
        $data['others'] = array_map(self::card(...), $data['others'] ?? []);
        if (isset($data['layout']) && \is_string($data['layout'])) {
            $data['layout'] = Layout::from($data['layout']);
        }
        if (isset($data['since']) && \is_string($data['since'])) {
            $data['since'] = new \DateTimeImmutable($data['since']);
        }

        return $data;
    }

    private static function card(mixed $card): VehicleCard
    {
        if ($card instanceof VehicleCard) {
            return $card;
        }

        /** @var array{name: string, price?: float, tags?: list<string>, layout?: string, since?: string} $card */
        return new VehicleCard(
            $card['name'],
            $card['price'] ?? 0.0,
            $card['tags'] ?? [],
            isset($card['layout']) ? Layout::from($card['layout']) : Layout::Grid,
            isset($card['since']) ? new \DateTimeImmutable($card['since']) : null,
        );
    }
}

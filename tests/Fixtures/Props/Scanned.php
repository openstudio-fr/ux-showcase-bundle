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

namespace OpenStudio\UxShowcaseBundle\Tests\Fixtures\Props;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Methods read by MethodSourceScanner, never called.
 */
final class Scanned
{
    public string $plain = '';
    /** @var list<string> */
    public array $list = [];
    /** @var array<string, array<string, string>> */
    public array $map = [];
    public string $text = '';
    public int $count = 0;
    public ?string $maybe = null;
    public string $untouched = '';

    public function mount(string $value): void
    {
        $this->plain = $value;
        $this->list[] = $value;
        $this->map['a']['b'] = $value;
        $this->text .= $value;
        $this->count += 2;
        $this->maybe ??= $value;
        $other = $this->untouched;
        if ($this->plain === $other) {
            return;
        }
    }

    public function options(OptionsResolver $resolver): void
    {
        $resolver->setAllowedValues('size', ['s', 'm', 'l']);
        $resolver->setAllowedValues('level', [1, 2, -3]);
        $resolver->setAllowedValues('ratio', [0.5, 1.5]);
        $resolver->setAllowedValues('flag', [true, false, null]);
        $resolver->setAllowedValues('computed', range(1, 3));
        $resolver->setAllowedValues('constant', [self::class]);
        $resolver->setAllowedValues('keyed', ['a' => 1]);
        $resolver->setAllowedValues('nested', [[1]]);
        $resolver->setAllowedTypes('size', 'string');
    }
}

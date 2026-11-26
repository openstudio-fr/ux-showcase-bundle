<?php

declare(strict_types=1);

$header = <<<'HEADER'
This file is part of the openstudio/ux-showcase-bundle package.

(c) OpenStudio

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
HEADER;

$finder = (new PhpCsFixer\Finder())->in([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/config']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PHP83Migration' => true,
        '@PHP82Migration:risky' => true,
        'declare_strict_types' => true,
        'header_comment' => ['header' => $header, 'location' => 'after_declare_strict'],
        'phpdoc_to_comment' => false,
    ])
    ->setFinder($finder);

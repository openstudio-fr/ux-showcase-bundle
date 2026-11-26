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

use OpenStudio\UxShowcaseBundle\EventListener\ShowcaseAccessListener;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    // The flag is what puts a route under the access check and the robots header: every route of the directory gets it.
    $routes->import(__DIR__.'/../src/Controller/', 'attribute')
        ->defaults([ShowcaseAccessListener::ROUTE_ATTRIBUTE => true]);
};

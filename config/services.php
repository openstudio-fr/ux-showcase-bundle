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

use OpenStudio\UxShowcaseBundle\Access\ShowcaseAccessInterface;
use OpenStudio\UxShowcaseBundle\Controller\IndexController;
use OpenStudio\UxShowcaseBundle\EventListener\ShowcaseAccessListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(IndexController::class)
        ->autowire()
        ->tag('controller.service_arguments');

    $services->set(ShowcaseAccessListener::class)
        ->args([service(ShowcaseAccessInterface::class)])
        ->tag('kernel.event_listener', ['event' => 'kernel.controller', 'method' => 'onController'])
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onResponse']);
};

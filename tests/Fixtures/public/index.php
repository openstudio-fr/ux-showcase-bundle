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

/*
 * Local front controller: serves the test library at /_showcase/ so the screens can be seen in a browser.
 * Debug is on. Run it with `php -S 127.0.0.1:8000 tests/Fixtures/public/index.php`, or require it from an
 * index.php at the root of the clone (not committed) to have DDEV serve it.
 */

use OpenStudio\UxShowcaseBundle\Tests\Fixtures\Library\Configuration;
use OpenStudio\UxShowcaseBundle\Tests\Fixtures\TestKernel;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$kernel = new TestKernel(true, Configuration::demo(), withLibrary: true, cleanCache: false);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);

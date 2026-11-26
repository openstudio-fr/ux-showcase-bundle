# UX Showcase Bundle

A showcase of Twig components, served by the application itself: browse the components of a project, see their
examples at several widths and on several backgrounds, read their props and their code, and try them with other
values. It replaces Storybook in Symfony projects, with no Node server.

Status: in development, not released. The first version is tracked in issue #1.

## What the first version does

- Lists the components of each library (a project, a theme, a back office), grouped and ordered, with a status.
- Shows a component: its examples, a preview at each width and on each background, its code, the table of its props.
- Reads props from `{% props %}`, from `##` and `{# @prop #}` comments, and from the public properties of a
  component class.
- Lets you try a component with other values, objects and lists included, through a signed preview.
- Checks in CI that every example renders, with a console command.
- Is open in debug only by default; a role or another access rule can be configured.

Libraries plug in as providers: Symfony projects, the Flexy theme of Thelia, the Phoenix back office and the
OpenStudioCMS bundle.

## Requirements

- PHP 8.3 or later
- Symfony 7.4
- `symfony/ux-twig-component` 2.36 or later
- Twig 3.29 or later

## Installation

```bash
composer require --dev openstudio/ux-showcase-bundle
```

Register the bundle in `config/bundles.php`, in the `dev` environment only or in all of them:

```php
return [
    // ...
    OpenStudio\UxShowcaseBundle\OpenStudioUxShowcaseBundle::class => ['dev' => true],
];
```

Import its routes with the prefix you want, `/_showcase` being the advised one (`config/routes/ux_showcase.php`):

```php
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import('@OpenStudioUxShowcaseBundle/config/routes.php')->prefix('/_showcase');
};
```

Configuration (`config/packages/ux_showcase.yaml`), every key is optional:

```yaml
ux_showcase:
    title: Components            # title of the screen
    logo: /build/logo.svg        # asset path or URL, none by default
    viewports:                   # preview widths in pixels, null for a responsive preview; replaces the default list
        responsive: ~
        mobile: 375
        tablet: 768
        desktop: 1280
    backgrounds:                 # preview backgrounds, name => CSS colour; replaces the default list
        light: '#f8f9fa'
        white: '#ffffff'
        dark: '#212529'
    access:
        role: null               # a Symfony role that opens the showcase outside debug
        service: null            # id of your own ShowcaseAccessInterface service
```

### Access

The showcase is a developer tool: it answers only when `kernel.debug` is on. Anywhere else, and for every route of the
bundle, a refused request gets a 404 (never a 403, so its existence is not advertised), and every response carries
`X-Robots-Tag: noindex, nofollow`. The check runs once, in a listener scoped to the routes of the bundle.

- `access.role`: also granted to a user who has this role. It needs `symfony/security-bundle`; without it the container
  does not compile.
- `access.service`: the id of a service implementing `OpenStudio\UxShowcaseBundle\Access\ShowcaseAccessInterface`
  (`isGranted(Request $request): bool`). It replaces the default rule entirely. Do the check in this service rather
  than in an `access_control` rule: a host without a Symfony firewall on the path, as Thelia has on `/admin`, would
  protect nothing.

Thelia: see the Thelia section (coming). The access service is the place to open the showcase to back-office
administrators.

The screen is in English; a French translation is provided in the `UxShowcaseBundle` translation domain. Its styles
and scripts are served with the page, with no dependency on the asset pipeline of the application.

## Quality

```bash
composer check   # php-cs-fixer, PHPStan level 10, PHPUnit
```

## License

LGPL-3.0-or-later. See `LICENSE`, which completes the GNU GPL v3 in `LICENSE.GPL`.

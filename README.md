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
    fixtures_directory: ~        # JSON fixtures of PHP examples, loaded by relative path
    preview:                     # last level of the preview options, see "Preview options"
        background: ~            # a key of `backgrounds`
        body_class: ~
        wrapper: true
        height: ~                # pixels
    libraries: {}                # see "Libraries"
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

## Libraries and examples

The public API is what carries an `@api` tag: `ComponentLibraryInterface`, `ComponentReference`, `ComponentStatus`,
`ComponentGroup`, `ShowcaseRegistry`, `Example`, `PreviewOptions`, `ResolvedPreview`, `ExampleProviderInterface`,
`AsShowcaseExample`, `FixtureLoader`, `ExampleCodeGenerator`, `ExampleRenderer`. Everything else is `@internal`. The
package is 0.x: a minor version may break the API.

### A library in configuration

A Symfony project needs no PHP for the simple case.

```yaml
ux_showcase:
    fixtures_directory: '%kernel.project_dir%/showcase/fixtures'
    libraries:
        app:
            label: Application
            prefix: App                           # covers App:Button, App:Form:Input, ... (every component when null)
            examples: '%kernel.project_dir%/showcase/examples'
            groups: [General, Form, Layout, Foundations]   # display order; the others come after, alphabetically
            wrapper: showcase/wrapper.html.twig   # surrounds every example, see "Wrapper"
            bare: showcase/bare.html.twig         # bare page of the preview (used by the preview screen)
            preview: { background: light, wrapper: true }
            statuses:                             # ready by default
                'App:Form:Date': waiting
                'App:Legacy:Old': hidden
```

Components are discovered from TwigComponent, anonymous ones (templates under a `components/` directory of a Twig path)
and class ones. The name is the TwigComponent name, `:` separated; its group is the name below the prefix, without the
last segment (`App:Form:Input` is in `Form`, `App:Button` in `General`). A component name is used as is in URLs, where
`:` is legal; library names and example slugs match `[a-z0-9]+(-[a-z0-9]+)*`.

A library that needs PHP implements `OpenStudio\UxShowcaseBundle\Library\ComponentLibraryInterface`: autoconfigured
(tag `ux_showcase.library`). It lists its `ComponentReference` (name, group, `ComponentStatus` `Ready`, `Waiting` or
`Hidden`, label), its group order, its example providers, its wrapper and bare templates, its preview options.

### Twig example files

One file per component under the `examples` directory: `Form/Input.showcase.twig` holds the examples of `App:Form:Input`
(the path below the prefix, `/` for `:`).

```twig
{# showcase/examples/Button.showcase.twig: examples of App:Button #}
{% showcase_example 'primary' with { label: 'Save' } %}
{% end_showcase_example %}

{% showcase_example 'danger' titled 'Dangerous action' with { label: 'Delete', variant: 'danger' } options { background: 'dark', height: 120 } %}
{% end_showcase_example %}

{% showcase_example 'with-icon' with { label: 'Send' } options { wrapper: false } %}
    <span class="icon">&rarr;</span>
    <twig:block name="footer">Sent</twig:block>
{% end_showcase_example %}
```

- `{% showcase_example 'slug' [titled 'Title'] [with { props }] [options { ... }] %}...{% end_showcase_example %}`;
  the parts after the slug come in any order, each once. The slug is unique within the component; the title defaults to
  the slug, capitalised.
- Props and options are literals only (strings, numbers, `true`, `false`, `null`, lists, hashes): a variable or a call
  is refused. The form of the preview is filled from them and the code shown is generated from them.
- The body is the markup between the tags of the component, named blocks included (`<twig:block name="...">`). It is
  optional, and it is read as text: it is rendered by the Twig environment of the application, so it may hold anything
  the application knows.
- Options: `background` (a key of `backgrounds`), `body_class`, `wrapper` (`true` or `false`), `height` (pixels).

A free example is a Twig snippet with no props, shown without a form (a whole page, a composition):

```twig
{% showcase_free 'login-page' titled 'Login page' options { wrapper: false } %}
    <twig:Layout:Page>...</twig:Layout:Page>
{% end_showcase_free %}
```

A documentation page (colours, typography, icons) is a free example tied to no component: a file in
`_docs/<Group>/<name>.showcase.twig` holds `showcase_free` tags, listed under the group `<Group>`.

### PHP examples

For props that come from data, mark a class (its `__invoke()`) or a method with `#[AsShowcaseExample]`; it is
autoconfigured, so declaring it as a service is enough. It returns the props, plain data only.

```php
use OpenStudio\UxShowcaseBundle\Example\Attribute\AsShowcaseExample;
use OpenStudio\UxShowcaseBundle\Example\FixtureLoader;

#[AsShowcaseExample(component: 'Demo:Layout:Card', slug: 'article', title: 'Article from a fixture')]
final readonly class ArticleCard
{
    public function __construct(private FixtureLoader $fixtures)
    {
    }

    /** @return array<mixed> */
    public function __invoke(): array
    {
        return $this->fixtures->load('cards/article.json'); // relative to ux_showcase.fixtures_directory
    }
}
```

An example of a component that no library lists, a duplicate slug, a prop that is not plain data: each fails when the
registry is first used, with a message that names it. Another source of examples implements
`ExampleProviderInterface`: registered as a service, it feeds every library that lists the component of an example;
returned by `getExampleProviders()` of a library, it feeds that library only.

### Wrapper and preview options

A wrapper is a Twig template declared once per library (a root modal controller, a live component the examples need).
It receives `content` (the rendered example), `example`, `library` and `preview`:

```twig
<div data-controller="modal-root">{{ content }}</div>
```

Preview options (`background`, `body_class`, `wrapper`, `height`) are decided by the first level that sets a field: the
screen choice, the example, the component (`ComponentReference::$preview`), the library, then `ux_showcase.preview`.
The wrapper is on unless a level turns it off.

### Rendering, code, registry

- `ShowcaseRegistry`: libraries, groups, components (hidden ones absent), examples, documentation pages, `findExample()`.
- `ExampleRenderer::render($library, $example, $screenOptions)`: the HTML of an example, in the wrapper when it is on.
  A live component needs a current request.
- `ExampleCodeGenerator::generate($example)`: `<twig:App:Button label="Save" :disabled="true" />`, props in declared
  order; a free example gives its source.

## Quality

```bash
composer check   # php-cs-fixer, PHPStan level 10, PHPUnit
```

## License

LGPL-3.0-or-later. See `LICENSE`, which completes the GNU GPL v3 in `LICENSE.GPL`.

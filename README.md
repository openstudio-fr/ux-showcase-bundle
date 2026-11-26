# UX Showcase Bundle

A showcase of Twig components, served by the application itself: browse the components of a project, see their
examples at several widths and on several backgrounds, read their props and their code, and try them with other
values. It replaces Storybook in Symfony projects, with no Node server.

Status: 0.x. The public API may still change between minor versions.

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
    backgrounds:                 # preview backgrounds, replaces the default list; a CSS colour, or a colour with attributes
        light: '#f8f9fa'
        white: '#ffffff'
        dark: { color: '#212529', html_attributes: { data-bs-theme: dark } }
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
`AsShowcaseExample`, `FixtureLoader`, `ExampleCodeGenerator`, `ExampleRenderer`, `PreviewUrlGeneratorInterface`, `PostedValuesNormalizerInterface`, `PostedValuesContext`, `PostedValueError`, `PreviewPrefill`, `ComponentCatalog` (`names()` and `exists()`), `TwigExampleFileProvider`. Everything else is `@internal`. The
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
            needs_data: ['App:Cart']              # components the check command skips, see "Checking every example"
            statuses:                             # ready by default
                'App:Form:Date': waiting
                'App:Legacy:Old': hidden
```

Components are discovered from TwigComponent, anonymous ones (templates under a `components/` directory of a Twig path)
and class ones. The name is the TwigComponent name, `:` separated; its group is the name below the prefix, without the
last segment (`App:Form:Input` is in `Form`, `App:Button` in `General`). A component name is used as is in URLs, where
`:` is legal; library names and example slugs match `[a-z0-9]+(-[a-z0-9]+)*`.

### A library in PHP

A library that needs PHP implements `OpenStudio\UxShowcaseBundle\Library\ComponentLibraryInterface`. It is autoconfigured
(tag `ux_showcase.library`): no service definition is needed beyond the usual autowiring of your `src/`. It lists its
`ComponentReference` (name, group, `ComponentStatus` `Ready`, `Waiting` or `Hidden`, label), its group order, its example
providers, its wrapper and bare templates and its preview options.

`ComponentCatalog` lists the components TwigComponent knows, and `TwigExampleFileProvider` reads a directory of
`.showcase.twig` files (see "Twig example files"). A library can reuse both and decide the rest: the groups, the labels,
which components it shows.

```php
use OpenStudio\UxShowcaseBundle\Example\PreviewOptions;
use OpenStudio\UxShowcaseBundle\Example\TwigExampleFileProvider;
use OpenStudio\UxShowcaseBundle\Library\ComponentCatalog;
use OpenStudio\UxShowcaseBundle\Library\ComponentLibraryInterface;
use OpenStudio\UxShowcaseBundle\Library\ComponentReference;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class AppLibrary implements ComponentLibraryInterface
{
    public function __construct(
        private ComponentCatalog $catalog,
        #[Autowire('%kernel.project_dir%/showcase/examples')]
        private string $examplesDirectory,
    ) {
    }

    public function getName(): string
    {
        return 'app';
    }

    public function getLabel(): string
    {
        return 'Application';
    }

    public function getComponents(): array
    {
        $references = [];
        foreach ($this->catalog->names() as $name) {
            if (!str_starts_with($name, 'App:')) {
                continue;
            }

            $segments = explode(':', substr($name, 4));
            // App:Button:Base is labelled "Button"
            $label = 'Base' === end($segments) && \count($segments) > 1 ? $segments[0] : null;
            $group = str_contains($name, ':Form:') ? 'Forms' : 'General';

            $references[] = new ComponentReference($name, $group, label: $label);
        }

        return $references;
    }

    public function getGroups(): array
    {
        return ['General', 'Forms'];
    }

    public function getExampleProviders(): iterable
    {
        yield new TwigExampleFileProvider($this->examplesDirectory);
    }

    public function getWrapperTemplate(): ?string
    {
        return null;
    }

    public function getBareTemplate(): ?string
    {
        return null;
    }

    public function getPreviewOptions(): PreviewOptions
    {
        return new PreviewOptions();
    }
}
```

Without a component prefix, `TwigExampleFileProvider` expects the full component name as a path: `App/Form/Input.showcase.twig`
holds the examples of `App:Form:Input`. Pass the prefix as second argument to start the paths below it, as the
`examples` option does.

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

The attribute takes the preview options of a Twig example as named arguments: `background`, `bodyClass`, `wrapper`,
`height`, `needsData`. Left out, an option is not decided here and the next level (component, library, global) answers.

```php
#[AsShowcaseExample(component: 'Demo:Layout:Card', slug: 'article', bodyClass: 'bg-light p-4', height: 320, needsData: true)]
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

Preview options (`background`, `body_class`, `wrapper`, `height`, `needs_data`) are decided by the first level that sets a field: the
screen choice, the example, the component (`ComponentReference::$preview`), the library, then `ux_showcase.preview`.
The wrapper is on unless a level turns it off.

### Rendering, code, registry

- `ShowcaseRegistry`: libraries, groups, components (hidden ones absent), examples, documentation pages, `findExample()`.
- `ExampleRenderer::render($library, $example, $screenOptions)`: the HTML of an example, in the wrapper when it is on.
  A live component needs a current request.
- `ExampleCodeGenerator::generate($example)`: `<twig:App:Button label="Save" :disabled="true" />`, props in declared
  order; a free example gives its source.

## Screens

Every route sits under the prefix you imported them with, behind the access check (a 404 outside debug).

| Name | Path | Screen |
|---|---|---|
| `ux_showcase_index` | `/` | libraries, then groups: components with a status badge (`ready`, `waiting`; `hidden` ones are absent) and documentation pages |
| `ux_showcase_component` | `/{library}/{component}` | the examples of a component, each with the tabs Preview, Code and Props, and the Try form beside the frame |
| `ux_showcase_doc` | `/{library}/_docs/{slug}` | a documentation page: Preview and Code, no Props |
| `ux_showcase_preview` | `/{library}/{component}/{slug}/preview` | the bare page of one example, loaded by its frame |
| `ux_showcase_preview_link`, `ux_showcase_preview_try` | see "Trying other values" | signed preview of tried values |
| `ux_showcase_doc_preview` | `/{library}/_docs/{slug}/preview` | the bare page of a documentation page |
| `ux_showcase_asset` | `/_assets/{file}` | the script and the style of the screens, served with cache headers |

A component name keeps its `:` in the URL (`/_showcase/app/App:Form:Input`). The width and the background chosen on a
component page are in its query string (`?viewport=mobile&background=dark`), so a link shares them. A background of
`default` leaves each example its own. The Code tab is the generated `<twig:…>` snippet, or the source of a free
example; the Props tab is the table of `ComponentDocumentation`, with the blocks. A free example has no Props tab.

The scripts are standalone: nothing to register in the application (no Stimulus controller), and no inline script, so
a strict content security policy lets them through. Tabs follow the ARIA pattern (arrow keys, Home, End) and every frame
has a title. Without JavaScript every panel is shown and the choices are plain links.

### Bare page

A preview is an `<iframe>` on the bare page of the example: a Twig template per library, in which the host brings its
own styles and scripts (AssetMapper, Encore or Vite: its template decides). The template is `bare:` of the library
(`getBareTemplate()`), else the one of the bundle. A template must define the blocks `head_assets`, `body_attributes`
and `content`, which is what extending the bundle one gives; a template without them is refused with a message that
says so.

```twig
{# templates/showcase/bare.html.twig #}
{% extends '@OpenStudioUxShowcase/bare.html.twig' %}

{% block head_assets %}{{ importmap('app') }}{% endblock %}
```

Variables: `library`, `component` (`ComponentReference`, null for a documentation page), `example`, `content` (the
rendered example, wrapper included, safe markup), `preview` (`ResolvedPreview`), `background` (the `Background` in force
or null), `html_attributes` (what goes on `<html>`: the attributes of the background, its colour as a `style`, its name),
`body_class`. The bundle default, which extends nothing:

```twig
<!doctype html>
<html lang="{{ app.request.locale|default('en') }}"{% for name, value in html_attributes %} {{ name }}="{{ value }}"{% endfor %}>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ example.title }}</title>
    {% block head_assets %}{% endblock %}
</head>
<body{% block body_attributes %}{% if body_class %} class="{{ body_class }}"{% endif %}{% endblock %}>
{% block content %}{{ content }}{% endblock %}
</body>
</html>
```

The bundle adds, by a listener scoped to the two preview routes, a script before `</body>` that reports the height of
the document and its background to the page around the frame (`postMessage`, same origin only), and it switches the
profiler off for that response, so there is no debug toolbar in a frame. A frame follows the height of its content,
except an example that sets `height`: a modal in fixed position has a document of zero height, so give it one
(`options { height: 400 }`).

A background is a CSS colour, or `{ color, html_attributes }` when the page needs more than a colour: a theme mode
(`data-bs-theme="dark"`) is an attribute of `<html>`.

### Extension points

The component page has three Twig blocks, for a host or an add-on to fill. Override the template from
`templates/bundles/OpenStudioUxShowcaseBundle/component.html.twig` and extend the original with `@!`:

```twig
{% extends '@!OpenStudioUxShowcase/component.html.twig' %}

{% block header_actions %}{{ parent() }}...{% endblock %}   {# header of the page #}
{% block below_preview %}...{% endblock %}                  {# under the frame of each example: `view`, `component` #}
{% block below_props %}...{% endblock %}                    {# under the props table of each example #}
```

Buttons come from services implementing `OpenStudio\UxShowcaseBundle\Screen\ComponentActionInterface` (autoconfigured,
tag `ux_showcase.action` with an optional `priority`). Each is asked once for the component (the header of the page,
`$context->example` null) and once for each example; `isVisible()` says where it applies, `getTarget()` returns an
`ActionTarget`: a link, or a form (POST, or hidden fields).

```php
interface ComponentActionInterface
{
    public function getName(): string;                          // unique, stable: data-action="..."
    public function getLabel(): string;                         // already translated
    public function isVisible(ActionContext $context): bool;    // library name, ComponentReference|null, Example|null
    public function getTarget(ActionContext $context): ActionTarget; // url, method (GET|POST), hidden fields
}
```

### Cache

Outside debug the registry (components, examples) is built once and kept in a filesystem pool of the bundle under
`kernel.cache_dir`: `cache:clear` empties it, so a deploy shows new examples. In debug it is rebuilt on every request.

### Seeing it in a browser

`tests/Fixtures/public/index.php` serves the test library and the `props` one (a component of each kind of prop to try) at `/_showcase/` with debug on:
`php -S 127.0.0.1:8000 tests/Fixtures/public/index.php`, or an `index.php` of your own at the root of the clone that
requires it.

## Trying other values

The component page lets a visitor try other prop values: the form is described in "The Try form" below, the server side is a signed preview.

| Name | Method and path | Role |
|---|---|---|
| `ux_showcase_preview_link` | GET `/{library}/{component}/{slug}/preview-link` | JSON `{url, token, expires}`: a fresh signed address and the CSRF token. A page left open more than an hour asks here for new ones. |
| `ux_showcase_preview_try` | POST `/{library}/{component}/{slug}/preview?locale=&expires=&signature=` | the example drawn again, in the bare page, with the posted values |

The POST has the path of the bare page (GET) of the example; the query is what `PreviewUrlGeneratorInterface::generate($library, $example, $locale)` gives. The default implementation signs library, component, slug, locale and expiry (HMAC-SHA256, key derived from `kernel.secret` for this use only, one hour, `hash_equals`). A host replaces the address by aliasing `OpenStudio\UxShowcaseBundle\Preview\PreviewUrlGeneratorInterface` to its own service, which then owns the route.

Order of the checks: access service (404 outside it, as everywhere), signature (404 when wrong, altered or expired), origin then CSRF token (403), values (422).

- Origin: `Sec-Fetch-Site: same-origin` when the browser sends it, else the host of `Origin` must be the host of the request. Neither: 403.
- CSRF: `symfony/security-csrf`, token id `ux_showcase_preview`, in the `X-CSRF-Token` header or the `_token` field (form only). It needs `framework.csrf_protection`; without it every POST is a 403.
- Body: `application/json` (an object, prop name to value) or a form (`application/x-www-form-urlencoded`, `multipart/form-data`). Anything else: 415.

```http
POST /_showcase/props/Props:Listing/default/preview?locale=en&expires=1790000000&signature=... HTTP/1.1
Content-Type: application/json
X-CSRF-Token: ...

{"title": "Other", "vehicle": {"name": "Beetle", "price": 9.5, "tags": ["a"]}, "layout": "grid", "since": "2026-05-06T07:08:00+02:00"}
```

The example is replayed with its own props, and only the posted keys are replaced (its content and blocks are kept, and cannot be posted). Only props of the `ComponentDoc` table are accepted: any other key is a 422 that names it (a key would otherwise become an HTML attribute through `{{ attributes }}`). A value is never rendered as Twig, and no template is built from it.

Each value is converted to the declared type of its `PropDoc` (`TypedValueConverter`): scalars (text from a form is converted, `"12"` to 12), backed enums by value and unit enums by case name, dates from ISO 8601 text, objects with constructor-promoted or settable properties (Symfony Serializer, property-info, phpdoc), lists and maps item by item, unions by the member the value matches (an error when several different ones do), nullable. A prop with `allowedValues` is checked against them. An empty form field is null where the type allows it and is not text. A prop typed `mixed`, `object`, `callable`, an unknown class, or an interface or abstract class without `#[DiscriminatorMap]` is not editable: posting it is a 422.

A refusal is a 422 and never a 500: the bare page of the library lists each error as a prop path and a message (`vehicle.price`, `vehicles[1].name`), or `{"errors": [{"path", "message"}]}` when the request accepts `application/json`. A component that throws with values of the right types is a 422 too, with an empty path. Every answer (200, 422, 403, 404) carries `X-Robots-Tag: noindex, nofollow`, `Cache-Control: no-store` and `Referrer-Policy: no-referrer`.

```json
{"errors": [{"path": "vehicle.price", "message": "Expected float, got string."}]}
```

### Fields of a host

`PostedValuesNormalizerInterface` (autoconfigured, tag `ux_showcase.posted_values_normalizer`, optional `priority`, highest first) runs on the posted values before they are checked, so a host can map its own form fields to props (a picked image id to an object) and normalise types:

```php
interface PostedValuesNormalizerInterface
{
    /** @param array<string, mixed> $values  @return array<string, mixed> */
    public function normalize(array $values, PostedValuesContext $context): array;
}
```

`PostedValuesContext` gives the library, the `Example`, the `ComponentDoc`, the `Request` and `fromForm`. A step takes its own fields out of the array; what is left must be props. An object it puts in is kept as it is when it satisfies the declared type, including an interface; values from the request are never turned into an interface, `mixed` or `object`.

### The Try form

Each example of a component (not a free one) has a form beside its frame, built from the props table of the component and
prefilled from `PreviewPrefill` (the value of the example, else the default of the prop). One field per prop, by its declared type:

| Type | Field (`data-field-type`) |
|---|---|
| `string`, `html`, dates (ISO 8601 text), `int\|string` | `text` (a number typed in an `int\|string` field is sent as a number) |
| `int`, `float` | `number` |
| `bool` | `checkbox` (a hidden `0` before it: an unticked box posts false) |
| backed or unit enum, union of literals (`'sm'\|'md'`), `allowedValues` | `select` (an empty choice when the prop takes null) |
| object, list, map, union of objects | `json`: a textarea, pretty-printed |
| `mixed`, `object`, unknown class, interface without discriminator | no field: listed read-only with the reason |

With JavaScript the values are sent as JSON 400 ms after the last change, to the signed address (`X-CSRF-Token`), and the HTML that
comes back is put in the frame (`srcdoc`: the height reporting works as for the first load). Only the props the example sets, and
those the visitor changed, are sent. A JSON field that is not JSON is refused in the page, nothing is sent. A 422 puts each error
next to the field of the first segment of its path (`vehicle.price` goes to `vehicle`, with the full path in the message; the
fields have `aria-invalid` and `aria-describedby`), an error with no field goes in an alert, and the frame keeps the last good
preview. A 404 or a 403 asks `preview-link` for a new address and token and sends again once; the address is also renewed
when it is less than two minutes from its expiry. "Reset" puts the values of the example back and reloads the frame; the form is
not stored (`autocomplete="off"`), a reload shows the example's values.

Without JavaScript the same form posts, form-urlencoded with `_token`, to the signed address, in the frame (`target`), and the visible
"Show" button sends it (the script hides it). A JSON field is posted as its text and decoded with the types of JSON. Differences from the
script: every field is posted, and an empty field of a nullable text prop is an empty string, not null.

#### Field types of a host

`Screen\TryFieldTypeInterface` (autoconfigured, tag `ux_showcase.try_field_type`, optional `priority`: highest first; the built-in
ones are at -100) says how a prop is drawn. For a prop, the first type that supports it wins; none: read-only.

```php
interface TryFieldTypeInterface
{
    public function getName(): string;                 // unique, stable: data-field-type="..." (text, number, checkbox, select, json are taken)
    public function supports(PropDoc $prop): bool;
    public function getTemplate(): string;             // Twig template of the control only
    public function getModuleUrl(): ?string;           // ES module, loaded only when a field of this type is on the page
}
```

The template gets `field` (`TryField`: `prop`, `name`, `id`, `describedBy`, `value`, `hasValue`, `json`, `kind`, `nullable`, `choices`) and `try`.
Its control has `id="{{ field.id }}"`, `name="{{ field.name }}"`, `aria-describedby="{{ field.describedBy }}"` and `data-try-control`; the label,
the hint and the error are drawn around it. The field posts under the name of the prop: a host maps the value in a
`PostedValuesNormalizerInterface` (a picked image id to the object the prop needs), with and without JavaScript.

The module is an ES module, imported once, whose default export (or `init`) is called with the field of each use:

```js
export default function (field) {
    field.element;      // the wrapper of the field
    field.name;         // the prop
    field.initial;      // the value of the example (JSON-safe), undefined when there is none
    field.handle({ read: () => value, write: (v) => { /* put v in the control */ } }); // how the form reads and sets the value
    field.notify();     // the value changed: the form sends it after the 400 ms
    field.getValue();   // read()
    field.setValue(v);  // write(v), without sending
    field.setError(text); // text at the field, null to clear (aria-invalid)
}
```

Until a module has called `handle()` its field is not sent. A module that fails to load shows an error at its field and the form works without it.

### Prefill

`PreviewPrefill::forExample($example)` gives the props of an example as JSON-safe values (objects to arrays, enums to their value, dates to ISO 8601), for the props that can be posted back; posting them renders the example as it was. `isEditable($propDoc)` says whether a prop can be tried.

## Props

`ComponentDocumentation::get('App:Button')` returns the `ComponentDoc` of a component: its description, its props
(`PropDoc`: name, type as a string, default, description, required, allowed values, where it was read) and its blocks
(`BlockDoc`). It is the merge of a chain of readers (`PropsReaderInterface`, autoconfigured with the tag
`ux_showcase.props_reader`, highest priority first); an application adds its own. The two built-in readers are the
template (priority 20) and the class (priority 10). `ComponentDoc`, `PropDoc`, `BlockDoc`, `PropsReaderInterface` and
`ComponentDocumentation` are `@api`.

### In a template

```twig
{# @description A button. #}
{% props
    ## string Text of the button
    label,
    ## ?string Smaller text under the label
    hint = null,
    ## list<string> Tags shown as badges,
    ## one per line
    tags = ['new', 'sale'],
    variant = 'primary'
%}
{# @block content Content after the label #}
<button class="btn btn-{{ variant }}">{{ label }}{% block content %}{% endblock %}</button>
```

- `{% props %}`: the names and the defaults. A literal default (string, number, `true`, `false`, `null`, list, map) gives
  its value; any other expression (`'now'|date('Y')`) gives "has a default" and a null value.
- `## <type> <description>` above a prop: the type is the first word, or a whole generic (`array<string, int>`,
  `list<array{name: string}>`), a union without spaces (`string|null`) or with them; the rest, over several lines when
  needed, is the description. It is read by the bundle from the tokens of Twig (`Token::getDocumentation()`, Twig 3.29
  or later, an experimental API), the same way on every version of TwigComponent. An older Twig gives no description and
  no error.
- Older comments, still read: `{# @prop name type description #}`, `{# @prop list.field type description #}` (a field of
  the rows of a list, in `PropDoc::$fields`), `{# @description ... #}`, `{# @block name description #}`.
- Blocks: every `{% block name %}` of the template.
- A prop with no default and no type that accepts null is required. A prop whose type is not written has the type `mixed`.

Do not write a `<twig:` tag in a `##` line while TwigComponent 2.x is supported: it breaks the rendering of the
template there (fixed in TwigComponent 3.6).

### In a class

For a class component (`#[AsTwigComponent]`, `#[AsLiveComponent]`) the class reader adds, from reflection only, so a
component with no documentation still gets a table:

- the public properties, with their type (native, or `@var` with generics: `list<VehicleDto>`, unions, enums), their
  default and nullability, through `symfony/property-info` and its PHPStan extractor; the first paragraph of their docblock is
  the description;
- the parameters of `mount()`, with the type of their `@param` when there is one;
- the allowed values of an enum type (the values of a backed enum, the names otherwise), and the ones a `#[PreMount]`
  method gives with `$resolver->setAllowedValues('name', [...])`. The method is read, never run, so only a literal list
  is found; a computed list (`range()`, a constant, `Enum::cases()`) is not.

What is not a prop: a public property that `mount()` assigns and that is not one of its parameters (it is computed from
the arguments, found by reading the source of `mount()`), a property or a method marked `#[ExposeInTemplate]` that is
not public (a variable for the template), and the properties of the `symfony/ux-*` traits (`isValidated`,
`validatedFields`, `formValues`...). An anonymous component has no class.

### Merge rule

When two readers know the same prop, in priority order: the description and the allowed values come from the first
reader that has some; the type and the default come from the last reader that knows them (so the type of the class wins
over the one of a comment, and `mixed` never erases a known type); the sources add up; `required` is worked out again
from the merged default and type. Props and blocks keep the order of the first reader that knows them.

## Checking every example (CI)

```bash
bin/console ux-showcase:check [--library=<name> ...] [--strict] [--with-data] [--format=junit --output=<file>]
```

It renders every example of every library (`--library`, repeatable, to pick some) through an HttpKernel sub-request to
its bare page: the bare template of the host, its asset helpers and its Twig globals are exercised, with a simulated
session (`csrf_token()` works) and the access check lifted for those sub-requests only. The lifting is a request
attribute set by the command and honoured on a sub-request only: a request from the network is always a main request,
so no query, header or cookie can set it.

An example fails on an exception or a status other than 200. With `--strict`, Twig strict variables are on for the run
(an application in dev usually has them off, and an undefined variable then renders as nothing) and a PHP notice,
warning or deprecation raised during a render fails it. Every broken example is listed, not the first:

```
 FAIL  flexy / Flexy:Form:Input / error: Twig\Error\RuntimeError: Variable "label" does not exist in "..." at line 3.
 WARN  flexy / Flexy:Cart / summary skipped, needs data: use --with-data to render it
Checked 41, passed 40, failed 1, skipped 1.
```

The exit code is 1 when an example failed, 0 otherwise. "Checked" counts the examples rendered; `-v` also lists the ones
that passed. `--format=junit --output=reports/showcase.xml` writes a JUnit report (a testsuite per library, a testcase
per example, `<failure message>` on a failed one, `<skipped>` on a skipped one) and keeps the human output on the
console; without `--output` the XML goes to the standard output.

GitLab CI, with the report in the test tab of the merge request:

```yaml
showcase:
  stage: test
  script:
    - bin/console ux-showcase:check --strict --format=junit --output=reports/showcase.xml
  artifacts:
    when: always
    reports:
      junit: reports/showcase.xml
```

Run it in the environment of the application (its `APP_ENV`, its database when `--with-data` is used): the command
renders with the real container.

### Examples that need data

An example that reads the database by a fixed id, the session at mount, or calls an API when a prop is missing cannot
render in a CI without those: mark it `needs_data` and the command skips it with a warning, unless `--with-data`.

- in an example file: `{% showcase_example 'cart' options { needs_data: true } %}`;
- for every example of a library: `ux_showcase.libraries.<name>.preview.needs_data: true`, or `ux_showcase.preview.needs_data`
  for all; the first level that sets it wins, like the other preview options (an example can set `false`);
- for some components of a library whose example files you do not own (a vendor kit): list them in the library,
  `ux_showcase.libraries.<name>.needs_data: ['Flexy:Cart', 'Flexy:Form:Address']`;
- on a PHP example: `#[AsShowcaseExample(component: '…', slug: '…', needsData: true)]`;
- in PHP: `new PreviewOptions(needsData: true)` on an `Example`, or `ComponentReference::$preview` for a component of
  a library that implements `ComponentLibraryInterface`; an adapter that feeds examples through an
  `ExampleProviderInterface` sets it on the examples it yields.

### What it does not see

It checks the server side only: a JavaScript error in the browser (a Stimulus controller that throws, a failing
`fetch`), a layout that is wrong, a style that is missing. Pair it with a browser test for those.

## Quality

```bash
composer check   # php-cs-fixer, PHPStan level 10, PHPUnit
```

## License

LGPL-3.0-or-later. See `LICENSE`, which completes the GNU GPL v3 in `LICENSE.GPL`.

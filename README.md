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

## License

LGPL-3.0-or-later. See `LICENSE`, which completes the GNU GPL v3 in `LICENSE.GPL`.

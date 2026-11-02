# SightPress core

`sightpress/core`: the package that holds every core feature of SightPress, a
powerful multi-site, multi-language CMS for everyone, designed with blind and
low-vision content creators and audiences who use assistive technology in
mind. Written in modern, object-oriented PHP. Licensed GPL-2.0-or-later.

> **Status:** pre-M0.

The core covers sites, hierarchical pages, authors, the media library (local,
cloud storage or CDN), the widgets library, navigation menus, internal links
that never break, the translation workflow, site profiles, logos and favicons,
the five generated legal pages, SEO and AIO, service email (invitations,
password resets, security and system alerts), themes and the theme generator,
the accessibility linter and publish gate, the accessibility dashboard and
feedback form, the admin, the API, the installer and the plugin engine.
Everything else ships as plugins.

## Using it

You don't install this package on its own. Create a project from the skeleton,
which requires it together with the official plugins:

```bash
composer create-project sightpress/skeleton my-site
```

Upgrade it with `composer update "sightpress/*"` followed by
`php bin/console doctrine:migrations:migrate`. The bundle brings its own
configuration, templates, translations and migrations, so an upgrade needs no
changes in your project. It follows SemVer; breaking changes only come in a
major version, with an upgrade note.

## Related repositories

| Repository | Package | Purpose |
|---|---|---|
| `skeleton` | `sightpress/skeleton` | The deployable project, with Docker support |
| `core` (this one) | `sightpress/core` | Core features, plugin engine, installer |
| `plugin-core` | `sightpress/plugin-core` | Plugin API and code shared by every plugin |
| `plugin-template` | `sightpress/plugin-template` | Starting point for a new plugin |
| `plugin-<id>` | `sightpress/plugin-<id>` | One official plugin each |

The core depends on `sightpress/plugin-core` and never on a plugin. It works
with every plugin removed.

## Development

```bash
composer install
vendor/bin/phpunit          # on a test kernel, with no plugins
vendor/bin/phpstan analyse
vendor/bin/deptrac analyse
```

To run it inside a real app, check out `skeleton` next to this repository and
run `COMPOSER=composer.dev.json composer install` there.

## Security

To report a vulnerability, follow `SECURITY.md` (added in M0); please don't
open a public issue.

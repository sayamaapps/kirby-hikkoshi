# Contributing

Bug reports, feature requests and pull requests are welcome. Support is provided on a best-effort basis.

## Before you start

Open an issue first for anything beyond a small fix, so the change can be discussed before work starts.

## Setup

```sh
git clone https://github.com/sayamaapps/kirby-hikkoshi.git
cd kirby-hikkoshi
composer install
```

To try the plugin on a site, link or copy the folder to `site/plugins/hikkoshi/`.

## Checks

Run this before you open a pull request. It runs every check that CI runs (`composer ci`), on the PHP version installed locally. CI runs the tests on PHP 8.2 to 8.5.

```sh
composer ci
```

The script runs: `validate`, `normalize:check` (composer.json layout), `analyze:require-checker` (undeclared dependencies), `analyze:unused` (unused packages), `check-platform-reqs`, `analyze:psalm`, `analyze:phpmd` and `test` (PHPUnit).

Code style is checked with PHP-CS-Fixer and the Kirby rule set (`getkirby/php-cs`). The package is installed globally, because it needs PHP 8.3 or newer and this package supports PHP 8.2:

```sh
composer global require getkirby/php-cs:0.2.1
kirby-cs fix --dry-run --diff     # the global bin directory must be in PATH
```

## Conventions

- Tabs for indentation, LF line endings. `.editorconfig` lists the rest.
- Classes live in `src/`, one class per file. The class map in `autoload.php` lists them, and `tests/AutoloadTest.php` checks it.
- Keep public names stable. A rename gets a deprecation wrapper first, see the README section Deprecations.
- Commit messages follow Conventional Commits (`feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `style:`, `chore:`).

## Pull requests

Describe what changes and why. Add or update tests for a bug fix or a feature. Use the pull request template.

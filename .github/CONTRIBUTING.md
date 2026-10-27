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

The script runs: `validate`, `normalize:check` (composer.json layout), `analyze:require-checker` (undeclared dependencies), `analyze:unused` (unused packages), `analyze:phpcpd` (duplicate code), `check-platform-reqs`, `analyze:psalm`, `analyze:phpmd` and `test` (PHPUnit). `analyze:phpcpd` needs phpcpd on the PATH, see below.

Code style is checked with PHP-CS-Fixer and the Kirby rule set (`getkirby/php-cs`). The package is installed globally, because it needs PHP 8.3 or newer and this package supports PHP 8.2:

```sh
composer global require getkirby/php-cs:0.2.1
kirby-cs fix --dry-run --diff     # the global bin directory must be in PATH
```

phpcpd is installed globally too. Its 6.0.3 release needs `sebastian/cli-parser` 1.x, which PHPUnit 11 does not allow as a dependency:

```sh
composer global require sebastian/phpcpd:6.0.3
```

## Conventions

- Tabs for indentation, LF line endings. `.editorconfig` lists the rest.
- Classes live in `src/`, one class per file. The class map in `autoload.php` lists them, and `tests/AutoloadTest.php` checks it.
- Keep public names stable. A rename keeps the old name as an alias or wrapper, marked `@deprecated` in the code and listed in the README under Deprecated. Nothing is raised at runtime.
- Commit messages follow Conventional Commits (`feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `style:`, `chore:`).
- To skip the style commit in `git blame`, run `git config blame.ignoreRevsFile .github/git-blame-ignore-revs`.

## Pull requests

Describe what changes and why. Add or update tests for a bug fix or a feature. Use the pull request template.

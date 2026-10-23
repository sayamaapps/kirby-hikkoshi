# Hikkoshi

Checked redirects for [Kirby CMS](https://getkirby.com) after a site has moved.

*Hikkoshi* (引っ越し) means moving house. It maps a site's old addresses to their new pages and checks that map before the site goes live.

## Features

- A checked list: loops, duplicate old paths and foreign targets are errors; chains are warnings.
- Tolerant matching: case, trailing slash, percent-encoding, umlauts, `.html` and `index.php` endings.
- Query-string addresses such as `/?page_id=82`, per-entry status and query handling, and 410 for removed pages.
- Command-line check, an `.htaccess` converter and a test helper for sites.
- Server-side only: no cookies, no browser storage, no outbound requests. English and German messages.

## Requirements

- PHP 8.2–8.4 with the `mbstring` extension, and Kirby 5

## Installation

```sh
composer require sayamaapps/kirby-hikkoshi
```

Until the package is on Packagist, add the repository to `composer.json` first:

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/sayamaapps/kirby-hikkoshi" }]
```

Composer installs to `site/plugins/hikkoshi/`. Add that path to `.gitignore` and deploy it like `vendor/`.

## Usage

Hikkoshi is off until you set `'hikkoshi.active' => true` in `site/config/config.php`. Then create `site/config/hikkoshi.php`, which maps old address to new address (the package ships a template as `config/hikkoshi.example.php`):

```php
<?php

return [
    'ueber-uns'        => 'team',
    'impressum.html'   => 'impressum',
    '?page_id=82'      => 'impressum',
    'aktion'           => ['to' => 'produkte', 'status' => 302, 'query' => 'drop'],
    'gewinnspiel-2019' => 410,
    'partner'          => 'https://partner.example/start', // needs hikkoshi.allowedHosts
];
```

### Keys and values

- **Keys** are old paths such as `'ueber-uns'` or `'downloads/karte.pdf'`. **Query conditions** follow the `?`: `'?page_id=82'`, or `'?feed'` (parameter present, any value). The home page needs a query condition.
- **Values** are a page id (`'team'`, `'home'`), `410`, an array with `to`, `status` and `query`, or an external URL whose host is listed in `hikkoshi.allowedHosts`. `410` answers with the snippet `hikkoshi/gone`; to restyle it, create `site/snippets/hikkoshi/gone.php`, which receives `$title` and `$text` (escape them).
- Targets are lowercase page ids: `'/kontakt'` and `'kontakt.html'` are errors. Numeric keys such as `2024` are errors too; write `'archiv/2024'`.

### Matching

The exact pass comes first. If nothing matches, one suffix (`html`, `htm`, `php`) or index file (`index.php`, `index.html`, `index.htm`) is removed from the last segment and the lookup runs again. The first hit wins.

- Requests and keys are normalised: case, repeated slashes, percent-encoding and umlauts (`/Über-Uns/` matches `'ueber-uns'`). The second pass runs one way: `/impressum.html` finds `'impressum'`, but `'impressum.html'` does not catch `/impressum`.
- Only `GET` and `HEAD` are redirected, and only to published pages. Otherwise Kirby answers as usual.
- Not matched: host names (keep those rules in the web server), files in `public/`, other suffixes such as `.pdf`, and old media URLs. A key that is also a real page address redirects, so that page becomes unreachable there.
- Matched condition parameters are removed from the target URL. Other parameters are kept (`query` `keep`, the default) or dropped (`drop`). A 410 never carries a query.

### Site and page methods

- `$site->hikkoshiRedirects()`: the validated entries; `[]` while off.
- `$site->hikkoshiResolve($path, $query = [])`: the decision for a path and query (`status`, `location`, `page`), or `null`.
- `$site->hikkoshiCheck($render = true)`: findings for the site test; `[]` when all is well.
- `$page->hikkoshiSources()`: the old paths that redirect to this page.
- `hikkoshi_option('language')` reads any `hikkoshi.*` option in your templates.

## Options

Each option is read as `hikkoshi.<name>`, flat or nested. `null` means "not set". Booleans take a real `true` or `false`. A broken value is logged once and the default is used; with Kirby's `debug` option on, it throws.

| Option | Default | Description |
|---|---|---|
| `active` | `false` | Master switch. Only `true` enables Hikkoshi. |
| `redirects` | `null` | The list as an array, or a Closure returning one. Takes precedence over `file`. |
| `file` | `null` | List file, absolute or relative to `site/config/`. Unset: `site/config/hikkoshi.php`, then `site/config/weiterleitungen.php`. |
| `status` | `301` | Status of entries without their own: `301`, `302`, `307` or `308`. |
| `query` | `'keep'` | `keep` passes the remaining query parameters on; `drop` sends none. |
| `allowedHosts` | `[]` | Hosts (string or list) that may be external redirect targets. |
| `resolveChains` | `false` | `true` replaces a chain's target with the final one, reported as info. |
| `lowercase` | `true` | Lowercase both sides before matching. `false` matches exact case. |
| `transliterate` | `true` | `ä`→`ae`, `ö`→`oe`, `ü`→`ue`, `ß`→`ss` on both sides. |
| `suffixes` | `['html', 'htm', 'php']` | Suffixes removed in the second pass. `[]` turns it off. |
| `indexFiles` | `['index.php', 'index.html', 'index.htm']` | Last segments removed in the second pass (`blog/index.php` becomes `blog`). `[]` turns it off. |
| `language` | site language | `en` or `de`, for messages and the 410 page. Default: site language, then `locale`, then English. |

## Validation

- **Errors** (loops, duplicate old paths, invalid keys or targets, foreign hosts, self-redirects, invalid status or query mode) stop the site: every request fails with HTTP 500. The first test shows them.
- **Warnings** (chains such as `a → b → c`) are logged and the site keeps working. Enter the final target directly, or set `resolveChains`. The chain check ignores entries with a query condition.

## CLI

```sh
vendor/bin/hikkoshi check [FILE] [--lang=en|de] [--resolve-chains] [--allow-host=HOST] [--strict]
vendor/bin/hikkoshi convert FILE [--lang=en|de]
```

- `check` validates the list without booting Kirby. `FILE` defaults to the list file. `--allow-host` accepts an external host and can be repeated; `--strict` also fails on warnings.
- `convert` turns Apache `Redirect`, `RedirectMatch` and `RewriteRule` lines into entries, prints PHP to standard output and writes no file. Regular expressions are skipped, and prefix rules convert for the exact path only. Review the result, then run `check`:

```sh
vendor/bin/hikkoshi convert public/.htaccess > site/config/hikkoshi.php
```

- Exit codes: `0` no errors (for `convert`: all lines converted), `1` errors (with `--strict` also warnings; for `convert`: lines skipped or partly converted), `2` usage error or file not found.
- Once the list passes `check` and the site test, delete the converted rules from `.htaccess` in the same deploy. Apache answers before PHP does, so a leftover rule wins over the list. Keep what the list cannot express: the host-name rule (`www` to the bare domain), `DirectoryIndex`, Kirby's rules that pass requests to `index.php`, and header settings.

## Test helper for sites

A test in `tests/HikkoshiTest.php` boots the site and fails on any finding. `hikkoshiCheck()` adds what only the running site shows: missing or draft targets, wrong answers and targets that redirect again.

```php
<?php

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';

try {
    $kirby = new Kirby\Cms\App(['roots' => [
        'index' => $base . '/public', 'base' => $base,
        'content' => $base . '/content', 'site' => $base . '/site',
    ]]);
    $findings = $kirby->site()->hikkoshiCheck();   // hikkoshiCheck(false) skips rendering
} catch (Hikkoshi\ConfigException $e) {          // a broken list stops Kirby while it starts
    $findings = $e->findings();
}

foreach ($findings as $f) {
    fwrite(STDERR, $f['level'] . ': ' . $f['message'] . PHP_EOL);
}
exit($findings === [] ? 0 : 1);
```

Without Kirby, load `I18n`, `Options`, `Path`, `ConfigException` and `Validator` from `site/plugins/hikkoshi/src/`, then call `Hikkoshi\Validator::validate()` with the list and the same `allowedHosts`.

## Privacy

Hikkoshi stores nothing and processes no visitor data: no cookies, no browser storage, no requests to third parties. It therefore has no place in a site's privacy policy.

Tests: `composer test`. Issues and pull requests are welcome, without a support promise. Report security problems privately through GitHub's vulnerability reporting. Changes: [CHANGELOG.md](CHANGELOG.md).

## License

MIT, see [LICENSE](LICENSE).

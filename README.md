# Hikkoshi

Redirects for [Kirby CMS](https://getkirby.com) 5 after a site has moved from Jimdo, Wix or WordPress: a checked list of old addresses, tolerant matching, a converter for Apache `.htaccess` rules and a test helper for the site. No styling, no outbound requests.

*Hikkoshi* (引っ越し) means moving house. The old addresses of a site are its forwarding address: anyone who arrives at the old door is sent on to the new one, with the same parcel (the query string) if it matters, and a clear "gone" for the things that were thrown away.

- **A checked list.** Old paths and their targets live in one PHP array. Loops, duplicate old paths and foreign targets are errors; chains are warnings, and can be resolved automatically.
- **Tolerant matching.** Case, trailing slash, percent-encoding, umlauts (`ü` = `ue`), `.html`, `.htm` and `.php` endings and `index.php` all lead to the same entry. Entries can be pasted from a browser.
- **Query-string addresses.** WordPress with plain permalinks (`/?page_id=82`) and QR codes with `?utm_source=…` work. Matched condition parameters are removed; the rest is kept or dropped as you choose.
- **Per-entry status and query.** 301 by default; 302, 307 and 308 per entry; 410 for pages that are gone for good, with a page you can restyle.
- **External targets only when allowed.** A foreign URL is a target only when its host is listed in `hikkoshi.allowedHosts`.
- **Command-line check and converter.** `vendor/bin/hikkoshi check` validates the list without Kirby; `vendor/bin/hikkoshi convert` turns Apache `Redirect`, `RedirectMatch` and `RewriteRule` lines into list entries.
- **A test helper for sites.** `$site->hikkoshiCheck()` verifies, against the real router, that every entry answers as it says and that every target is a published page.
- **English and German.** Messages and the 410 page follow `hikkoshi.language`, the site language or the locale.
- **No cookies, no browser storage, no requests to third parties.** See [Privacy](#privacy).

## Requirements

- PHP 8.2–8.4, with the `mbstring` extension
- Kirby 5

## Installation

```sh
composer require sayamaapps/kirby-hikkoshi
```

Composer installs the plugin into `site/plugins/hikkoshi/` (via `getkirby/composer-installer`, which ships with `getkirby/cms`) and links the command as `vendor/bin/hikkoshi`. Add `/site/plugins/hikkoshi/` to `.gitignore` and make sure your deploy ships it, just as it ships `kirby/` and `vendor/`.

Until the package is on Packagist, add the repository first:

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/simsimmal/kirby-hikkoshi" }]
```

Manual installation (download into `site/plugins/hikkoshi/`) also works. The command is then `php site/plugins/hikkoshi/bin/hikkoshi`.

If the site still has the pre-1.0 copy `site/plugins/weiterleitungen/`, remove it before the first request. See [Upgrading](#upgrading-from-siteispluginsweiterleitungen).

## Quick start

1. Switch it on in `site/config/config.php`:

   ```php
   <?php

   return [
       'hikkoshi.active' => true,
       // 'hikkoshi.language' => 'de',   // optional; German messages and 410 page
   ];
   ```

2. Create the list in `site/config/hikkoshi.php`. The package ships a template:

   ```sh
   cp site/plugins/hikkoshi/config/hikkoshi.example.php site/config/hikkoshi.php
   ```

   ```php
   <?php

   return [
       'ueber-uns'      => 'familie-und-hof',
       'impressum.html' => 'impressum',
       '?page_id=82'    => 'impressum',
       'gewinnspiel-2019' => 410,
   ];
   ```

   Page ids are the Kirby page ids without a domain, `home` is the home page.

3. Check the list:

   ```sh
   vendor/bin/hikkoshi check
   ```

   It reads `site/config/hikkoshi.php` (or the older `site/config/weiterleitungen.php`) and reports every finding. Exit code 0 means no errors.

4. Add the site test described in [Test helper for sites](#test-helper-for-sites), so that a broken target is found before the site goes live.

## The list

The list is one PHP array, old address => new address:

```php
return [
    'old-path' => 'new-page-id',
];
```

It lives in `site/config/hikkoshi.php`. A file named `site/config/weiterleitungen.php` is read unchanged when the new file does not exist. The option `hikkoshi.redirects` takes the array (or a Closure that returns it) directly, and `hikkoshi.file` names another file (see [Options](#options)).

### Key forms

| Key | Meaning | Example request |
|---|---|---|
| `'ueber-uns'` | an old path | `/ueber-uns`, `/Über-Uns/`, `/%c3%bcber-uns` |
| `'angebot/kiste'` | an old path with segments | `/angebot/kiste/` |
| `'impressum.html'` | an old path with a suffix | `/impressum.html` |
| `'downloads/karte.pdf'` | an old file, now a page | `/downloads/karte.pdf` |
| `'?page_id=82'` | the home page with a query parameter (WordPress, plain permalinks) | `/?page_id=82` |
| `'index.php?p=14'` | a path with a query condition | `/index.php?p=14` |
| `'?feed'` | a query parameter that must be present, any value | `/?feed`, `/?feed=rss2` |
| `'a?k=1&l=2'` | two conditions, both must hold | `/a?k=1&l=2` |

Key normalisation is the same as for request paths (see [Matching](#matching)): the keys `'Menue'`, `'/menue/'`, `'über-uns'` and `'%c3%bcber-uns'` are accepted and stored as `menue` and `ueber-uns`. Keys that are not valid old paths are errors: `../secret`, `.hidden`, `a..html`, `a.b.c`, `a(*)`, `(:all)`, `https://example.org`, names with spaces, and the empty key (except the query form above).

A key with a numeric name, such as `2024 => 'archiv'`, is an error, because PHP turns `'2024'` into an integer. Write `'archiv/2024'` instead.

### Value forms

| Value | Meaning |
|---|---|
| `'hofladen'` | the page with that id; status and query come from the options |
| `'leistungen/beratung'` | a page id with slashes inside, no slash at the ends |
| `'home'` | the home page |
| `410` | gone: answered with status 410 and the `hikkoshi/gone` snippet |
| `['to' => 'hofladen', 'status' => 302, 'query' => 'drop']` | a per-entry status (301, 302, 307 or 308) and query mode (`keep` or `drop`) |
| `['status' => 410]` | gone, the same as the value `410` |
| `'https://other.example/x'` or `['to' => 'https://other.example/x']` | an external target; only when the host is in `hikkoshi.allowedHosts` |

Targets are strict: lowercase page ids only, no leading slash (`'/kontakt'` is an error), no `.html` (`'kontakt.html'` is an error), no uppercase. The converter removes a leading slash from Apache targets, so pasted rules still work.

A complete example:

```php
<?php

return [
    // Jimdo: pages without suffix, subpages with a path
    'ueber-uns'            => 'familie-und-hof',
    'angebot/gemuesekiste' => 'hofladen',
    // Jimdo and WordPress exports with a suffix
    'impressum.html'       => 'impressum',
    'index.html'           => 'home',
    // Old downloads whose content is a page now
    'downloads/preisliste-2024.pdf' => 'hofladen',
    // WordPress with plain permalinks (query-string addresses)
    '?page_id=82'          => 'impressum',
    '?pagename=contact'    => 'kontakt',
    '?feed'                => 'home',
    // Removed for good: 410 instead of a redirect
    'gewinnspiel-2019'     => 410,
    // Temporary, with the query dropped
    'aktion'               => ['to' => 'hofladen', 'status' => 302, 'query' => 'drop'],
    // A foreign target, needs hikkoshi.allowedHosts
    'partner'              => 'https://partner.example/start',
];
```

The shipped template is `config/hikkoshi.example.php` in the package.

### `home`

The home page is the page id `home`. A request for `/` never matches an entry without a condition, because the home page cannot be an old path by itself. An old home page is therefore written with a query condition (`'?page_id=82'`), and an old file name for it is an ordinary path (`'index.html' => 'home'` answers `/index.html`).

## Matching

A request is matched in two passes. The first hit wins.

1. **Exact pass.** The request path is normalised and looked up. Among the entries with that path whose query conditions all hold, the one with more conditions wins; equal entries keep the order of the list.
2. **Suffix pass.** If nothing matched, one suffix (`html`, `htm`, `php`) or one index file (`index.php`, `index.html`, `index.htm`) is removed from the last segment and the lookup runs again.

Normalisation of request paths and of keys:

- surrounding whitespace is removed, percent-encoding is decoded (at most twice), the text is Unicode-normalised (NFC);
- repeated slashes become one, slashes at the start and end are removed;
- with `lowercase` (default on) the text is lowercased;
- with `transliterate` (default on) `ä` becomes `ae`, `ö` becomes `oe`, `ü` becomes `ue` and `ß` becomes `ss`.

So `/Über-Uns/` and `/%C3%BCber-uns` both match `'ueber-uns'`. With only the key `'impressum'` (and no `'impressum.html'`), `/Impressum.html` matches in the suffix pass.

Examples with `site/config/hikkoshi.php` as above:

| Request | Matches | Pass |
|---|---|---|
| `/ueber-uns` | `'ueber-uns'` | exact |
| `/Über-Uns/` | `'ueber-uns'` | exact (normalised) |
| `/impressum.html` | `'impressum.html'` | exact |
| `/Impressum.html` | `'impressum.html'` | exact (normalised) |
| `/impressum.php` with only the key `'impressum'` | `'impressum'` | suffix |
| `/blog/index.php` with the key `'blog'` | `'blog'` | suffix |
| `/?page_id=82&utm_source=qr` | `'?page_id=82'` | exact |
| `/index.php?p=14` with the key `'?p=14'` | `'?p=14'` | suffix (`index.php` removed) |
| `/index.php?p=14` with the key `'index.php?p=14'` | `'index.php?p=14'` | exact |
| `/?p=14` with the key `'index.php?p=14'` | nothing | |

Conditions are compared as text. `'?page_id=82'` matches `?page_id=82`, and `?page_id=082` does not match. A condition without a value (`'?feed'`) matches any value, including an empty one (`?feed=`). An array value (`?probe[]=1`) never equals a text condition.

### What is not matched

- **The suffix pass goes one way only.** The request may carry a suffix that the key does not have (`/impressum.html` hits the key `'impressum'`), but not the other way round: `'impressum.html' => 'impressum'` does not catch `/impressum`. The address `/impressum` is the new page, and a redirect there would hide it.
- **Other suffixes.** `/karte.pdf` does not match `'karte'`. Only the suffixes in `hikkoshi.suffixes` are removed.
- **The host name.** Nothing is matched by host. A rule such as "www to the bare domain" stays in the web server (see [Taking over `.htaccess` rules](#taking-over-htaccess-rules)).
- **Files that exist.** A file in `public/` is served by the web server and never reaches Kirby, so it cannot be redirected.
- **Other methods.** Only `GET` and `HEAD` are redirected. A `POST` to an old address is not changed, so a form never turns into a `GET`.
- **Language prefixes.** `/de/menue` is a path with the prefix `de`. A key for it would have to contain the prefix. This is not tested.
- **Real pages.** The plugin route runs before Kirby looks up pages. An old path under which a real page still exists redirects, and the page is then unreachable.

### Redirects only to published pages

A redirect is sent only while its target page exists and is published. A draft or a missing page as target gives the normal treatment of the old path, usually a 404. This is deliberate: during a staged deploy, an old address then shows the ordinary "not found" page, not a broken redirect.

### Query strings

- The condition parameters that matched are always removed from the redirect: `/?page_id=82&utm_source=qr` goes to `/impressum?utm_source=qr`, not to `/impressum?page_id=82`.
- The other parameters are kept (`query => 'keep'`, the default) or dropped (`'drop'`). The option `hikkoshi.query` sets the default; an entry can set its own.
- A 410 never carries a query.

## Options

Every option is read as `hikkoshi.<name>`, either flat (`'hikkoshi.active' => true`) or nested (`'hikkoshi' => ['active' => true]`). `null` counts as "not set" and gives the default.

| Option | Default | Meaning |
|---|---|---|
| `active` | `false` | Master switch. Only `true` switches Hikkoshi on. Off: no route is registered, the site and page methods return `[]` or `null`, and `hikkoshiCheck()` reports `inactive`. |
| `redirects` | `null` | The list as an array or a Closure returning one. Overrides the file. |
| `file` | `null` | Path of the list file (absolute, or relative to `site/config/`). Default: `site/config/hikkoshi.php`, then `site/config/weiterleitungen.php`. |
| `status` | `301` | Status of entries without their own: 301, 302, 307, 308. Invalid: logged, 301 is used. |
| `query` | `'keep'` | `keep` passes the remaining query parameters on (matched condition keys are always removed); `drop` sends none. |
| `allowedHosts` | `[]` | Hosts (lowercase, string or list) that may be redirect targets. Nothing else is ever an external target (open-redirect safety). |
| `resolveChains` | `false` | `true` replaces a chain's target by the final one (info instead of warning). |
| `lowercase` | `true` | Lowercase both sides before matching. `false` = exact case (the pre-1.0 behaviour). |
| `transliterate` | `true` | `ä→ae ö→oe ü→ue ß→ss` on both sides. |
| `suffixes` | `['html', 'htm', 'php']` | Suffixes removed from the last segment for the second lookup pass. `[]` = off. |
| `indexFiles` | `['index.php', 'index.html', 'index.htm']` | Last segments removed for the second pass (`blog/index.php` → `blog`, `index.php` → home). `[]` = off. |
| `language` | site language | `en` or `de`; else current/default language, `locale`, English. Messages in the log and the 410 page. |

Boolean options take a real `true` or `false`. The string `'false'` (typical for values from the environment) is logged and the default is used. A list option given as a single string is accepted as a one-element list.

A broken value is logged once per process and the default is used. With Kirby's `debug` option set to `true`, it throws instead, so the error shows on the page during development.

Example with a few options:

```php
<?php

return [
    'hikkoshi.active'        => true,
    'hikkoshi.status'        => 302,              // while the new site is still settling
    'hikkoshi.query'         => 'drop',
    'hikkoshi.allowedHosts'  => ['partner.example'],
    'hikkoshi.resolveChains' => true,
];
```

## Validation

Every entry is checked when the list is read. The findings have three levels:

| Level | Meaning | At runtime | In `check` and `hikkoshiCheck()` |
|---|---|---|---|
| **error** | the entry or the list is wrong | the list is not used: **every request fails with HTTP 500** | reported; `check` exits 1 |
| **warning** | works, but should be fixed | logged once per process | reported; `check --strict` exits 1 |
| **info** | a note, such as a chain that was resolved | logged once per process | reported by `check`; not returned by `hikkoshiCheck()` |

A list with errors stops the site on purpose, as the pre-1.0 copy did. A single entry is never skipped silently: the error shows at once, in the first test, and not weeks later in the search console.

The messages are translated; the codes are the keys of the translation table (`finding.<code>`).

| Code | Level | Cause |
|---|---|---|
| `config.notArray` | error | the list is not an array |
| `source.numeric` | error | the key is missing or a plain number (`2024`) |
| `source.invalid` | error | the old path has forbidden characters or segments (`../`, `.hidden`, `a..html`, `(:all)`) |
| `source.empty` | error | the home page without a query condition |
| `source.duplicate` | error | two keys normalise to the same old path (`'Menue'` and `'menue'`) |
| `entry.invalid` | error | the value is not a page id, `410` or an array with `to`, `status` and `query` only; or `410` together with `to` |
| `target.missing` | error | an array without `to` (and without status 410) |
| `target.invalid` | error | the target is not a valid page id (`'/kontakt'`, `'Kontakt'`, `'kontakt.html'`), has the wrong type, or is a URL with a space or control character inside |
| `target.foreign` | error | an external target whose host is not in `hikkoshi.allowedHosts` |
| `target.self` | error | the entry redirects to itself |
| `status.invalid` | error | a status other than 301, 302, 307, 308, 410 |
| `query.invalid` | error | a query mode other than `keep` or `drop` |
| `loop` | error | a redirect loop, for example `x → y → x` |
| `chain` | warning | a redirect to an address that redirects again, for example `a → b → c`. Enter the final target directly. |
| `chain.resolved` | info | a chain that `hikkoshi.resolveChains` has resolved |

The test helper adds findings about the site itself (see [Test helper for sites](#test-helper-for-sites)): `inactive`, `page.missing` (the target page does not exist or is a draft), `check.noMatch` (an old path does not match its own entry), `check.status` (the old path answers with another status), `check.location` (it redirects to another address), `check.target` (the target does not answer 200) and `check.hop` (the target is itself redirected).

Two kinds of chains are not reported, because the list alone cannot show them:

- An entry with a query condition is not counted as the next hop. With `query` on `keep`, `'alt' => 'speisekarte'` forwards `?x=1`, and `'speisekarte?x=1' => 'hofladen'` then answers a second time — but only for that query. Give the first entry `'query' => 'drop'` if that matters.
- A target `home` is never captured by an entry `home`: the redirect lands on `/`, and the entry `home` answers `/home`. If the site renames its home page (Kirby option `home`), the same holds for that page's id; the list check does not know it, `hikkoshiCheck()` does.

Chains are warnings, not errors: the site keeps working with two hops, and the warning is logged. Loops and duplicates are errors. Run `vendor/bin/hikkoshi check --strict` in CI to fail on chains too, or set `hikkoshi.resolveChains` to let the site resolve them.

## CLI

The command is `vendor/bin/hikkoshi`. It does not need Kirby and never edits a file.

### `check`

```sh
vendor/bin/hikkoshi check [FILE] [--lang=en|de] [--resolve-chains] [--allow-host=HOST]... [--strict]
```

- `FILE` defaults to `site/config/hikkoshi.php`, then `site/config/weiterleitungen.php`. A relative path is taken from the current directory, so run it from the project root.
- `--resolve-chains` reports chains as resolved infos, as `hikkoshi.resolveChains` would.
- `--allow-host=HOST` accepts an external target on that host. Repeat it for more hosts.
- `--strict` makes warnings fail too.
- `--lang=en|de` sets the language of the messages.

The check does not know which pages exist or what the site's options are. Page existence and the real responses are the job of the site test.

Exit codes: `0` no errors (and with `--strict` no warnings), `1` errors (or warnings with `--strict`), `2` usage error or file not found.

```text
Checking site/config/sample.php
error: Foreign domain evil.example is not allowed as a target: 'foo' => 'https://evil.example/x'. Add it to hikkoshi.allowedHosts.
error: Old path missing or purely numeric: 2024 => 'archiv'
error: Redirect loop: x → y → x
warning: Redirect chain: a → b → c → d. Enter the final target directly.
warning: Redirect chain: b → c → d. Enter the final target directly.
6 entries, 3 errors, 2 warnings.
```

### `convert`

```sh
vendor/bin/hikkoshi convert FILE [--lang=en|de]
```

`convert` reads an `.htaccess` file and prints the list as PHP to standard output. Skipped lines go to standard error. Redirect the output to a file yourself; the command never writes one, and you should review the result before you use it:

```sh
vendor/bin/hikkoshi convert public/.htaccess > site/config/hikkoshi.php
vendor/bin/hikkoshi check
```

It reads these directives (names are case-insensitive): `Redirect`, `RedirectPermanent`, `RedirectTemp`, `RedirectMatch` (only literal patterns), and `RewriteRule` with an `R` flag (a redirect) or a `G` flag (410), together with the `RewriteCond %{QUERY_STRING}` lines before it. Other lines are ignored, not skipped: Kirby's own `RewriteRule`s that pass requests to `index.php` produce no redirect and are not part of the list. A `RewriteRule` to an absolute URL without `R` is skipped and reported, because Apache answers it with a redirect too when the host is another one. A protocol-relative target (`//host/path`) is skipped as well.

Exit codes: `0` all lines converted, `1` lines skipped (listed on standard error), `2` usage error or file not found.

#### The radladen example

The WordPress site of radladen ran with plain permalinks, so its old addresses were query strings (`/?page_id=82`). Its `public/.htaccess` maps them with one `RewriteCond` and one `RewriteRule` per target, for example:

```apache
RewriteCond %{QUERY_STRING} (^|&)page_id=82(&|$)
RewriteRule ^(index\.php)?$ /impressum? [R=301,L]

RewriteCond %{QUERY_STRING} (^|&)page_id=14(&|$) [OR]
RewriteCond %{QUERY_STRING} (^|&)p=14(&|$) [OR]
RewriteCond %{QUERY_STRING} (^|&)pagename=contact(&|$)
RewriteRule ^(index\.php)?$ /kontakt? [R=301,L]
```

The converter produces this from the whole file:

```text
$ vendor/bin/hikkoshi convert public/.htaccess > site/config/hikkoshi.php
Skipped line 18 (condition is not a query-string condition): RewriteRule ^(.*)$ https://radladen.com/$1 [R=301,L]
14 entries converted, 1 lines skipped.
```

and the file `site/config/hikkoshi.php`:

```php
<?php
/**
 * Converted from public/.htaccess by hikkoshi convert.
 * Review before use and run: hikkoshi check
 */
return [
    '?page_id=13' => ['to' => 'ueber-mich', 'query' => 'drop'],
    '?page_id=36' => ['to' => 'bilder', 'query' => 'drop'],
    '?page_id=14' => ['to' => 'kontakt', 'query' => 'drop'],
    '?p=14' => ['to' => 'kontakt', 'query' => 'drop'],
    '?pagename=contact' => ['to' => 'kontakt', 'query' => 'drop'],
    '?page_id=80' => ['to' => 'datenschutz', 'query' => 'drop'],
    '?page_id=82' => ['to' => 'impressum', 'query' => 'drop'],
    '?page_id=2' => ['to' => 'home', 'query' => 'drop'],
    '?page_id=12' => ['to' => 'home', 'query' => 'drop'],
    '?page_id=15' => ['to' => 'home', 'query' => 'drop'],
    '?page_id=16' => ['to' => 'home', 'query' => 'drop'],
    '?page_id=17' => ['to' => 'home', 'query' => 'drop'],
    '?p=1' => ['to' => 'home', 'query' => 'drop'],
    '?feed' => ['to' => 'home', 'query' => 'drop'],
];
```

Fourteen entries come out. The `RewriteRule` that was skipped is the rule that sends `www.radladen.com` to the bare domain. It depends on the host name, so it stays in `.htaccess` and is not a redirect of the list. That is why the converter exits with 1: it is a review signal, not a failure. The seven rewrites that pass requests to `index.php` produce no entries.

Every target ends in `?` in the Apache rules, so every entry has `'query' => 'drop'`. The matched `page_id` or `p` parameter is always removed anyway; `drop` also removes `utm_*` parameters. Write plain strings instead of the array form if you want to keep them.

Lines the converter cannot translate are skipped and listed, never guessed. Skipped lines, with the reason:

| Reason | Meaning |
|---|---|
| `pattern is not a literal path` | a regular expression with real pattern characters |
| `condition is not a query-string condition` | a `RewriteCond` on something other than `%{QUERY_STRING}`, such as the host |
| `target cannot be converted` | a target with `$1`, or one that is not a path or URL |
| `status is not a redirect status` | a status such as 303 |
| `old path already converted` | a duplicate old path; the second line is skipped |

## Taking over `.htaccess` rules

Until Hikkoshi, the WordPress query-string redirects of radladen had to be written as Apache rules. Kirby routes see only the path, `/`, so `/?page_id=82` looks like the home page to Kirby; only Apache's `RewriteCond %{QUERY_STRING}` could tell the addresses apart. Hikkoshi's route receives the query string, so the query-string rules can move into the list (`'?page_id=82' => 'impressum'`).

Move the rules that depend on the request's path or query, and keep what belongs to the web server:

| Stays in `.htaccess` | Why |
|---|---|
| `www.` to the bare domain (`RewriteCond %{HTTP_HOST}` plus `RewriteRule`) | depends on the host name, which Hikkoshi does not match |
| `DirectoryIndex index.php`, the `index.html` rule | how the web server picks the index file |
| the rules that send `media/`, `content/`, `site/`, `kirby/` and missing files to `index.php` | Kirby's front controller and access protection |
| `Header` and `AddType` lines, `CGIPassAuth` | web server configuration |

Once the list works (`vendor/bin/hikkoshi check` and the site test pass), delete the converted rules from `.htaccess`, together, in the same deploy. Apache answers before PHP does, so a rule left in `.htaccess` wins over the list; check the live site after the switch.

Old image addresses (`/wordpress/wp-content/uploads/…`) are not redirected. Kirby gives its own names to media files, so there is no one-to-one mapping. A redirect to the home page is a soft 404 for search engines; an honest 404 is better.

## Test helper for sites

Two checks belong in the site's test suite: one that boots Kirby and checks the real routes and pages, and one that checks the list without Kirby. Both exit 1 on any finding.

### With Kirby

`tests/HikkoshiTest.php`:

```php
<?php

/**
 * Redirects of the site: every old address answers as its entry says, every
 * target is a published page, nothing is looped or chained.
 * Run: php tests/HikkoshiTest.php
 */

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';

// The same roots as public/index.php. Hikkoshi must be active in site/config/config.php.
// A list with an error stops Kirby while it starts, as on the live site:
// the error findings come with the exception.
try {
    $kirby = new Kirby\Cms\App([
        'roots' => [
            'index'   => $base . '/public',
            'base'    => $base,
            'content' => $base . '/content',
            'site'    => $base . '/site',
            'storage' => $base . '/storage',
        ],
    ]);
} catch (Hikkoshi\ConfigException $e) {
    foreach ($e->findings() as $finding) {
        fwrite(STDERR, $finding['level'] . ': ' . $finding['message'] . PHP_EOL);
    }
    exit(1);
}

$findings = $kirby->site()->hikkoshiCheck();

foreach ($findings as $finding) {
    fwrite(STDERR, $finding['level'] . ': ' . $finding['message'] . PHP_EOL);
}

exit($findings === [] ? 0 : 1);
```

Errors in the list (a loop, a duplicate, a foreign host) never reach `hikkoshiCheck()`: Kirby registers the routes while it starts, and a list with an error throws `Hikkoshi\ConfigException` there — the same 500 the live site would show. That is why the example catches it. `hikkoshiCheck()` then reports the warnings and what only the running site can tell.

`hikkoshiCheck()` runs each entry through the router and renders the target pages. It is slower than the list check, but it finds what only the site knows: a missing or draft target page, an address that answers differently than its entry says, and a target that is itself redirected. Use `hikkoshiCheck(false)` to check the list and the target pages without rendering.

### Without Kirby

The list check uses the same validator as `vendor/bin/hikkoshi check`, and needs no Kirby boot. `tests/HikkoshiListTest.php`:

```php
<?php

/**
 * The redirect list alone, without Kirby: no loops, no duplicates, no
 * foreign targets outside hikkoshi.allowedHosts.
 * Run: php tests/HikkoshiListTest.php
 */

$base = dirname(__DIR__);
$src  = $base . '/site/plugins/hikkoshi/src/';

foreach (['I18n', 'Options', 'Path', 'ConfigException', 'Validator'] as $class) {
    require_once $src . $class . '.php';
}

use Hikkoshi\Validator;

$result = Validator::validate(require $base . '/site/config/hikkoshi.php', [
    'allowedHosts' => ['partner.example'],   // the same as hikkoshi.allowedHosts
]);

foreach ($result['findings'] as $finding) {
    fwrite(STDERR, $finding['level'] . ': ' . $finding['message'] . PHP_EOL);
}

exit(Validator::hasErrors($result['findings']) ? 1 : 0);
```

Without the `allowedHosts` setting the check reports every external target as foreign, so give it the same list as the site.

## Site and page methods

| Method | Returns |
|---|---|
| `$site->hikkoshiRedirects()` | the validated, normalised entries; `[]` while Hikkoshi is off |
| `$site->hikkoshiResolve(string $path, array $query = [])` | the decision for a path and query, as the route would take it, or `null`. The array has the entry's fields plus `pass` (`exact` or `suffix`), `remaining`, `page` (the target page, `null` for 410 and external targets) and `location` (the address for the `Location` header, `null` for 410). |
| `$site->hikkoshiCheck(bool $render = true)` | the list of findings (errors and warnings), `[]` when all is well. See [Test helper for sites](#test-helper-for-sites). |
| `$page->hikkoshiSources()` | the old paths (keys) that redirect to this page, `[]` while off; the home page includes `'home'` |

```php
$decision = $site->hikkoshiResolve('/Impressum.html', ['utm_source' => 'qr']);

if ($decision !== null) {
    echo $decision['status'];      // 301
    echo $decision['location'];    // the address of the target with the query kept
}
```

`hikkoshi_option(string $key, mixed $default = null)` reads any `hikkoshi.*` option, for snippets with their own markup:

```php
<?php

if (hikkoshi_option('language') === 'de') {
    // German markup
}
```

`hikkoshiResolve()` and the route do not differ: both call the same code. Use the method in a test or to debug one address.

## The 410 page

An entry with the value `410` answers with the status 410 and the snippet `hikkoshi/gone`. The plugin's snippet is a plain page with only `hikkoshi-gone*` classes and no styling. To give it your own markup, create `site/snippets/hikkoshi/gone.php`; Kirby looks there first:

```php
<?php
/** @var string $title */
/** @var string $text */
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?></title>
</head>
<body class="hikkoshi-gone">
  <main>
    <h1 class="hikkoshi-gone-title"><?= htmlspecialchars($title) ?></h1>
    <p class="hikkoshi-gone-text"><?= htmlspecialchars($text) ?></p>
  </main>
</body>
</html>
```

The snippet receives `$title` and `$text`, already translated (the translation keys are `hikkoshi.gone.title` and `hikkoshi.gone.text`). To change the wording, write it into the snippet. Escape every value you print.

## Upgrading from `site/plugins/weiterleitungen`

Hikkoshi replaces the pre-1.0 copy `site/plugins/weiterleitungen` (plugin id `weiterleitungen/kern`), which was copied into several sites. The list file stays as it is.

> **Remove the copy.** Delete `site/plugins/weiterleitungen/` when the package is installed. Both register routes for the same old paths, and the copy would answer first, with its old rules. Hikkoshi does not keep an alias for the old class or plugin id.

Steps:

1. `composer require sayamaapps/kirby-hikkoshi`
2. Delete `site/plugins/weiterleitungen/`.
3. Add `'hikkoshi.active' => true` to `site/config/config.php`. **The copy was on whenever its list file existed; Hikkoshi is off until this switch is set.**
4. Keep `site/config/weiterleitungen.php` (it is read unchanged), or rename it to `site/config/hikkoshi.php`.
5. Replace `tests/WeiterleitungenTest.php` and `tests/WeiterleitungenHttpTest.php` with the site test from [Test helper for sites](#test-helper-for-sites).
6. Look for other tests that load the copy: `grep -rn 'plugins/weiterleitungen' tests/`. mustergasthaus's Playwright spec `tests/weiterleitungen.spec.ts` `require`s `site/plugins/weiterleitungen/index.php` against a fake Kirby App to test rejected keys; that test fails once the folder is gone. Replace it with a loop over `Hikkoshi\Validator::assert([$bad => 'hofladen'])` (it throws `ConfigException`, a `LogicException` as before), or drop it in favour of the list test. Note that `'a%2ehtml'` is now decoded to `a.html` and accepted.
7. Run `vendor/bin/hikkoshi check`, then the site test.

| Before (copy) | Now (Hikkoshi) |
|---|---|
| `Weiterleitungen\Liste::pruefe($ziele)` returns the list or throws `LogicException` | `Hikkoshi\Validator::assert($config)` returns the entries or throws `Hikkoshi\ConfigException` (which extends `LogicException`) |
| `require site/config/weiterleitungen.php` | the same file, or `site/config/hikkoshi.php`, or the option `hikkoshi.redirects` |
| on when the file exists | off until `'hikkoshi.active' => true` |
| one route per entry, exact string match | one catch-all route with normalised matching |
| a chain makes every request fail with 500 | a chain is a warning in the log; the site works. Errors only in `hikkoshiCheck()` and the CLI; `resolveChains` |
| loop makes every request fail with 500 | unchanged |
| keys like `'Menue'`, `'/menue'`, `'menue/'`, `'über-uns'`, `'%c3%bcber-uns'` rejected | accepted and normalised; `'a?b=1'` is a query condition |
| uppercase request `/Menue` gives 404 | 301 (with `lowercase` on) |
| `/impressum` for the entry `'impressum.html'` gives 404 | unchanged 404 (no reverse pass) |
| always 301, query always kept | `status` and `query` options, per-entry overrides; matched condition keys removed |
| targets: page ids only | plus 410 and external URLs on allowed hosts |

The German class names and the old plugin id are gone. The German messages of the copy are still available as `hikkoshi.language => 'de'`.

## Privacy

Hikkoshi reads the request path and query to decide on a redirect, sends a `Location` header or a static 410 page, stores nothing, sets no cookies, uses no browser storage and makes no outbound request. Configuration problems are written to the server's error log, without visitor data.

- [PRIVACY.md](PRIVACY.md): English summary.
- [DATENSCHUTZ.md](DATENSCHUTZ.md): German text block for a site's privacy policy and notes for the operator. It describes the implementation; it is not legal advice.

The operator alone decides which foreign hosts may be redirect targets (`hikkoshi.allowedHosts`). The web server's access logs are outside the plugin and are covered by the site's own privacy policy.

## Development

```sh
composer install
ASDF_PHP_VERSION=8.5.3 sh tests/run.sh
```

The tests are plain PHP scripts (exit code 0 = pass), without PHPUnit, so they also run on a hoster's PHP. The Kirby tests boot a throwaway site in a temporary directory and render it in-process; no test starts a server or makes a request.

Changes to the matching rules or the config contract are major versions. See [CHANGELOG.md](CHANGELOG.md).

## License

MIT. See `LICENSE`.

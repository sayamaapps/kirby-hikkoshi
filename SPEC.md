# Hikkoshi — build specification

Hikkoshi (引っ越し, moving house) is a Kirby 5 plugin for redirects from the
old addresses of a site that moved to Kirby (Jimdo, Wix, WordPress): a checked
list of old paths (loops, chains, foreign domains, duplicates), tolerant
matching (case, trailing slash, percent-encoding, umlauts, `.html`/`index.php`
suffixes, query-string addresses), 301 by default, 302/307/308/410 per entry, a
converter for Apache `.htaccess` rules and a test helper for sites. **No
outbound HTTP at runtime, no styling.**

Package `sayamaapps/kirby-hikkoshi`, plugin id `sayamaapps/hikkoshi`, namespace
`Hikkoshi`, option prefix `hikkoshi.`, directory this repository.

It replaces `site/plugins/weiterleitungen` (plugin id `weiterleitungen/kern`,
class `Weiterleitungen\Liste`), a byte-identical copy in kirby-starter and in the
sites built from it, and takes over the WordPress query-string redirects that a
migrated site still writes as Apache rules.

This document is the contract. Section numbers are referenced from code
comments and task prompts.

---

## 1. Ground rules for every builder

- PHP 8.2 compatible code (no typed class constants, no `readonly` classes,
  no 8.3+ functions). Run with `ASDF_PHP_VERSION=8.5.3 php …` (asdf otherwise
  says "No version is set"). Composer: `php ~/Applications/PHP_Composer/composer.phar`.
- Code, identifiers, comments, README in **English**. German only in
  `translations/de.php` and `DATENSCHUTZ.md`.
- Every user-facing string comes from `I18n::t()` (keys in §5). If you need a
  key that does not exist, do NOT add it — write it into `NOTES-<task>.md` and
  use the closest existing key.
- Only edit files your task owns (§2). Need a change in a file you do not own
  (a bug in Validator.php, a missing translation, a helper)? Write it into
  `NOTES-<task>.md` in the plugin root: file, problem, proposed change. Do not
  work around it with a copy of the logic.
- Tests: standalone PHP scripts `tests/<Name>Test.php`, exit code 0 = pass, no
  PHPUnit. Use `tests/helpers.php` (`expect()`, `holds()`, `only()`, `codes()`,
  `finish()`, Kirby fixtures `hikkoshi_site()`, `hikkoshi_kirby()`,
  `hikkoshi_write_config()`, `hikkoshi_render()`, `hikkoshi_page()`,
  `hikkoshi_remove_tree()`). Kirby-free tests require `src/I18n.php`,
  `src/Options.php`, `src/Path.php` and the classes they need directly, never
  `index.php`. Kirby tests never require src files; Kirby loads the plugin
  through the symlink `hikkoshi_site()` creates.
- `src/Options.php` loads without Kirby: `Kirby\Cms\App` appears only in type
  hints. Kirby-free code uses only `Options::DEFAULTS`, `Options::FILES`,
  `Options::PREFIX`.
- Do not push, do not create GitHub repos, do not touch any other repository
  (the sources in §0 are read-only). Never delete files outside the plugin
  directory. Do not run `git commit`.
- No outbound HTTP anywhere, not even in tests (the pre-1.0 HTTP test started
  a local PHP server; the port renders in-process, §8.5).

### 0. Sources (read-only)

| What | Path |
|---|---|
| Original plugin | `../kirby-starter/site/plugins/weiterleitungen/{index.php,src/Liste.php}` |
| Original config template | `../kirby-starter/site/config/weiterleitungen.example.php` |
| Original tests | `../kirby-starter/tests/WeiterleitungenTest.php` (rules), `../kirby-starter/tests/WeiterleitungenHttpTest.php` (real requests) |
| Real lists (compatibility contract) | two migrated sites' lists, not in this repo: a Jimdo one (14 entries) and a Wix one (12 entries), copied verbatim into the tests |
| Apache rules to take over | a migrated WordPress site's `public/.htaccess` (`?page_id=` redirects), not in this repo; the test reads it from `../wp-site/` when present |
| Site e2e expectations | a site's Playwright spec (catches `\LogicException`) |
| Family conventions | `../kirby-noren` (SPEC, README, CHANGELOG, PRIVACY, DATENSCHUTZ, tests), `../kirby-koe` (bin/ script), `../kirby-kizami` |

---

## 2. File tree and owners

`architect` = written before the build, shared, **builders never edit**.
`architect → <task>` = written by the architect as working code; from now on
owned by that task, which verifies it with tests and may fix it.
`stub → <task>` = a placeholder so the plugin loads; the task replaces the
whole file.

```
kirby-hikkoshi/
├── composer.json                    architect
├── composer.lock, vendor/           generated (composer install), not committed
├── .gitignore, .gitattributes       architect
├── LICENSE                          architect
├── .github/workflows/ci.yml         architect
├── SPEC.md                          architect
├── index.php                        architect   (complete registration, §3.11)
├── bin/hikkoshi                     architect   (CLI entry, §3.11)
├── config/hikkoshi.example.php      architect   (§6; ValidatorTest checks it is valid)
│                                    (unlike noren/koe/kizami, which keep the template in each
│                                    site's site/config/: the starter has no Hikkoshi template yet;
│                                    when the sites migrate, the starter gets site/config/hikkoshi.example.php)
├── src/
│   ├── I18n.php                     architect   (§3.10)
│   ├── Options.php                  architect   (§3.10, §4)
│   ├── Path.php                     architect   (§3.1)
│   ├── ConfigException.php          architect → core   (§3.2.3)
│   ├── Validator.php                architect → core   (§3.2)
│   ├── Resolver.php                 architect → core   (§3.3)
│   ├── Redirects.php                stub → kirby       (§3.4)
│   ├── SiteCheck.php                stub → kirby       (§3.6)
│   ├── Htaccess.php                 stub → cli         (§3.7)
│   ├── CheckCommand.php             stub → cli         (§3.8)
│   └── ConvertCommand.php           stub → cli         (§3.9)
├── snippets/hikkoshi/gone.php       placeholder → kirby (§3.5)
├── translations/en.php, de.php      architect   (§5)
├── tests/
│   ├── run.sh                       architect
│   ├── helpers.php                  architect
│   ├── I18nTest.php                 architect
│   ├── PathTest.php                 architect
│   ├── ValidatorTest.php            core      (§8.1)
│   ├── ResolverTest.php             core      (§8.2)
│   ├── KirbyTest.php                kirby     (§8.3)
│   ├── SiteCheckTest.php            kirby     (§8.4)
│   ├── HtaccessTest.php             cli       (§8.5)
│   ├── CheckCommandTest.php         cli       (§8.6)
│   └── ConvertCommandTest.php       cli       (§8.7)
├── README.md                        docs   (§9)
├── CHANGELOG.md                     docs
├── PRIVACY.md                       docs
└── DATENSCHUTZ.md                   docs
```

Run everything: `cd this repository && ASDF_PHP_VERSION=8.5.3 sh tests/run.sh`.

---

## 3. Architecture and public API

```
request path + query ──► Redirects::routes()  (one catch-all GET|HEAD route)
                              │
                              ▼
                     Redirects::respond() ── Resolver::match() ── entries
                              │                                     ▲
             ┌────────────────┴──────────────┐                      │
     Response::redirect(location, 301…)   410 + snippet      Validator::validate()
                                                                    ▲
                                             site/config/hikkoshi.php | weiterleitungen.php
                                             or option hikkoshi.redirects

bin/hikkoshi check   ── CheckCommand   ── Validator        (Kirby-free)
bin/hikkoshi convert ── ConvertCommand ── Htaccess         (Kirby-free)
$site->hikkoshiCheck() ── SiteCheck    ── Validator, Resolver, Kirby router
```

Kirby-free: `Path`, `I18n`, `Options` (constants only), `ConfigException`,
`Validator`, `Resolver`, `Htaccess`, `CheckCommand`, `ConvertCommand`.
Kirby glue: `Redirects`, `SiteCheck`, `index.php`, the snippet.

### Shapes used everywhere

**Entry** (output of the Validator, input of the Resolver):

```php
[
    'key'        => 'index.php?p=1',     // the config key as written (string)
    'source'     => 'index.php',         // normalised path part, '' = home, never leading/trailing slash
    'conditions' => ['p' => '1'],        // query conditions: name => value, or name => null ("present, any value"); [] = none
    'target'     => 'home',              // page id, or absolute URL when external, or null for 410
    'status'     => 301,                 // 301 | 302 | 307 | 308 | 410
    'query'      => 'keep',              // 'keep' | 'drop'   (always 'drop' for 410)
    'external'   => false,               // true when target is a URL on an allowed host
]
```

**Finding** (Validator, SiteCheck, CLI):

```php
[
    'level'   => 'error',                       // Validator::ERROR | WARNING | INFO
    'code'    => 'loop',                        // translation key without 'finding.'
    'message' => 'Redirect loop: a → b → a',    // I18n::t('finding.' . code, values), current language
    'key'     => 'a',                           // config key of the entry concerned, or null
    'values'  => ['chain' => 'a → b → a'],      // the placeholder values
]
```

### 3.1 `Hikkoshi\Path` (src/Path.php — written, architect)

Kirby-free path rules. Read the file; summary:

| Method | Does |
|---|---|
| `normalize(string $path, bool $lowercase = true, bool $transliterate = true): string` | trim, percent-decode (twice at most), NFC, `//` → `/`, strip slashes at both ends, `mb_strtolower`, `ä→ae ö→oe ü→ue ß→ss`. `'/Über-Uns/'` → `'ueber-uns'`, `'/'` → `''`. |
| `split(string $key): array{path: string, conditions: array<string, ?string>}` | `'?page_id=82'` → `['path' => '', 'conditions' => ['page_id' => '82']]`; `'index.php?p=1&feed'` → `['path' => 'index.php', 'conditions' => ['p' => '1', 'feed' => null]]`. Path part is NOT normalised. |
| `stripSuffix(string $path, array $suffixes, array $indexFiles): ?string` | `'impressum.html'` → `'impressum'`; `'blog/index.php'` → `'blog'`; `'index.html'` → `''`; `'impressum'` → null; `'karte.pdf'` → null. |
| `isValidSource(string $path): bool` | segments `[a-zA-Z0-9_-]+` with at most one dot each; `''` is NOT valid. |
| `isValidTarget(string $target): bool` | lowercase page id: segments `[a-z0-9_-]+`, no dots, no slashes at the ends. `'Produkte'`, `'/produkte'`, `'produkte.html'` invalid. |
| `externalHost(string $target): ?string` | `'https://Example.org/x'` → `'example.org'`; `'//example.org'` → `'example.org'`; page id → null; `javascript:` → null (and invalid). |
| `query(array $query): string` | RFC 3986 query string, `''` for `[]`. |

### 3.2 `Hikkoshi\Validator` (src/Validator.php — written, architect → `core`)

```php
public static function validate(mixed $config, array $settings = []): array   // ['entries' => list<Entry>, 'findings' => list<Finding>]
public static function assert(mixed $config, array $settings = []): array     // list<Entry>; throws ConfigException on any error
public static function hasErrors(array $findings): bool
public static function ofLevel(array $findings, string $level): array
public static function finding(string $level, string $code, array $values, ?string $key): array
public static function identity(string $source, array $conditions): string    // 'menue' | '?p=1&q=2' (conditions ksorted)
public const ERROR = 'error'; WARNING = 'warning'; INFO = 'info';
public const STATUSES = [301, 302, 307, 308, 410]; QUERY_MODES = ['keep', 'drop'];
```

`$settings` is the `Options::settings()` shape (§4); missing keys come from
`Options::DEFAULTS`. Entries with an error are **dropped** from `entries`;
warnings and infos keep their entry. Order of `entries` = config order.

#### 3.2.1 Per-entry rules (in this order; the first failing rule wins)

| # | Condition | Finding (code, level) | Values |
|---|---|---|---|
| 0 | `$config` is not an array | `config.notArray` error | — (returns immediately) |
| 1 | key is an int (`'2024' => …` or a list `['produkte']`) | `source.numeric` error | entry |
| 2 | `Path::split(key)`; `source = Path::normalize(path, lowercase, transliterate)`; `source === ''` and no conditions | `source.empty` error | entry |
| 3 | `source !== ''` and `!Path::isValidSource(source)` | `source.invalid` error | entry |
| 4 | value: int other than 410 | `entry.invalid` error | entry |
| 5 | value: array with a key other than `to`, `status`, `query` | `entry.invalid` error | entry |
| 6 | array `status` not int in STATUSES | `status.invalid` error | status (var_export), entry |
| 7 | array `query` not in QUERY_MODES | `query.invalid` error | query (var_export), entry |
| 8 | array with `status => 410` AND a `to` | `entry.invalid` error | entry |
| 9 | array without `to` (status ≠ 410) | `target.missing` error | entry |
| 10 | array `to` not a string, or value neither string/int/array (null, bool, float, object) | `target.invalid` error | entry |
| 11 | target is a URL (`Path::externalHost()` ≠ null) and host not in `allowedHosts` (compared lowercase, also without `:port`) | `target.foreign` error | host, entry |
| 11a | allowed URL with whitespace or a control character inside (`\s`, `\x00–\x1F`, `\x7F`; Kirby would send a mangled Location). An allowed URL's scheme is stored lowercase (`HTTPS:` makes Kirby's Uri throw) | `target.invalid` error | entry |
| 12 | not external and `!Path::isValidTarget(target)` | `target.invalid` error | entry |
| 13 | not external, no conditions, `target === source` | `target.self` error | entry |
| 14 | `identity(source, conditions)` seen before | `source.duplicate` error | source (= identity), entry, target (first entry's target, or its status for 410) |

`entry` is always `I18n::entry($key, $value)`: `'old' => 'new'`, arrays on
one line as `['to' => 'x', 'status' => 302]`. Values are `trim()`med. `410`
entries get `target null, status 410, query 'drop', external false`. A
string value takes `status`/`query` from settings; the array form overrides
them per entry.

Key normalisation means `'Menue'`, `'/menue/'`, `'über-uns'`, `'%c3%bcber-uns'`
and `'a%2ehtml'` are **accepted** (stored as `menue`, `menue`, `ueber-uns`,
`ueber-uns`, `a.html`) — the pre-1.0 copy rejected them (§7). Still rejected:
`../secret`, `a/../secret`, `.hidden`, `a..html`, `a.b.c`, `a(*)`, `(:all)`,
`blog/(:any)`, `https://example.org`, `mit leerzeichen`, `''`.

#### 3.2.2 Loops and chains (after all entries)

A *plain* entry has `conditions === []`. A target page id `T` is captured by
another redirect iff a plain entry has `source === T` (a redirect to a page
id carries no query condition, so a conditional entry is not counted — with
query `keep` it can still catch a forwarded query; README → Validation names
that limit). The target `home` is never captured: its redirect lands on `/`,
which the source `home` (`/home`) does not match.

For every non-external, non-410 entry E, follow `target → plain[target] →
…`:

- the walk returns to a source already visited → **`loop` error**, values
  `chain` = `'a → b → a'`. Each loop is reported once (members remembered);
  E's `key` is the finding's key. A list with a loop is never resolved.
- the walk makes at least one hop (E's target is a plain source) → the chain
  text is `start → … → final`, where `start` is E's source (or E's key when E
  has conditions) and `final` is the last entry's target, the URL, or `'410'`:
  - `resolveChains` false → **`chain` warning**, values `chain`;
  - `resolveChains` true → **`chain.resolved` info**, values `chain`, `target`
    (= final target or `'410'`), and E takes the final entry's `target` and
    `external`; when the final is 410, also `status 410`, `query 'drop'`.
    E keeps its own status and query otherwise.

Examples (`→` = chain text): `['a'=>'b','b'=>'c']` → warning `a → b → c`
(reported for `a` only; `b → c` is no chain). `['a'=>'b','b'=>'c','c'=>'d']` →
warnings for `a` (`a → b → c → d`) and `b` (`b → c → d`). `['a'=>'b','b'=>410]`
+ resolve → info `a → b → 410`, `a` becomes 410. `['?page_id=1'=>'start','start'=>'produkte']`
→ warning `?page_id=1 → start → produkte`. `['?page_id=1'=>'home','home'=>'start']`
→ no chain (see above). `['impressum.html'=>'impressum']` → no
chain (`impressum` is not a source). `['produkte'=>'produkte','shop'=>'produkte']`
→ no chain.

#### 3.2.3 `Hikkoshi\ConfigException` (src/ConfigException.php — written, architect → `core`)

`final class ConfigException extends \LogicException` — the pre-1.0 type, which
a site's own test catches it. Constructor takes the full
findings list; `getMessage()` = error messages joined with `"\n"`;
`findings(): array` (all), `errors(): array` (errors only).

### 3.3 `Hikkoshi\Resolver` (src/Resolver.php — written, architect → `core`)

```php
public function __construct(array $entries, array $settings = [])
public function match(string $path, array $query = []): ?array
    // ['entry' => Entry, 'pass' => 'exact'|'suffix', 'path' => 'impressum', 'remaining' => array]
public function entries(): array                 // all entries, grouped by source
public function settings(): array
public static function conditionsMatch(array $conditions, array $query): bool
public static function targetQuery(array $match): array      // remaining when entry keeps the query, [] for drop/410
public static function appendQuery(string $url, array $query): string
```

Matching (`match()`):

1. `n = Path::normalize(path, lowercase, transliterate)`; look up `n`: among
   the entries with `source === n`, the first whose conditions all hold
   (`conditionsMatch`), **more conditions first**, config order among equals.
   → `pass 'exact'`.
2. No hit: `s = Path::stripSuffix(n, suffixes, indexFiles)`; when `s !== null`
   look up `s` the same way → `pass 'suffix'`. (`/Impressum.html` hits
   `'impressum'`; `/index.php?page_id=13` hits `'?page_id=13'`; `/blog/index.php`
   hits `'blog'`.)
3. Nothing → `null`. There is no third pass: `'impressum.html'` as the only
   entry does NOT catch `/impressum` or `/impressum.php` (the pre-1.0 contract:
   the suffix-free page is the new page itself).

`conditionsMatch`: `name => null` holds when the query has the key;
`name => 'v'` holds when `(string) $query[name] === 'v'`; an array value
(`probe[]=1`) never equals a string. `remaining` = `$query` minus the
condition keys of the matched entry (`?page_id=82&utm_source=qr` →
`['utm_source' => 'qr']`).

`appendQuery('https://x.example/p?a=1', ['b'=>'2'])` → `…?a=1&b=2`;
`('http://h', ['u'=>'q'])` → `http://h?u=q` (the pre-1.0 form for the home
page); fragment kept at the end; `[]` appends nothing.

### 3.4 `Hikkoshi\Redirects` (src/Redirects.php — stub → `kirby`)

The Kirby glue. All methods are static and take `App $kirby`.

```php
public static function config(App $kirby): mixed
```
The raw list: option `hikkoshi.redirects` when set (an array, or a Closure
which is called and whose return value is used); else the file named by
`hikkoshi.file` (absolute, or relative to `$kirby->root('config')`; missing →
`Options::invalid()` with a plain English message and `[]`); else the first
existing file of `Options::FILES` (`hikkoshi.php`, then `weiterleitungen.php`)
in `$kirby->root('config')`; else `[]`. A file is `require`d and whatever it
returns is passed on (the Validator rejects non-arrays).

```php
public static function entries(App $kirby): array      // list<Entry>, [] when inactive
public static function resolver(App $kirby): Resolver  // over entries(), same cache
```
`[]` when `!Options::active($kirby)`. Otherwise `Options::applyLanguage()`,
then `Validator::validate(config, Options::settings())`: any **error → throw
`ConfigException`** (so every request fails with 500 instead of one entry
being skipped silently — pre-1.0 behaviour, §7); each warning/info message →
`Options::log()` (once per process). Result cached per App instance in a
`\WeakMap` (two site methods and the route must not validate three times).

```php
public static function routes(App $kirby): array
```
`[]` when inactive, else **exactly one** route:
```php
['pattern' => '(:all)', 'method' => 'GET|HEAD', 'action' => function (string $path = '') use ($kirby) {
    $response = Redirects::respond($kirby, $path, $kirby->request()->query()->toArray());
    if ($response === null) { Route::next(); }   // falls through to Kirby (pages, 404, …)
    return $response;
}]
```
`entries()` is called inside `routes()` so a broken list throws at route
registration. Kirby runs plugin routes after its own `before` routes (api,
media, panel) and before the page route, so the catch-all costs one array
lookup per page request. POST and other methods are untouched (an old form
address must not become a GET).

```php
public static function resolve(App $kirby, string $path, array $query = []): ?array
```
The decision, or `null` when inactive, no match, or the internal target page
is missing/draft (then the request falls through to Kirby — pre-1.0: a draft as
target gives the normal treatment of the old path). Returns the Entry fields
plus:
```php
'pass'      => 'exact'|'suffix',
'remaining' => [...],                               // Resolver match remaining
'page'      => Page|null,                           // the target page (internal), null for 410/external
'location'  => 'http://example.invalid/produkte?utm_source=qr' | null (410),
```
Target page: `$page = $kirby->site()->find($target)`; when null and
`$target === 'home'` → `$kirby->site()->homePage()`; `null` or
`$page->isDraft()` → return null. Location: internal →
`Resolver::appendQuery($page->url(), Resolver::targetQuery($match))`; external
→ `Resolver::appendQuery($entry['target'], …)`. The home page URL has no
trailing slash (`http://example.invalid`, then `http://example.invalid?utm=…`).

```php
public static function respond(App $kirby, string $path, array $query = []): ?Response
```
`null` when `resolve()` is null. Status 410 → `new Response($kirby->snippet('hikkoshi/gone',
['title' => I18n::t('gone.title'), 'text' => I18n::t('gone.text')], true), 'text/html', 410)`
(language already applied by `entries()`). Otherwise
`Response::redirect($location, $status)`.

```php
public static function sourcesFor(App $kirby, Page $page): array   // list<string> of keys
```
Keys of entries whose `target === $page->id()`, or `'home'` when the page is
the home page (`$page->isHomePage()`), config order; `[]` when inactive.

### 3.5 Snippet `snippets/hikkoshi/gone.php` (placeholder → `kirby`)

Body of a 410 response. Variables `$title`, `$text` (translated strings).
Complete HTML document, `<meta charset="utf-8">`, `<title>`, only
`hikkoshi-gone*` classes (`hikkoshi-gone` on body, `hikkoshi-gone-title`,
`hikkoshi-gone-text`), every value `htmlspecialchars`ed, no CSS. A site
overrides it with `site/snippets/hikkoshi/gone.php` (Kirby looks there first).
The placeholder already has this shape; the owner verifies and keeps it.

### 3.6 `Hikkoshi\SiteCheck` (src/SiteCheck.php — stub → `kirby`)

```php
public static function run(App $kirby, bool $render = true): array   // list<Finding>, [] = all well
```
The test helper for sites: "every entry resolves in at most one hop to a 200
page". Infos are never returned (a resolved chain is not a problem).

1. `!Options::active` → `[finding(error, 'inactive')]`, stop.
2. `Options::applyLanguage`; `Validator::validate(Redirects::config, Options::settings)`
   (NOT assert). Findings = its errors and warnings. Any error → return now.
   (Reachable only for an App whose list changed after boot, as in the tests:
   a normally built App with a broken list throws `ConfigException` while it
   registers the routes, before `hikkoshiCheck()` runs. README → Test helper
   shows the site test catching it.)
3. Per entry:
   - internal with target: page lookup as in `Redirects::resolve()`; missing
     or draft → error `page.missing`, values `target`, `entry`.
   - self-consistency: `Redirects::resolver($kirby)->match($entry['source'], $conditionsAsQuery)`
     where `null` conditions become `'1'`; the hit's `key` must be the entry's
     key → else error `check.noMatch`, values `source` (= identity), `entry`.
   - `$render` and no conditions: `[$code, $location] = self::request($kirby, '/' . source)`.
     `$code !== status` → error `check.status`, values `source`, `actual`,
     `expected`. For redirects: `$location !== Redirects::resolve(...)['location']`
     → error `check.location`, values `source`, `actual`, `expected`.
   - `$render` and internal page exists (each page once): `self::request($kirby, '/' . $page->uri())`
     (`''` for home). 301/302/307/308 → error `check.hop`, values `target`,
     `entry`; any other code ≠ 200 → error `check.target`, values `target`,
     `actual`.
4. `request(App $kirby, string $path): array{0:int, 1:?string}` (private):
   `$result = $kirby->router()->call(trim($path, '/'), 'GET')` — NOT
   `$kirby->render()` (it writes into the one shared `Responder`, so a 404
   would stick to every later render). Map: `Response` → `[code, (string) Location header or null]`;
   `Page` → `[isErrorPage ? 404 : 200, null]` (call `$page->render()` first:
   a throwing template counts as 500); `File` → `[200, null]`; `null` → `[404, null]`;
   `Throwable` → `[getHttpCode() for Kirby\Exception\Exception, else 500, null]`.

### 3.7 `Hikkoshi\Htaccess` (src/Htaccess.php — stub → `cli`)

Kirby-free converter for Apache rules. Never touches files.

```php
public static function convert(string $text): array
    // ['entries' => array<string, mixed>, 'skipped' => list<['line' => int, 'text' => string, 'reason' => string]>]
public static function render(array $entries, string $header = ''): string   // PHP source of a config file
```

`reason` is the translation key suffix: `pattern`, `condition`, `target`,
`status`, `duplicate` (`I18n::t('reason.' . reason)` for display). `line` is
1-based; `text` the trimmed directive line.

Parsing, line by line (trim; join a line ending in `\` with the next; skip
empty lines, `#` comments and everything that is not `Redirect`,
`RedirectMatch`, `RewriteCond`, `RewriteRule` — those are *ignored*, not
*skipped*; directive names case-insensitive):

**`Redirect [status] /old [target]`** — status token optional:
`301|302|307|308|410|permanent|temp|gone` (`permanent`=301, `temp`=302,
`gone`=410, no token = 302). `303`/`seeother` or anything else → `status`.
Old path must start with `/`; key = path without the leading slash (verbatim,
the Validator normalises later). 410: no target, value `410`. Otherwise target
rules below.

**`RedirectMatch [status] regex target`** — only a *literal* regex: strip `^`
and `$`, a trailing `/?`, unescape `\.` `\-` `\/`; if any unescaped regex
meta (`. * + ? ( ) [ ] { } | \`) remains → `pattern`. Then as `Redirect`.

**`RewriteCond %{QUERY_STRING} regex [flags]`** — collected for the next
`RewriteRule`. Accepted regex: optional `(^|&)` or `^` prefix, then
`name=value`, optional `(&|$)` or `$` suffix. `value` may be
`[A-Za-z0-9_%.-]+` with `\.` unescaped, an alternation `(a|b|c)` (one
condition per alternative), or **empty** (`feed=`) meaning "present, any
value" (→ `name` without `=` in the key). Flags: `[OR]` recognised, `[NC]`
ignored. A `RewriteCond` on anything but `%{QUERY_STRING}`, or an
unparseable regex, poisons the group: the following `RewriteRule` is skipped
with `condition` (reported with the rule's line) and the group resets.

Groups: Apache ANDs consecutive conds, `[OR]` ORs with the next. Build
`alternatives = [[]]`, `pending = []`; for each cond: `pending[] = cond`; if
the cond has no `[OR]`: `alternatives = [alt + [p] for alt in alternatives for p in pending]`,
`pending = []`. At the rule, a trailing OR-pending is merged the same way.
Each alternative becomes one key: `path . '?' . implode('&', conds)` with
`name=value` or bare `name`; conditions within a key in cond order. One
`RewriteRule` therefore yields several keys (WordPress .htaccess: `?page_id=14`,
`?p=14`, `?pagename=contact` all → `kontakt`; `page_id=(2|12|15|16|17)` →
five keys).

**`RewriteRule pattern target [flags]`** — flags in `[...]`, comma-separated:
`R` → 302, `R=301` etc. → that status (only STATUSES minus 410 … `R=410`
accepted as 410 without target); **no `R` flag → ignored** (an internal
rewrite is not a redirect: Kirby's own `index.php` rules produce no output).
Pattern: literal only, like RedirectMatch, plus these exact WordPress forms →
path `''`: `^$`, `^/?$`, `^(index\.php)?$`, `^index\.php$` → `'index.php'`.
Other meta → `pattern`. Path `''` **without** conditions → `pattern` (home
cannot be a source). Target rules below; `-` → `target`. Entries in file order.

**Target rules** (Redirect, RedirectMatch, RewriteRule): `/` → `home`;
`/path` → `path` (leading slash removed, trailing slash removed); a trailing
`?` (`/ueber-mich?`, `/?`) means Apache dropped the query → `query drop`;
`http://…` or `https://…` → external, verbatim (the Validator decides whether
the host is allowed; a trailing `?` is removed and means drop); contains `$1`
or `%1`, or does not start with `/` or `http` → `target`.

**Value shape** of `entries[key]`: 410 → `410`; status 301 and no drop →
`'id'` (string); otherwise `['to' => 'id']` plus `'status' => n` when
`n !== 301` plus `'query' => 'drop'` when dropped — keys in that order.

**Duplicates**: a key already produced → `duplicate` (second occurrence
skipped, the line is reported).

`render($entries, $header)`:
```php
<?php
/**
 * {header, one or more lines, each prefixed " * "}
 */
return [
    '?page_id=13' => ['to' => 'ueber-mich', 'query' => 'drop'],
    'alt' => 410,
    'produkte' => 'produkte',
];
```
Keys via `var_export`, values via `I18n::export()`, four-space indent, one
entry per line, trailing comma, `"\n"` line ends. No header → no docblock.
Empty entries → `return [\n];`.

**Expected result for `../wp-site/public/.htaccess`** (the acceptance
case): 14 entries in this order, all `['to' => …, 'query' => 'drop']`:
`?page_id=13`→ueber-mich, `?page_id=36`→bilder, `?page_id=14`→kontakt,
`?p=14`→kontakt, `?pagename=contact`→kontakt, `?page_id=80`→datenschutz,
`?page_id=82`→impressum, `?page_id=2`, `?page_id=12`, `?page_id=15`,
`?page_id=16`, `?page_id=17`, `?p=1`, `?feed` → home. Exactly one skipped
line: the `RewriteRule ^(.*)$ https://example.com/$1 [R=301,L]` with
reason `condition` (its `RewriteCond %{HTTP_HOST}`). Nothing else is reported
(the seven internal `index.php` rewrites are ignored).

### 3.8 `Hikkoshi\CheckCommand` (src/CheckCommand.php — stub → `cli`)

```php
public static function run(array $args, $out, $err, string $cwd): int
```
`$args` = everything after `check`. Flags: `--lang=xx` (already applied by
bin, accept and ignore), `--resolve-chains`, `--allow-host=HOST` (repeatable),
`--strict`; the first non-flag argument is FILE. Unknown `--flag` → usage
(`cli.usage`) to `$err`, return 2.

File: given → as is when absolute, else `$cwd . '/' . FILE`; missing →
`cli.fileMissing` to `$err`, 2. Not given → the first existing
`$cwd . '/site/config/' . name` for `Options::FILES`; none → `cli.noFile`
(values `files` = the tried paths) to `$err`, 2.

Then `cli.file` to `$out`; `$config = require $file` (a `Throwable` → its
message to `$err`, return 1); `Validator::validate($config, array_replace(Options::DEFAULTS,
['resolveChains' => …, 'allowedHosts' => […]]))`. Each finding on its own
line to `$out`: `I18n::t('level.' . level) . ': ' . message`. Then
`cli.ok` (values `entries`) when there are no findings at all, else
`cli.summary` (values `entries`, `errors`, `warnings`). Return 1 when errors,
or warnings with `--strict`; else 0.

### 3.9 `Hikkoshi\ConvertCommand` (src/ConvertCommand.php — stub → `cli`)

```php
public static function run(array $args, $out, $err, string $cwd): int
```
FILE required (none → usage to `$err`, 2; not found → `cli.fileMissing`, 2).
`Htaccess::convert(file_get_contents)`; `$out` ← `Htaccess::render($entries,
"Converted from {FILE as given} by hikkoshi convert.\nReview before use and run: hikkoshi check")`;
`$err` ← one `cli.skipped` line per skipped entry (values `line`, `reason` =
`I18n::t('reason.' . reason)`, `text`), then `cli.convertSummary` (values
`entries`, `skipped`). Return 1 when anything was skipped, else 0. Never
writes a file.

### 3.10 `Hikkoshi\I18n`, `Hikkoshi\Options` (written, architect)

`I18n`: `LANGUAGES = ['en', 'de']`, `setLanguage()`, `language()`, `table()`,
`t($key, $values)` (array values joined with `', '`), `entry($key, $value)`
(`'old' => 'new'`, arrays one-line), `export($value)`.

`Options`: `PREFIX`, `FILES`, `DEFAULTS` (§4), `get()`, `active()`,
`settings()`, `hosts()`, `strings()`, `flag()`, `invalid()` (throws with
Kirby `debug`, else logs once), `log()`, `language()`, `applyLanguage()`.

### 3.11 index.php and bin/hikkoshi (written, architect)

Site methods: `hikkoshiRedirects(): array` (entries, `[]` off),
`hikkoshiResolve(string $path, array $query = []): ?array`
(`Redirects::resolve`), `hikkoshiCheck(bool $render = true): array`
(`SiteCheck::run`). Page method: `hikkoshiSources(): array`. Global
`hikkoshi_option($key, $default)`. Snippet `hikkoshi/gone`. Translations
`hikkoshi.<key>`. Routes via `Redirects::routes()`.

`bin/hikkoshi check|convert …` requires the Kirby-free src files and
dispatches to `CheckCommand::run` / `ConvertCommand::run` with
`(array_slice($argv, 2), STDOUT, STDERR, getcwd())`; `--lang=` anywhere sets
I18n first. Exit 2 and usage for no/unknown command.

---

## 4. Options

All read as `hikkoshi.<name>`, flat or nested; `null` = not set. `Options::settings()`
returns the starred ones, normalised, for the Kirby-free classes.

| Option | Default | Meaning |
|---|---|---|
| `active` | `false` | Master switch; only `true` switches on. Off: no route, methods return `[]`/`null`, `hikkoshiCheck()` reports `inactive`. |
| `redirects` | `null` | The list as an array or a Closure returning one. Overrides the file. |
| `file` | `null` | Path of the list file (absolute or relative to `site/config/`). Default: `site/config/hikkoshi.php`, then `site/config/weiterleitungen.php` (`Options::FILES`). |
| `status` * | `301` | Status of entries without their own: 301, 302, 307, 308. Invalid → logged, 301. |
| `query` * | `'keep'` | `keep` passes the remaining query parameters on (matched condition keys are always removed); `drop` sends none. |
| `allowedHosts` * | `[]` | Hosts (lowercase, string or list) that may be redirect targets. Nothing else is ever an external target (open-redirect safety). The Kirby-free entry points accept the string form too (`Options::lists()`, also for `suffixes`/`indexFiles`). |
| `resolveChains` * | `false` | `true` replaces a chain's target by the final one (info instead of warning). |
| `lowercase` * | `true` | Lowercase both sides before matching. `false` = exact case (the pre-1.0 behaviour). |
| `transliterate` * | `true` | `ä→ae ö→oe ü→ue ß→ss` on both sides. |
| `suffixes` * | `['html', 'htm', 'php']` | Suffixes removed from the last segment for the second lookup pass. `[]` = off. |
| `indexFiles` * | `['index.php', 'index.html', 'index.htm']` | Last segments removed for the second pass (`blog/index.php` → `blog`, `index.php` → home). `[]` = off. |
| `language` | site language | `en` or `de`; else current/default language, `locale`, English. Messages in the log and the 410 page. |

---

## 5. Translation keys

Complete tables: `translations/en.php`, `translations/de.php` (written;
`tests/I18nTest.php` keeps them in sync). Registered in Kirby as
`hikkoshi.<key>`. `{entry}` is always `I18n::entry()`.

| Key | en | de |
|---|---|---|
| `level.error` | error | Fehler |
| `level.warning` | warning | Warnung |
| `level.info` | info | Hinweis |
| `finding.config.notArray` | The redirect list must be an array old path => target. | Die Weiterleitungsliste muss ein Array alt => neu sein. |
| `finding.source.numeric` | Old path missing or purely numeric: {entry} | Alter Pfad fehlt oder ist rein numerisch: {entry} |
| `finding.source.invalid` | Invalid old path: {entry} | Ungültiger alter Pfad: {entry} |
| `finding.source.empty` | The home page can only be an old path together with a query condition (such as '?page_id=82'): {entry} | Die Startseite kann nur zusammen mit einer Query-Bedingung (etwa '?page_id=82') ein alter Pfad sein: {entry} |
| `finding.source.duplicate` | Duplicate old path {source}: {entry} (already points to {target}) | Doppelter alter Pfad {source}: {entry} (zeigt bereits auf {target}) |
| `finding.entry.invalid` | Invalid entry: {entry}. Allowed are a page id, 410, or an array with the keys to, status and query. | Ungültiger Eintrag: {entry}. Erlaubt sind eine Seiten-ID, 410 oder ein Array mit den Schlüsseln to, status und query. |
| `finding.target.invalid` | Invalid redirect: {entry} | Ungültige Weiterleitung: {entry} |
| `finding.target.missing` | Entry without target: {entry} | Eintrag ohne Ziel: {entry} |
| `finding.target.foreign` | Foreign domain {host} is not allowed as a target: {entry}. Add it to hikkoshi.allowedHosts. | Fremde Domain {host} ist als Ziel nicht erlaubt: {entry}. In hikkoshi.allowedHosts eintragen. |
| `finding.target.self` | Redirect to itself: {entry} | Weiterleitung auf sich selbst: {entry} |
| `finding.status.invalid` | Invalid status {status}: {entry}. Allowed: 301, 302, 307, 308, 410. | Ungültiger Status {status}: {entry}. Erlaubt: 301, 302, 307, 308, 410. |
| `finding.query.invalid` | Invalid query setting {query}: {entry}. Allowed: keep, drop. | Ungültige Query-Einstellung {query}: {entry}. Erlaubt: keep, drop. |
| `finding.loop` | Redirect loop: {chain} | Weiterleitungsschleife: {chain} |
| `finding.chain` | Redirect chain: {chain}. Enter the final target directly. | Weiterleitungskette: {chain}. Das Ziel direkt eintragen. |
| `finding.chain.resolved` | Redirect chain {chain} resolved to {target}. | Weiterleitungskette {chain} auf {target} aufgelöst. |
| `finding.inactive` | Hikkoshi is off: set 'hikkoshi.active' => true. | Hikkoshi ist aus: 'hikkoshi.active' => true setzen. |
| `finding.page.missing` | Target page {target} does not exist or is a draft: {entry} | Zielseite {target} existiert nicht oder ist ein Entwurf: {entry} |
| `finding.check.noMatch` | The old path {source} does not match its own entry: {entry} | Der alte Pfad {source} trifft seinen eigenen Eintrag nicht: {entry} |
| `finding.check.status` | {source} answers {actual} instead of {expected} | {source} antwortet mit {actual} statt {expected} |
| `finding.check.location` | {source} redirects to {actual} instead of {expected} | {source} leitet auf {actual} statt {expected} |
| `finding.check.target` | Target {target} answers {actual} instead of 200 | Ziel {target} antwortet mit {actual} statt 200 |
| `finding.check.hop` | Target {target} of {entry} is itself redirected | Ziel {target} von {entry} wird selbst weitergeleitet |
| `gone.title` | Page removed | Seite entfernt |
| `gone.text` | This page has been removed permanently. | Diese Seite wurde dauerhaft entfernt. |
| `cli.usage` | (multi-line usage text, see file) | (Aufruf …) |
| `cli.file` | Checking {file} | Prüfe {file} |
| `cli.unknownCommand` | Unknown command: {command} | Unbekannter Befehl: {command} |
| `cli.fileMissing` | File not found: {file} | Datei nicht gefunden: {file} |
| `cli.noFile` | No redirect list found (tried {files}). | Keine Weiterleitungsliste gefunden (versucht: {files}). |
| `cli.ok` | OK: {entries} entries, no findings. | OK: {entries} Einträge, keine Befunde. |
| `cli.summary` | {entries} entries, {errors} errors, {warnings} warnings. | {entries} Einträge, {errors} Fehler, {warnings} Warnungen. |
| `cli.skipped` | Skipped line {line} ({reason}): {text} | Zeile {line} übersprungen ({reason}): {text} |
| `cli.convertSummary` | {entries} entries converted, {skipped} lines skipped. | {entries} Einträge übernommen, {skipped} Zeilen übersprungen. |
| `reason.pattern` | pattern is not a literal path | Muster ist kein fester Pfad |
| `reason.condition` | condition is not a query-string condition | Bedingung ist keine Query-String-Bedingung |
| `reason.target` | target cannot be converted | Ziel lässt sich nicht übernehmen |
| `reason.status` | status is not a redirect status | Status ist kein Weiterleitungsstatus |
| `reason.duplicate` | old path already converted | alter Pfad bereits übernommen |

The German `finding.target.invalid`, `finding.target.self`, `finding.chain`
and `finding.source.numeric` texts reproduce the pre-1.0 messages word for
word (the ported tests compare them).

---

## 6. Config contract

The list is a PHP array `old => target`, returned by `site/config/hikkoshi.php`
(or the unchanged pre-1.0 `site/config/weiterleitungen.php`, or the option
`hikkoshi.redirects`). Template: `config/hikkoshi.example.php`.

| Key form | Meaning |
|---|---|
| `'ueber-uns'`, `'angebot/kiste'`, `'impressum.html'`, `'downloads/karte.pdf'` | an old path (normalised: case, slashes, umlauts, percent-encoding do not matter) |
| `'?page_id=82'` | the home page with the query parameter `page_id=82` (WordPress plain permalinks) |
| `'index.php?p=14'` | path plus condition; matches `/index.php?p=14` only. `'?p=14'` matches `/?p=14` and (via indexFiles) `/index.php?p=14` |
| `'?feed'` | parameter `feed` present with any value |
| `'a?k=1&l=2'` | both conditions |

| Value form | Meaning |
|---|---|
| `'produkte'`, `'leistungen/beratung'`, `'home'` | page id (lowercase, no slashes at the ends); `home` = the home page. Status/query from the options. |
| `410` | gone: 410 with the `hikkoshi/gone` snippet |
| `['to' => 'produkte', 'status' => 302, 'query' => 'drop']` | per-entry status (301/302/307/308) and query mode; `['status' => 410]` is also gone |
| `'https://other.example/x'` or `['to' => 'https://…']` | external, only with the host in `hikkoshi.allowedHosts` |

Request handling: GET and HEAD only; the matched condition parameters are
removed, the rest is kept (`keep`) or dropped; redirect only while the target
page is published (draft/missing → the request falls through to Kirby, usually
404). Plugin routes come before Kirby's page lookup: an old path under which a
real page still exists redirects — the page is then unreachable (pre-1.0, kept).

Not covered: multi-language path prefixes are part of the path (`'de/menue'`
would be the key) — not tested, documented as a limitation.

---

## 7. Compatibility with site/plugins/weiterleitungen (the pre-1.0 copy)

**Must be removed when the package is installed**: both register routes for
the same old paths and the copy would answer first. The list file stays:
`site/config/weiterleitungen.php` is read unchanged. Site tests
`tests/WeiterleitungenTest.php` / `WeiterleitungenHttpTest.php` are replaced by
a call to `$site->hikkoshiCheck()` (README shows how).

| Before | Now |
|---|---|
| `Weiterleitungen\Liste::pruefe($ziele)` → list or `LogicException` | `Hikkoshi\Validator::assert($config)` → entries or `Hikkoshi\ConfigException` (extends `LogicException`) |
| `require site/config/weiterleitungen.php` | same file, or `site/config/hikkoshi.php`, or option `hikkoshi.redirects` |
| off when the file is missing | off until `'hikkoshi.active' => true` |
| one route per entry, exact string match | one catch-all route, normalised match (§3.3) |
| chain → every request 500 | chain → warning in the log (site works), error only in `hikkoshiCheck()`/CLI; `resolveChains` |
| loop → 500 | loop → 500 (unchanged) |
| `'Menue'`, `'/menue'`, `'menue/'`, `'über-uns'`, `'%c3%bcber-uns'`, `'a?b=1'` rejected | accepted and normalised (`'a?b=1'` is a query condition) |
| uppercase request `/Menue` → 404 | → 301 (with `lowercase` on) |
| `/impressum` for the entry `'impressum.html'` → 404 | unchanged 404 (no reverse pass) |
| always 301, query always kept | `status`/`query` options and per-entry overrides; matched condition keys removed |
| targets: page ids only | plus 410 and allow-listed external URLs |

Behaviour changes are listed in CHANGELOG under 1.0.0 (docs task).

---

## 8. Test plan

Every test file starts with a docblock (what it covers, how to run), requires
`tests/helpers.php`, ends with `finish('Hikkoshi <Suite>')`. German
expectations run after `I18n::setLanguage('de')`; reset to `'en'` afterwards.
Kirby-free tests require `../src/I18n.php`, `../src/Options.php`,
`../src/Path.php` and the classes under test, in that order.

### 8.1 tests/ValidatorTest.php (task `core`) — Kirby-free

Port **every** check of `kirby-starter/tests/WeiterleitungenTest.php`, with
these adaptations, each named in a comment:

- "valid list unchanged": `Validator::assert($list)` returns entries whose
  `key`/`source`/`target` equal the list, `status 301`, `query 'keep'`,
  `conditions []`, `external false`; `assert([])` → `[]`.
- `config/hikkoshi.example.php` is valid (no errors; exactly which findings:
  none). Also the two real lists copied verbatim into the test (Jimdo 14
  entries, Wix 12 entries) → no findings at all.
- `['2024' => 'archiv']` → one error `source.numeric`, message contains
  `rein numerisch` (de); `['archiv/2024' => 'archiv']` accepted.
- the former "invalid old paths" split into (a) still rejected (error
  `source.invalid`/`source.empty`, codes): `../secret`, `a/../secret`,
  `.hidden`, `a..html`, `a.b.c`, `a(*)`, `(:all)`, `blog/(:any)`,
  `https://example.org`, `mit leerzeichen`, `''`; (b) now accepted with their
  normalised source: `Menue`→`menue`, `Impressum.HTML`→`impressum.html`,
  `über-uns`→`ueber-uns`, `%c3%bcber-uns`→`ueber-uns`, `a%2ehtml`→`a.html`,
  `/menue`→`menue`, `menue/`→`menue`, `a//b`→`a/b`, `a?b=1`→source `a`,
  conditions `['b' => '1']`. With `['lowercase' => false]`: `Menue` keeps its
  case; with `['transliterate' => false]`: `über-uns` is rejected
  (`source.invalid`).
- invalid targets (each one error, code given): `https://example.org` and
  `//example.org` → `target.foreign` (host `example.org`); `/produkte`,
  `produkte/`, `produkte.html`, `../produkte`, `Produkte`, `produkte?x=1`,
  `''`, `(:all)`, `null` → `target.invalid`; `['produkte']` → `entry.invalid`;
  `301` → `entry.invalid`; non-array config (`'produkte'`, `1`) →
  `config.notArray`.
- self: `['menue' => 'menue']` → `target.self`, message contains `sich selbst`
  (de); `['Menue/' => 'menue']` also self (normalised).
- loop: `['a'=>'b','b'=>'a']` → exactly one `loop` error, chain `a → b → a`,
  message `Weiterleitungsschleife: a → b → a` (de); `assert()` throws
  `ConfigException` which `instanceof LogicException`, `getMessage()` is that
  line, `errors()` has 1, `findings()` has 1. Three-way loop `a→b→c→a` → one
  error.
- chain: `['a'=>'b','b'=>'c']` → NO error, one `chain` warning, message exactly
  `Weiterleitungskette: a → b → c. Das Ziel direkt eintragen.` (de), `key` `a`;
  `assert()` does NOT throw and returns both entries unchanged; order
  `['b'=>'c','a'=>'b']` gives the same warning; `['a'=>'archiv/2024','archiv/2024'=>'archiv']`
  → warning; `['a'=>'b','b'=>'c','c'=>'d']` → two warnings (`a → b → c → d`,
  `b → c → d`); `['?page_id=1'=>'home','home'=>'start']` → warning
  `?page_id=1 → home → start`.
- resolveChains: `['a'=>'b','b'=>'c']` + `['resolveChains'=>true]` → one
  `chain.resolved` info, `a` has target `c`, `b` unchanged; `['a'=>'b','b'=>410]`
  → `a` becomes `status 410`, `target null`, `query 'drop'`; a loop with
  resolveChains → `loop` error and nothing resolved.
- no chain: `['produkte'=>'produkte','shop'=>'produkte']`,
  `['impressum.html'=>'impressum']`, `['a'=>'b','b.html'=>'c']`.
- value forms: `410` → entry `target null status 410 query drop`;
  `['status'=>410]` same; `['to'=>'x','status'=>308,'query'=>'drop']`;
  `['to'=>'x']` inherits settings `status 302` when passed; `['to'=>'x','status'=>200]`
  → `status.invalid` with values `status` `200`; `['to'=>'x','query'=>'maybe']`
  → `query.invalid`; `['status'=>410,'to'=>'x']` → `entry.invalid`;
  `['status'=>302]` → `target.missing`; `['to'=>'x','foo'=>1]` → `entry.invalid`.
- external: `['m'=>'https://Example.org/x']` + `allowedHosts ['example.org']`
  → `external true`, target kept as written; `https://example.org:8080/y` also
  allowed by the port-less host; `//example.org` allowed too; host not listed →
  `target.foreign`.
- duplicates: `['menue'=>'a','Menue/'=>'b']` → `source.duplicate` with values
  `source 'menue'`, `target 'a'`, only the first entry survives;
  `['?p=1&q=2'=>'x','?q=2&p=1'=>'y']` → duplicate with source `?p=1&q=2`;
  duplicate of a 410 reports target `410`.
- `source.empty`: `['' => 'x']` and `['?' => 'x']`; `['?page_id=82' => 'impressum']`
  accepted with source `''`.
- messages: `Ungültiger alter Pfad: '../secret' => 'produkte'` exact (de; code
  `source.invalid`, §3.2.1 rule 3 and §5);
  English default `Invalid old path: '../secret' => 'produkte'`; array entry
  printed on one line `'m' => ['to' => 'x', 'status' => 200]`.
- `identity('a', [])` → `a`; `identity('', ['q'=>'2','p'=>null])` → `?p&q=2`.
- `hasErrors`, `ofLevel` on a mixed findings list.

### 8.2 tests/ResolverTest.php (task `core`) — Kirby-free

Build entries with `Validator::assert()` and the default settings unless
stated. Cases (expected `key` of the hit, `pass`, `remaining`):

- `menue`: `/menue`, `/menue/`, `menue`, `/MENUE`, `/Menue/`, `/menue?x` (path
  only — query is the second argument) → `menue`/exact; `/menue/unterseite`
  → null; `/menu` → null.
- `/menue` + query `['utm_source'=>'qr','probe'=>['1']]` → remaining is the
  whole query; `targetQuery` returns it (keep); with entry `query drop` → `[]`.
- suffixes: entries `['impressum'=>'legal','blog'=>'news']`: `/impressum.html`,
  `/Impressum.HTML`, `/impressum.htm`, `/impressum.php` → `impressum`/suffix,
  path `impressum`; `/blog/index.php`, `/blog/index.html` → `blog`/suffix;
  `/impressum.pdf` → null; with `suffixes []` → `/impressum.html` null; with
  `indexFiles []` → `/blog/index.php` null.
- pre-1.0 contract: entry `['impressum.html'=>'impressum']` only: `/impressum.html`
  → hit (exact); `/impressum`, `/impressum.php` → null.
- umlauts/encoding: entry `['ueber-uns'=>'about']`: `/über-uns`, `/%C3%BCber-uns`,
  `/Ü%62er-uns`, `/%25C3%25BCber-uns` → hit; with `transliterate false` →
  `/über-uns` null, `/ueber-uns` hit.
- `lowercase false`: entry `['Menue'=>'x']` (source stays `Menue`): `/Menue` hit,
  `/menue` null.
- conditions (WordPress .htaccess): entries `['?page_id=13'=>'ueber-mich','?page_id=14'=>'kontakt','?p=14'=>'kontakt','?pagename=contact'=>'kontakt','?feed'=>'home','index.php?p=1'=>'home']`:
  `('', ['page_id'=>'13'])` → `?page_id=13` exact; `('/', ['page_id'=>'13','utm'=>'x'])`
  → same, remaining `['utm'=>'x']`; `('/index.php', ['page_id'=>'14'])` →
  `?page_id=14`/suffix; `('', ['feed'=>'rss2'])` and `('', ['feed'=>''])` → `?feed`;
  `('', ['page_id'=>'99'])` → null; `('', [])` → null; `('/index.php', ['p'=>'1'])`
  → `index.php?p=1` exact (exact beats suffix); `('', ['p'=>'1'])` → null
  (the key names `index.php`, the home page does not strip to it).
- specificity: entries `['a?k=v'=>'specific','a'=>'general']` in both orders:
  `('/a', ['k'=>'v'])` → `a?k=v`; `('/a', ['k'=>'w'])` → `a`, remaining
  `['k'=>'w']`; `('/a', [])` → `a`. Two conditions `['a?k=1&l=2'=>'x']`: both
  needed; `('/a', ['k'=>'1'])` → null. Array query value: `('/a', ['k'=>['v']])`
  with `a?k=v` → does not match the specific entry.
- `conditionsMatch` directly: `[]` always true; `['k'=>null]` with `['k'=>'']`
  true; `['k'=>'1']` with `['k'=>1]` (int) true (string compare).
- `appendQuery`: `('http://h/p', [])` → unchanged; `('http://h', ['u'=>'q'])`
  → `http://h?u=q`; `('https://x/p?a=1', ['b'=>'2'])` → `…?a=1&b=2`;
  `('https://x/p?', ['b'=>'2'])` → `…?b=2`; `('http://h/p#f', ['a'=>'1'])` →
  `http://h/p?a=1#f`; array parameter round-trips through `parse_str`.
- 410 entry: `targetQuery` → `[]` even with remaining.
- `entries()` returns all; `settings()` has the defaults merged.

### 8.3 tests/KirbyTest.php (task `kirby`) — Kirby

Port of `kirby-starter/tests/WeiterleitungenHttpTest.php`, rendered
in-process with `hikkoshi_render()` (no PHP server, no sockets). The fixture
`hikkoshi_site()` already has the pages of that test (speisekarte, produkte,
leistungen/beratung, team, draft produkt-b, home, error). Use
`hikkoshi_write_config()` for the list, `hikkoshi_kirby($tmp, $options,
$request)` for a fresh instance (a new instance per list; the query is a
property of the instance). Use a `withSite()` wrapper with try/finally like
noren's KirbyTest. Sections:

1. **Registered, off by default**: plugin `sayamaapps/hikkoshi` present;
   `hikkoshiRedirects()` `[]` and `/menue` → 404 while off even with a list
   file; `'hikkoshi.active' => 'yes'` stays off; translations
   `hikkoshi.gone.title` en/de; `hikkoshi_option('active')`.
2. **The pre-1.0 list** (`menue`, `produkt-c.html`,
   `tageskarte/karte-1.pdf`, `alt/tief/seite`, `index.html`,
   `produkt-b.html` → draft `produkt-b`, `team` → `produkte`) written
   as **`weiterleitungen.php`** (legacy file name) with `hikkoshi.active`:
   for each of the first five, `GET` and `HEAD`, with and without trailing
   slash → 301 and Location `http://example.invalid/<target>`
   (`http://example.invalid` for home); target paths render 200. Query kept:
   instance with request query `['utm_source'=>'qr','probe'=>['1']]` →
   Location path `/speisekarte`, `parse_str` of its query equals the array;
   `/menue/` too. **Changed:** `/MENUE`, `/Menue`, `/Produkt-c.html`
   → 301 now (comment: pre-1.0 gave 404). Draft target → 404, no Location.
   `/team` → 301 to produkte (page hidden). `POST /menue` → not 301.
   `/speisekarte` → 200 body `Page speisekarte`; `/gibt-es-nicht` → 404 no
   Location; `/menue/unterseite` → 404.
3. **Without a list**: file removed → `/menue` 404, `/` 200.
4. **Broken list = 500 on every request** (pre-1.0, kept) for: loop
   `['a'=>'b','b'=>'a']`, pattern `['(:all)'=>'produkte']`, foreign domain
   `['menue'=>'https://example.org']`, duplicate `['menue'=>'a','Menue'=>'b']`:
   `/` → 500, body contains neither `example.org` nor `Weiterleitungsschleife`
   (`debug false`). **Changed:** chain `['menue'=>'speisekarte','speisekarte'=>'produkte']`
   → `/` 200 and `/menue` → 301 to speisekarte (two hops), and the warning
   appears in the PHP error log (capture with `ini_set('error_log', file)`,
   see noren's `withErrorLog`). After repairing the list a new instance gives
   301 again.
5. **New forms**: `hikkoshi.php` beats `weiterleitungen.php` when both exist;
   option `hikkoshi.redirects` (array and Closure) beats the files;
   `hikkoshi.file` relative and absolute; `410` entry → 410, body contains
   `hikkoshi-gone-title` and `Seite entfernt` with `'hikkoshi.language'=>'de'`,
   `Page removed` by default, no Location; a site snippet
   `site/snippets/hikkoshi/gone.php` replaces the body; per-entry `302`/`308`;
   `['to'=>…,'query'=>'drop']` with a request query → Location without query;
   `'hikkoshi.query'=>'drop'` globally; external `https://other.example/x`
   with `allowedHosts` → Location exactly `https://other.example/x?utm_source=qr`
   (query kept), without allow-list → 500.
6. **Query conditions**: list `['?page_id=82'=>'impressum' (add page), '?feed'=>'home', 'index.php?p=1'=>'home']`
   with pages created via `hikkoshi_page`: request `page_id=82&utm=x` on `''`
   → 301 `http://example.invalid/impressum?utm=x` (condition key removed,
   rest kept); on `/index.php` → same (suffix pass); `page_id=99` → 200 home
   (no match, Kirby renders home); `/index.php` with `p=1` → 301 to home.
7. **Suffix and umlaut on real requests**: `['impressum'=>'speisekarte','ueber-uns'=>'produkte']`:
   `/impressum.html`, `/Impressum.php` → 301; `/über-uns`, `/%C3%BCber-uns`
   → 301; `'hikkoshi.lowercase'=>false` → `/Impressum.html` 404.
8. **Site and page methods**: `hikkoshiRedirects()` count and first key;
   `hikkoshiResolve('/menue', ['a'=>'1'])` → `location`, `status 301`, `pass`;
   `hikkoshiResolve('/nope')` → null; `$kirby->page('produkte')->hikkoshiSources()`
   → `['produkt-c.html', 'team']` (config order);
   `$kirby->site()->homePage()->hikkoshiSources()` → `['index.html']`;
   all `[]`/null when off.
9. **Options validation**: `'hikkoshi.status'=>200` → logged once (error log
   file contains `hikkoshi.status`), 301 used; with `'debug'=>true` →
   `InvalidArgumentException` on the first request.

### 8.4 tests/SiteCheckTest.php (task `kirby`) — Kirby

`$kirby->site()->hikkoshiCheck()` / `SiteCheck::run()`:

- off → exactly `[inactive]` (codes()).
- the pre-1.0 list without the draft and `team` entries → `[]`.
- draft target (`produkt-b.html` → `produkt-b`) → `page.missing`;
  missing page → `page.missing`.
- chain `['menue'=>'speisekarte','speisekarte'=>'produkte']` → codes contain
  `chain` (warning) and `check.hop` (speisekarte renders as 301), nothing else.
- loop → codes `[loop]` only (no render checks).
- `'hikkoshi.redirects' => fn () => [...]` works.
- `run($kirby, false)` skips the render checks: the chain case then yields
  `[chain]` only.
- a 410 entry → `[]`; an external allowed target → `[]` (no render of the
  target); `'?page_id=82' => 'impressum'` with the page present → `[]`.
- the error page (`error`) as target → `check.target` with `actual 404`.
- a site snippet throwing in the target's template → `check.target` with
  `actual 500` (write a template `site/templates/boom.php` with `throw`).
- findings have the full shape (`level`, `code`, `message`, `key`, `values`)
  and German messages with `'hikkoshi.language' => 'de'`.

### 8.5 tests/HtaccessTest.php (task `cli`) — Kirby-free

- **Acceptance**: `Htaccess::convert(file_get_contents('../wp-site/public/.htaccess'))`
  — read from `dirname(__DIR__, 2) . '/wp-site/public/.htaccess'`; if the
  file is missing, `holds(true, 'real WordPress file not present')` and skip
  that block — gives exactly the 14 entries of §3.7 in order (compare the
  whole array), and exactly one skipped entry with `reason 'condition'` whose
  `text` starts with `RewriteRule ^(.*)$`. Additionally embed the redirect
  block of that file verbatim as a heredoc fixture so the test runs without
  the sibling repo, with the same expectation.
- `Redirect 301 /alt /neu` → `['alt' => 'neu']`; `Redirect permanent /a /b`;
  `Redirect /a /b` → `['a' => ['to' => 'b', 'status' => 302]]`; `Redirect 302 …`,
  `307`, `308`; `Redirect gone /alt` and `Redirect 410 /alt` → `['alt' => 410]`;
  `Redirect 303 /a /b` and `Redirect seeother …` → skipped `status`;
  `Redirect 301 /a /b?` → `['a' => ['to' => 'b', 'query' => 'drop']]`;
  `Redirect 301 /a /` → `['a' => 'home']`; `Redirect 301 /a https://x.example/p`
  → `['a' => 'https://x.example/p']`; `Redirect 301 /a https://x.example/p?` →
  `['a' => ['to' => 'https://x.example/p', 'query' => 'drop']]`;
  `Redirect 301 /a b` (no slash) → `target`; `Redirect 301 a /b` (old without
  slash) → `pattern`; `Redirect 301 /alt/ /neu/` → `['alt' => 'neu']`.
- `RedirectMatch 301 ^/alt/?$ /neu` → `['alt' => 'neu']`; `^/alt\.html$` →
  `['alt.html' => 'neu']`; `^/blog/(.*)$ /news` → `pattern`; `^/a|b$` → `pattern`.
- `RewriteRule ^alt/?$ /neu [R=301,L]` → `['alt' => 'neu']`; `[R,L]` → status
  302 array form; `[L]` (no R) → nothing, not even skipped; `^(.*)$ index.php [L]`
  → nothing; `RewriteRule ^alt$ - [R=410]` → `['alt' => 410]`... (`-` with
  `R=410` is gone; `-` with R=301 → `target`); `^alt$ /neu/$1 [R=301]` →
  `target`; `^(index\.php)?$ /neu [R=301]` without conds → `pattern`; `[NC,R=301,L]`
  flag order irrelevant; `[R=301,QSD,L]` fine.
- RewriteCond groups: single cond `(^|&)page_id=13(&|$)` + rule `^(index\.php)?$ /x? [R=301,L]`
  → `['?page_id=13' => ['to' => 'x', 'query' => 'drop']]`; three `[OR]` conds →
  three keys; alternation `page_id=(2|12)` → two keys; `feed=` → `?feed`;
  `^p=1$` → `?p=1`; `p=1` (no anchors) → `?p=1`; two conds without OR →
  `?a=1&b=2`; `(A [OR] B) then C` → `?a=1&c=3` and `?b=2&c=3`; cond
  `%{HTTP_HOST} ^www\.` + rule with R → skipped `condition`, and the group is
  reset for the next rule; cond regex `page_id=.*` → `condition`; `[NC]` on a
  cond accepted; cond followed by a rule without R → nothing (group reset).
- duplicates: two rules for `/alt` → second skipped `duplicate`.
- line handling: `#` comments, blank lines, `<IfModule>` ignored; directive
  case-insensitive (`redirect 301 …`); `\` continuation joins lines; `line`
  numbers are 1-based and point at the directive line.
- `render([...], 'Header')` exact string for a mixed list (string, 410, array
  with status, array with drop); `render([])` → `"<?php\n\nreturn [\n];\n"`;
  header with two lines → two ` * ` lines; the rendered code `eval`s (strip
  `<?php`) back to the same array.

### 8.6 tests/CheckCommandTest.php (task `cli`) — Kirby-free

Run `CheckCommand::run($args, $out, $err, $cwd)` with `fopen('php://memory', 'w+')`
streams and a temp dir as `$cwd` (`sys_get_temp_dir()`, removed in finally):

- no file anywhere → 2, `$err` contains `No redirect list found`.
- `site/config/weiterleitungen.php` present and valid → 0, `$out` has
  `Checking …weiterleitungen.php` and `OK: 3 entries, no findings.`.
- `hikkoshi.php` and `weiterleitungen.php` both present → hikkoshi.php is used.
- explicit FILE relative and absolute; missing explicit FILE → 2 with `File not found`.
- loop list → 1, `$out` has `error: Redirect loop: a → b → a` and
  `2 entries, 1 errors, 0 warnings.`.
- chain list → 0, `$out` has `warning: Redirect chain: a → b → c. …`;
  with `--strict` → 1; with `--resolve-chains` → 0 and `info: Redirect chain a → b → c resolved to c.`.
- `--allow-host=other.example` makes `['a'=>'https://other.example/x']` pass; without → 1.
- `--bogus` → 2 and usage on `$err`; `--lang=de` accepted (bin sets the language; the
  test sets `I18n::setLanguage('de')` and sees `Fehler: …`).
- a config file that throws (`<?php throw new RuntimeException('boom');`) → 1, `$err` contains `boom`.
- Also run the real binary once: `exec(PHP_BINARY . ' ' . escapeshellarg(bin/hikkoshi) . ' check ' . file)`
  → exit code and output as above; `bin/hikkoshi` without args → 2 and usage;
  `bin/hikkoshi help` → 0; `bin/hikkoshi nope` → 2 with `Unknown command: nope`.

### 8.7 tests/ConvertCommandTest.php (task `cli`) — Kirby-free

- no FILE → 2 + usage on `$err`; missing FILE → 2 + `File not found`.
- a temp `.htaccess` with `Redirect 301 /a /b` and `Redirect 303 /c /d` → 1;
  `$out` is exactly the `Htaccess::render()` of `['a' => 'b']` with the header
  `Converted from <FILE> by hikkoshi convert.` / `Review before use and run: hikkoshi check`;
  `$err` has `Skipped line 2 (status is not a redirect status): Redirect 303 /c /d`
  and `1 entries converted, 1 lines skipped.`.
- only convertible lines → 0 and `$err` has `… 0 lines skipped.`.
- the WordPress file (when present) via the binary: exit 1, stdout `eval`s
  to the 14-entry array, stderr has exactly one `Skipped line` line.
- the command never writes: the temp dir contains only the input afterwards.

---

## 9. Documentation (task `docs`)

Model on `../kirby-noren` (README structure, CHANGELOG rules, PRIVACY and
DATENSCHUTZ tone). English everywhere except DATENSCHUTZ.md.

**README.md** sections: title + one-paragraph meaning of the name; feature
list; Requirements (PHP 8.2–8.4, Kirby 5); Installation (composer, repository
line until Packagist, `.gitignore` note); Quick start (config.php with
`hikkoshi.active`, the list file, `vendor/bin/hikkoshi check`); The list
(§6 tables with examples; key forms, value forms, `home`); Matching (§3.3
rules in prose with examples, both passes, what is NOT matched, methods);
Options (§4 table); Validation (findings table: what is an error, a warning,
an info; what happens at runtime: 500 vs. log); CLI (`check`, `convert`, exit
codes, the WordPress example with its output); Taking over `.htaccess`
rules (why `?page_id=` worked only in Apache before, what stays in `.htaccess`:
www→apex, assets); Test helper for sites (a complete `tests/HikkoshiTest.php`
example booting Kirby from `public/index.php`'s roots, calling
`$kirby->site()->hikkoshiCheck()`, failing on any finding; and the
Kirby-free `Validator::validate(require 'site/config/hikkoshi.php')` variant);
Site and page methods (`hikkoshiRedirects`, `hikkoshiResolve`, `hikkoshiCheck`,
`hikkoshiSources`, `hikkoshi_option`); 410 snippet override; Upgrading from
`site/plugins/weiterleitungen` (§7 table, the "remove the copy" warning, the
test replacement); Privacy (link); License.

**CHANGELOG.md**: Keep a Changelog, SemVer, `## [1.0.0] — unreleased`, with
the project rule "a change to the matching rules or the config contract is a
major version". Sections: Added (everything new vs. the copy), Changed
(breaking, with the §7 table: chains are warnings, normalised keys, uppercase
requests redirect, `active` switch, catch-all route), Removed (German class and
plugin names, no aliases).

**PRIVACY.md / DATENSCHUTZ.md**: the plugin reads the request path and query
to decide on a redirect, sends a `Location` header, stores nothing, sets no
cookies, makes no outbound request; configuration errors go to the server's
error log without visitor data; the 410 page is static text; `allowedHosts`
means the operator alone decides which foreign hosts are targets; web server
access logs are outside the plugin. DATENSCHUTZ.md: German text block for a
privacy policy + "Hinweise für den Betreiber", "beschreibt die
Implementierung, keine Rechtsberatung".

The docs task may run `sh tests/run.sh` but owns no test file.

---

## 10. Decisions for Simon

1. **Chains are warnings, not errors** (requirement 2). The pre-1.0 copy made
   every request 500 on a chain; now the site keeps working with two hops, the
   warning is logged once, `hikkoshiCheck()` and `hikkoshi check --strict`
   fail. Loops and duplicates still 500 (like before). If you want chains fatal
   at runtime again, say so: one line in `Redirects::entries()`.
2. **Config keys are normalised** like request paths. `'Menue'`, `'/menue/'`,
   `'über-uns'` are accepted now (the copy rejected them). Cost: nothing;
   benefit: keys can be pasted from a browser. Duplicates after normalisation
   are errors.
3. **No reverse suffix pass**: `'impressum.html' => 'impressum'` does not catch
   `/impressum`. The suffix pass goes one way only (request has a suffix, key
   has none). Reason: `/impressum` is the new page; a redirect there would
   shadow it.
4. **Query handling**: the matched condition parameters are always removed
   from the redirect (`/?page_id=82&utm=x` → `/impressum?utm=x`); `keep`/`drop`
   governs the rest. The converter therefore emits `'query' => 'drop'` for
   every Apache target ending in `?` (faithful to the `.htaccess`); for
   the WordPress file that is all 14 entries. You can strip the array form by hand
   if you prefer plain strings there — the only difference is `utm_*`
   parameters.
5. **Suffix pass default includes `php`**: `/impressum.php` → `impressum`.
   Jimdo/WordPress-era links use it; a Kirby site has no `.php` pages. Switch
   off with `'hikkoshi.suffixes' => ['html', 'htm']`.
6. **One catch-all route** instead of one route per entry: needed for
   case/umlaut/suffix/query matching. Cost: one array lookup per page
   request. POST is untouched.
7. **Targets stay strict**: lowercase page ids only, no leading slash
   (`'/kontakt'` is an error). The converter strips the slash, so pasted
   Apache targets still work.
8. **External targets** need `hikkoshi.allowedHosts` (open-redirect safety);
   `//host` counts as external. Port-less host in the list also allows
   `host:8080`.
9. **410 body** is a plain translated page via the snippet `hikkoshi/gone`;
   no option for the texts, a site overrides the snippet.
10. **`hikkoshi check` is Kirby-free**: it cannot know page existence or the
    site's `hikkoshi.*` options (flags for `--resolve-chains`,
    `--allow-host`). Page existence and real responses are
    `hikkoshiCheck()`'s job (the site test). Booting Kirby from the CLI was
    judged too fragile across hosters.
11. **Converter exit code 1 on skipped lines**: the www→apex rule of
    the WordPress www rule is skipped by design (it belongs in `.htaccess`), so the
    converter exits 1 there. It is a review signal, not a failure.
12. **Multi-language paths** (`/de/menue`) are not handled specially; the key
    would include the prefix. Documented as a limitation, no test.
13. **`SiteCheck` renders via `$kirby->router()->call()`**, not
    `$kirby->render()`, because Kirby's single `Responder` keeps the last
    status code. The test helper `hikkoshi_render()` resets that Responder
    through a bound closure (test code only).
14. **No deprecated aliases** for `Weiterleitungen\Liste` or the plugin id
    `weiterleitungen/kern`: the copy must be deleted on upgrade because both
    would register routes for the same paths.
15. **The `--strict` flag** (warnings → exit 1) was added beyond the request
    so CI can fail on chains without resolving them.

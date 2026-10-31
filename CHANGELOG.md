# Changelog

All notable changes to this project are documented here. Versions follow
[Semantic Versioning](https://semver.org) with these project rules:

- **A change to the matching rules or to the config contract** (the key and
  value forms of the redirect list, the normalisation of keys and request
  paths, the order of the passes) **is a major version**: a site would
  redirect other addresses after a routine update.

## [1.3.0] — 2026-10-31

### Changed

- Options are read as `sayamaapps.hikkoshi.<name>`. The 1.x names `hikkoshi.<name>` keep working and are read without a warning. If both are set, the new one wins.
- `Options::OPTION_PREFIX` is new. `Options::PREFIX` keeps its 1.x value.

## [1.2.0] — 2026-10-27

Compatible with 1.x: every 1.x name, option, message key and behaviour still works. The changes below that break a 1.x site are planned for 2.0 (see README).

### Changed

- **Classes live in the namespace `Sayamaapps\Hikkoshi\`.** The 1.x names `Hikkoshi\*` stay as aliases, and an alias is declared when its class loads, so `Hikkoshi\Validator` works without a prior `require` of the file. The exception keeps its 1.x class name at runtime (`Hikkoshi\ConfigException`, also in `get_class()` and uncaught output). The other classes report `Sayamaapps\Hikkoshi\*` in `get_class()` and in stack traces.
- **The classes are no longer `final`.** A site may extend them; nothing that worked before stops working.
- **Translations are also registered as `sayamaapps.hikkoshi.<key>`.** The 1.x keys `hikkoshi.<key>` stay registered.
- **The spec moved to `docs/SPEC.md`.** `docs/` and `scripts/` are no longer part of the dist archive.
- **Long methods are split** (`CheckCommand::run`, `Validator::chains`, `Validator::parseValue`, `Validator::validate`). The output and the results do not change.
- **Code style follows Kirby's rules** (`getkirby/php-cs`); `bin/hikkoshi` is checked too.
- **`composer ci` runs every check that CI runs**, including `phpcpd` and the platform requirements.

### Deprecated

- `hikkoshi_option()` and the `Hikkoshi\*` class names: use `Sayamaapps\Hikkoshi\Options::get()` and `Sayamaapps\Hikkoshi\*`. Nothing is raised at runtime; the README lists them.

### Planned for 2.0

- Options are read as `sayamaapps.hikkoshi.<name>` only; the 1.x spelling `hikkoshi.<name>` is no longer read.
- Broken options throw a Kirby exception instead of PHP's `InvalidArgumentException`.
- The translation keys `hikkoshi.<key>` and the `Hikkoshi\*` aliases are removed, with `hikkoshi_option()`.

## [1.1.1] — 2026-10-26

### Fixed

- **`convert` drops the request's query when an external target has a query of its own.** Apache replaces the request's query with the target's: `RewriteRule ^old$ https://other.example/new?mode=fixed [R=301,L]`, `Redirect 301 /old https://other.example/new?mode=fixed`, `RedirectPermanent`, `RedirectTemp` and `RedirectMatch` answer `/old?campaign=x` with `…/new?mode=fixed`. The converter kept the request's query (`query keep`), so the list sent `…?mode=fixed&campaign=x`. Such targets now give `'query' => 'drop'`, as `QSD` does; `QSA` still keeps the query in a RewriteRule. Targets without a query are unchanged. Lists converted earlier are not changed: convert the rules again.

### Removed

- **`DATENSCHUTZ.md` and `PRIVACY.md`.** Hikkoshi processes no visitor data, so it has no place in a site's privacy policy. The README says so under "Privacy".

## [1.1.0] — 2026-10-17

### Fixed

- **`hikkoshiCheck()` reads the status a target's template sets.** A target
  page whose template calls `$kirby->response()->code(503)` or
  `->redirect(...)` was reported as fine; the check assumed 200 for every
  rendered page that is not the error page. It now reads Kirby's Responder
  after rendering, as `App::io()` does, and reports `check.target` or
  `check.hop`. The Responder is reset before every simulated request and
  once after the check, so nothing leaks into the site's test process.
- **`convert` maps the `QSD` flag.** `RewriteRule … [R=301,QSD,L]` (also
  `qsdiscard`) discards the request's query in Apache; the converter ignored
  the flag and produced a plain entry with `query=keep`, so the parameters
  travelled on. QSD now gives `'query' => 'drop'`, like a trailing `?` on the
  target. `QSA` keeps the query even when the target ends in `?`, as in
  Apache.
- **`convert` no longer narrows prefix rules silently.** Apache's `Redirect`,
  `RedirectPermanent` and `RedirectTemp` match the old path as a prefix and
  forward the paths below it (`/old/child` → `/new/child`); a regex without
  a closing `$` does the same. The entry for the exact path is still
  converted, and the line is now reported as converted partially
  (`partial`, reason `prefix`) on standard error with exit code 1: add
  entries for the old subpaths, or keep that rule in `.htaccess`. A
  `RedirectMatch` or `RewriteRule` pattern without a leading `^` can match
  anywhere in the path and is skipped (`pattern`).
- **`convert` reads the `\/?` pattern form.** A literal regex ending in
  `\/?$` (`^/alt\/?$`) was skipped as `pattern`; it now converts like `/?$`.

### Changed

- **`convert` skips redirects with a flag the list cannot express** (`CO`,
  `C`, `S`, `N`, `P`, `T`, `H` or an unknown flag) with the new reason
  `flag`, instead of converting them silently. `L`, `END`, `NC`, `NE`,
  `E` and similar flags stay ignored; rules without `R` or `G` stay internal
  rewrites. A path target with a query of its own (`/page?x=1`) is skipped as
  `target`: the Validator accepts no `?` in a page id.
- `Htaccess::convert()` returns a third list `partial` next to `entries` and
  `skipped`. `hikkoshi convert` exits 1 when lines were skipped or converted
  partially.

### Added

- Translation keys `reason.flag`, `reason.prefix`, `cli.partial` and
  `cli.partialSummary`.

## [1.0.0] — 2026-10-11

First release as a Composer package. Before 1.0.0 the redirects lived as a copy
in each site: a plugin folder with the plugin id `weiterleitungen/kern` and the
class `Weiterleitungen\Liste`, and the WordPress redirects of a migrated site as
Apache rules. The changes below are measured against that copy.

### Added

- **Normalised matching.** Case, slashes at the ends, percent-encoding, Unicode
  normalisation, umlauts (`ä` = `ae`, `ö` = `oe`, `ü` = `ue`, `ß` = `ss`) and
  the suffixes `.html`, `.htm`, `.php` and the index files apply to request
  paths and to keys alike. Two passes: exact, then with one suffix or index
  file removed from the request. Options `lowercase`, `transliterate`,
  `suffixes`, `indexFiles`.
- **Query conditions in keys**: `'?page_id=82'`, `'index.php?p=14'`, `'?feed'`
  (any value), `'a?k=1&l=2'`. The matched condition parameters are removed from
  the redirect; the rest is kept or dropped (option `query`).
- **410 Gone** as a value (`'gewinnspiel-2019' => 410`), with the overridable
  snippet `hikkoshi/gone`.
- **Per-entry status and query**: `['to' => …, 'status' => 302, 'query' => 'drop']`;
  statuses 301, 302, 307, 308. Global options `status` and `query`.
- **External targets** only on hosts listed in `hikkoshi.allowedHosts`.
  Nothing else is ever an external target.
- **Findings for the whole list**: duplicate old paths after normalisation,
  loops (error), chains (warning, or info with `resolveChains`), self
  redirects, invalid statuses, query modes, sources and targets. Each finding
  has a level, a translated message and the key it concerns.
- **Option `resolveChains`** replaces a chain's target by the final one.
- **Command line**: `vendor/bin/hikkoshi check` (validates the list without
  Kirby; `--resolve-chains`, `--allow-host`, `--strict`, exit codes 0/1/2) and
  `vendor/bin/hikkoshi convert` (Apache `Redirect`, `RedirectPermanent`,
  `RedirectTemp`, `RedirectMatch` and `RewriteRule` with `R` or `G` flag and
  query conditions to list entries; skipped lines are reported with a reason,
  nothing is written; control characters never reach the terminal or the
  comment header of the printed file).
- **Site test helper** `$site->hikkoshiCheck()`: boots each entry through the
  real router and reports missing or draft target pages, wrong statuses and
  locations, and redirected targets.
- **Site and page methods** `hikkoshiRedirects()`, `hikkoshiResolve()`,
  `hikkoshiSources()`, and the global function `hikkoshi_option()`.
- **Options** `active`, `redirects`, `file`, `status`, `query`, `allowedHosts`,
  `resolveChains`, `lowercase`, `transliterate`, `suffixes`, `indexFiles`,
  `language`.
- **English and German** messages, log lines, CLI output and the 410 page
  (`hikkoshi.language`, else the site language, else `locale`, else English).
- **Documentation**: README, this changelog, and privacy notes in
  [privacy text] `PRIVACY.md` (English) and `DATENSCHUTZ.md` (German text block
  for a site's privacy policy, with notes for the operator).

### Changed (breaking)

- **Names are English.** The class `Weiterleitungen\Liste` is replaced by
  `Hikkoshi\Validator` (`::assert()` for the old behaviour, `::validate()` for
  the list of findings). The exception `Hikkoshi\ConfigException` extends
  `LogicException`, as before. The option prefix is `hikkoshi.`.
- **Activation.** The copy was on whenever the legacy list file
  `site/config/weiterleitungen.php` existed. Hikkoshi is off until
  `'hikkoshi.active' => true` is set.
- **One catch-all route** (GET and HEAD) instead of one route per entry, for
  normalised matching. POST and other methods are not touched.
- **Chains are warnings**, not errors: a chain used to make every request fail
  with HTTP 500. The site now works, the warning is logged, and
  `hikkoshiCheck()` and `check --strict` report it. Loops and duplicates still
  fail every request.
- **Normalised keys are accepted.** `'Menue'`, `'/menue/'`, `'über-uns'`,
  `'%c3%bcber-uns'` were rejected; they are now stored as `menue`, `ueber-uns`.
  A key that normalises to an existing key is a duplicate error.
- **Uppercase requests redirect.** `/Menue` answered 404 before; it is a 301
  now (with `lowercase` on).
- **The list file name** is `site/config/hikkoshi.php`. The pre-1.0 file
  `site/config/weiterleitungen.php` is still read when it exists and no
  `hikkoshi.php` does; its content is unchanged.
- **Matched query parameters are removed** from the redirect, and the rest
  follows `query` (default `keep`, as before).
- **Targets stay strict**: lowercase page ids without a leading slash, as
  before. A target that is a URL is new (see Added); everything else is
  rejected as before.

### Removed

- `Weiterleitungen\Liste` and the plugin id `weiterleitungen/kern`. There are
  no aliases: a site-local plugin folder with the pre-1.0 code must be deleted
  when the package is installed, because both would register routes for the
  same paths.
- The German names of classes and options. Options are `hikkoshi.*` only.

# Changelog

All notable changes to this project are documented here. Versions follow
[Semantic Versioning](https://semver.org) with these project rules:

- **A change to the matching rules or to the config contract** (the key and
  value forms of the redirect list, the normalisation of keys and request
  paths, the order of the passes) **is a major version**: a site would
  redirect other addresses after a routine update.
- **A change to the privacy text** (`DATENSCHUTZ.md` / `PRIVACY.md`) is marked
  **[privacy text]**. Every site using the plugin must then review its privacy
  policy.

## [1.0.0] — 2026-10-11

First release as a Composer package. Before 1.0.0 the redirects lived as a copy
in each site: `site/plugins/weiterleitungen` (plugin id `weiterleitungen/kern`,
class `Weiterleitungen\Liste`), byte-identical in kirby-starter and the sites
built from it, and the WordPress redirects of radladen as Apache rules. The
changes below are measured against that copy.

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
- **Activation.** The copy was on whenever `site/config/weiterleitungen.php`
  existed. Hikkoshi is off until `'hikkoshi.active' => true` is set.
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
  no aliases: the copy in `site/plugins/weiterleitungen/` must be deleted when
  the package is installed, because both would register routes for the same
  paths.
- The German names of classes and options. Options are `hikkoshi.*` only.

<?php
/**
 * Template for site/config/hikkoshi.php (checked in, nothing secret). Read
 * by sayamaapps/kirby-hikkoshi when 'sayamaapps.hikkoshi.active' => true.
 *
 * Old public paths → Kirby page ids, each without domain. `home` is the home
 * page. The pre-1.0 file site/config/weiterleitungen.php is read unchanged
 * when this file does not exist.
 *
 * - Old paths are matched tolerantly: case, a trailing slash, percent-encoding,
 *   umlauts (ü = ue) and the suffixes .html/.htm/.php or a trailing index file
 *   do not matter ('impressum' also catches /Impressum.html).
 * - A key may carry a query condition: '?page_id=82' (WordPress without
 *   permalinks), 'index.php?p=14', '?feed' (any value).
 * - Targets are page ids. A foreign URL needs 'sayamaapps.hikkoshi.allowedHosts'.
 * - Chains (a → b, b → c) are warned about; 'sayamaapps.hikkoshi.resolveChains' => true
 *   resolves them. Loops and duplicates are errors and stop the site.
 * - Redirects only while the target is published; a draft as target gives
 *   the normal treatment of the old path (usually 404).
 *
 * Check it: vendor/bin/hikkoshi check
 */
return [
	// Jimdo: pages without suffix, subpages with a path
	'ueber-uns' => 'team',
	'angebot/kurs-1' => 'produkte',
	// Jimdo/WordPress exports with a suffix
	'impressum.html' => 'impressum',
	'index.html' => 'home',
	// Old downloads whose content is a page now
	'downloads/preisliste-2024.pdf' => 'produkte',
	// WordPress with plain permalinks (query-string addresses)
	'?page_id=82' => 'impressum',
	'?pagename=contact' => 'kontakt',
	'?feed' => 'home',
	// Removed for good: 410 instead of a redirect
	'gewinnspiel-2019' => 410,
	// Temporary, with the query dropped
	'aktion' => ['to' => 'produkte', 'status' => 302, 'query' => 'drop'],
];

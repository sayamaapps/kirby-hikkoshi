<?php

use Kirby\Cms\App;
use Kirby\Filesystem\F;

F::loadClasses(require __DIR__ . '/autoload.php', __DIR__);
require_once __DIR__ . '/legacy.php';

use Sayamaapps\Hikkoshi\I18n;
use Sayamaapps\Hikkoshi\Options;
use Sayamaapps\Hikkoshi\Redirects;
use Sayamaapps\Hikkoshi\SiteCheck;

/**
 * Hikkoshi (引っ越し — moving house): redirects from the old addresses of a
 * site that moved to Kirby (Jimdo, Wix, WordPress). Off by default: without
 * `'hikkoshi.active' => true` no route is registered and the site and page
 * methods return [].
 *
 * This file only registers the plugin. All logic lives in src/Redirects.php
 * (Kirby glue) and the Kirby-free classes Validator, Resolver, Path.
 *
 * Defaults are passed as fallback values directly at each option call, NOT
 * in an 'options' block (Kirby would file them under sayamaapps.hikkoshi.*
 * otherwise).
 *
 * A plugin folder from the pre-1.0 release (plugin id weiterleitungen/kern,
 * in site/plugins/) must be REMOVED when the package is installed: both
 * register routes for the same old paths, and the old one would answer first.
 * Hikkoshi reads the legacy list file site/config/weiterleitungen.php
 * unchanged, so only the plugin folder goes (SPEC.md §7).
 */

/** Reads `hikkoshi.<key>`. Also meant for site snippets with their own markup. */
function hikkoshi_option(string $key, mixed $default = null): mixed
{
	return Options::get(App::instance(), $key, $default);
}

$hikkoshiTranslations = [];
foreach (I18n::LANGUAGES as $hikkoshiLanguage) {
	foreach (I18n::table($hikkoshiLanguage) as $hikkoshiKey => $hikkoshiText) {
		$hikkoshiTranslations[$hikkoshiLanguage]['hikkoshi.' . $hikkoshiKey] = $hikkoshiText;
	}
}
unset($hikkoshiLanguage, $hikkoshiKey, $hikkoshiText);

App::plugin('sayamaapps/hikkoshi', [
	// All strings, also usable as t('hikkoshi.<key>').
	'translations' => $hikkoshiTranslations,

	/**
	 * The body of a 410 response. A site with its own markup creates
	 * site/snippets/hikkoshi/gone.php — Kirby looks there first. It receives
	 * $title and $text (SPEC.md §3.5).
	 */
	'snippets' => [
		'hikkoshi/gone' => __DIR__ . '/snippets/hikkoshi/gone.php',
	],

	/**
	 * One catch-all GET|HEAD route (SPEC.md §3.4) that answers for old paths
	 * and calls $this->next() for everything else. [] while Hikkoshi is off.
	 * A list with errors throws Sayamaapps\Hikkoshi\ConfigException here, so EVERY
	 * request fails (500) instead of one entry being skipped silently — the
	 * pre-1.0 behaviour, kept on purpose (SPEC.md §10).
	 */
	'routes' => function (App $kirby): array {
		return Redirects::routes($kirby);
	},

	'siteMethods' => [
		/** The validated, normalised entries (SPEC.md §3.2.1); [] when off. */
		'hikkoshiRedirects' => function (): array {
			return Redirects::entries($this->kirby());
		},

		/**
		 * The decision for a path and query as the route would take it
		 * (SPEC.md §3.3.2), or null. Meant for a site's own tests and
		 * debugging; [] / null when off.
		 */
		'hikkoshiResolve' => function (string $path, array $query = []): array|null {
			return Redirects::resolve($this->kirby(), $path, $query);
		},

		/**
		 * Site test helper (SPEC.md §3.6): every entry resolves in at most one
		 * hop to a 200 page. Returns the list of findings, [] when all is well.
		 */
		'hikkoshiCheck' => function (bool $render = true): array {
			return SiteCheck::run($this->kirby(), $render);
		},
	],

	'pageMethods' => [
		/** Old paths (config keys) that redirect to this page: list<string>. [] when off. */
		'hikkoshiSources' => function (): array {
			return Redirects::sourcesFor($this->kirby(), $this);
		},
	],
]);

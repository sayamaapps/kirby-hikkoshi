<?php

use Kirby\Filesystem\F;

/**
 * Hikkoshi (引っ越し — moving house): redirects from the old addresses of a
 * site that moved to Kirby (Jimdo, Wix, WordPress). Off by default: without
 * `'hikkoshi.active' => true` no route is registered and the site and page
 * methods return [].
 *
 * This file only registers the plugin: the class map, the legacy aliases and
 * the definitions in config/. All logic lives in src/Redirects.php (Kirby glue)
 * and the Kirby-free classes Validator, Resolver, Path.
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
F::loadClasses(require __DIR__ . '/autoload.php', __DIR__);
require_once __DIR__ . '/legacy.php';

Kirby::plugin(
	name: 'sayamaapps/hikkoshi',
	extends: [
		'options'      => require __DIR__ . '/config/options.php',
		'translations' => require __DIR__ . '/config/translations.php',
		'snippets'     => require __DIR__ . '/config/snippets.php',
		'routes'       => require __DIR__ . '/config/routes.php',
		'siteMethods'  => require __DIR__ . '/config/site-methods.php',
		'pageMethods'  => require __DIR__ . '/config/page-methods.php',
	],
	root: __DIR__
);

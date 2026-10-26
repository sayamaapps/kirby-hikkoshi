<?php

/**
 * Names of the 1.x API, kept until 2.0 (README, Deprecations).
 *
 * Loaded once by index.php, after the class map is registered. Class aliases
 * only; no logic here. Each alias is guarded, so a file that declares an alias
 * itself (see src/Validator.php) can be loaded before this file.
 */

$hikkoshiLegacyClasses = [
	'CheckCommand',
	'ConfigException',
	'ConvertCommand',
	'Htaccess',
	'I18n',
	'Options',
	'Path',
	'Redirects',
	'Resolver',
	'SiteCheck',
	'Validator',
];

foreach ($hikkoshiLegacyClasses as $hikkoshiLegacyClass) {
	// Loads the class first: some files declare their own alias when they load (see
	// src/Validator.php), and a second alias of the same name would be an error.
	class_exists('Sayamaapps\\Hikkoshi\\' . $hikkoshiLegacyClass);
	if (class_exists('Hikkoshi\\' . $hikkoshiLegacyClass, false) === false) {
		class_alias('Sayamaapps\\Hikkoshi\\' . $hikkoshiLegacyClass, 'Hikkoshi\\' . $hikkoshiLegacyClass);
	}
}
unset($hikkoshiLegacyClasses, $hikkoshiLegacyClass);

if (Kirby\Cms\Helpers::hasOverride('hikkoshi_option') === false) {
	/** Reads `hikkoshi.<key>`. Deprecated: use Sayamaapps\Hikkoshi\Options::get(). */
	function hikkoshi_option(string $key, mixed $default = null): mixed
	{
		Kirby\Cms\Helpers::deprecated(
			'hikkoshi_option() is deprecated, use Sayamaapps\Hikkoshi\Options::get()',
			'hikkoshi-function'
		);

		return Sayamaapps\Hikkoshi\Options::get(Kirby\Cms\App::instance(), $key, $default);
	}
}

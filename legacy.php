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
	if (class_exists('Hikkoshi\\' . $hikkoshiLegacyClass, false) === false) {
		class_alias('Sayamaapps\\Hikkoshi\\' . $hikkoshiLegacyClass, 'Hikkoshi\\' . $hikkoshiLegacyClass);
	}
}
unset($hikkoshiLegacyClasses, $hikkoshiLegacyClass);

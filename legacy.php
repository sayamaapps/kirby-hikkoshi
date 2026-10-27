<?php

/**
 * Names of the 1.x API, kept until 2.0 (README, Deprecations).
 *
 * Loaded once by index.php. Nothing here raises a deprecation at runtime: the
 * deprecations are in the README and in the docblocks (@deprecated).
 *
 * The `Hikkoshi\*` classes are aliased lazily: the first time a site uses such a
 * name, the class of the same name in Sayamaapps\Hikkoshi\ is loaded and aliased.
 * Loading this file therefore loads no class. A file that declares its own alias
 * (every src file does) is not aliased twice.
 */

spl_autoload_register(static function (string $class): void {
	if (str_starts_with($class, 'Hikkoshi\\') === false) {
		return;
	}

	// Loading the new class runs its own alias declaration, when it has one.
	$name = 'Sayamaapps\\' . $class;
	if (class_exists($name) && class_exists($class, false) === false) {
		class_alias($name, $class);
	}
});

if (Kirby\Cms\Helpers::hasOverride('hikkoshi_option') === false) {
	/**
	 * Reads `hikkoshi.<key>`.
	 *
	 * @deprecated 2.0 Use Sayamaapps\Hikkoshi\Options::get($kirby, $key, $default).
	 */
	function hikkoshi_option(string $key, mixed $default = null): mixed
	{
		return Sayamaapps\Hikkoshi\Options::get(Kirby\Cms\App::instance(), $key, $default);
	}
}

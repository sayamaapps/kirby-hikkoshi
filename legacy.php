<?php

/**
 * Names of the 1.x API, kept until 2.0 (README, Deprecations).
 *
 * Loaded once by index.php. Nothing here raises a deprecation at runtime: the
 * deprecations are in the README and in the docblocks (@deprecated).
 *
 * The `Hikkoshi\*` names are declared eagerly. instanceof and catch do not
 * autoload, so a name that is not declared yet would not match. Each class file
 * declares its own alias when it loads (src/*.php), so each line only loads its
 * class: a class_alias() here would run after that declaration and warn.
 */

class_exists(\Sayamaapps\Hikkoshi\CheckCommand::class);
class_exists(\Sayamaapps\Hikkoshi\ConfigException::class); // declares Hikkoshi\ConfigException itself
class_exists(\Sayamaapps\Hikkoshi\ConvertCommand::class);
class_exists(\Sayamaapps\Hikkoshi\Htaccess::class);
class_exists(\Sayamaapps\Hikkoshi\I18n::class);
class_exists(\Sayamaapps\Hikkoshi\Options::class);
class_exists(\Sayamaapps\Hikkoshi\Path::class);
class_exists(\Sayamaapps\Hikkoshi\Redirects::class);
class_exists(\Sayamaapps\Hikkoshi\Resolver::class);
class_exists(\Sayamaapps\Hikkoshi\SiteCheck::class);
class_exists(\Sayamaapps\Hikkoshi\Validator::class);

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

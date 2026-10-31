<?php

namespace Sayamaapps\Hikkoshi;

use InvalidArgumentException;
use Kirby\Cms\App;
use Throwable;

/**
 * Reads the plugin's options (docs/SPEC.md §4): `sayamaapps.hikkoshi.<key>`,
 * falling back to the 1.x name `hikkoshi.<key>`.
 *
 * `null` counts as "not set", like Kirby's own option(), so
 * `'sayamaapps.hikkoshi.status' => null` falls back to the default.
 *
 * The flat form (`'sayamaapps.hikkoshi.active' => true`) is the one to use. The
 * nested form (`'hikkoshi' => ['active' => true]`) works for the 1.x prefix only:
 * Kirby resolves `sayamaapps.hikkoshi.*` through the plugin's own options block
 * first, which shadows a nested `sayamaapps` array.
 *
 * Defaults live in DEFAULTS and are given at the call sites, never in an
 * `options` block of the plugin (Kirby would file them under the plugin name).
 * The CLI (no Kirby) reads DEFAULTS directly; the file loads without Kirby
 * because App is only a type in signatures.
 *
 * @package   Kirby Hikkoshi
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-hikkoshi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class Options
{
	/**
	 * The settings the Kirby-free classes receive (docs/SPEC.md §4), with their
	 * defaults. Validator and Resolver take this array (or a part of it) and
	 * fill missing keys from here.
	 */
	public const DEFAULTS = [
		'status'        => 301,
		'query'         => 'keep',
		'allowedHosts'  => [],
		'resolveChains' => false,
		'lowercase'     => true,
		'transliterate' => true,
		'suffixes'      => ['html', 'htm', 'php'],
		'indexFiles'    => ['index.php', 'index.html', 'index.htm'],
	];

	/** File names tried in site/config/ when the `redirects` and `file` options are not set, in this order. */
	public const FILES = ['hikkoshi.php', 'weiterleitungen.php'];

	/** Option prefix since 1.3: `sayamaapps.hikkoshi.<key>`. */
	public const OPTION_PREFIX = 'sayamaapps.hikkoshi.';

	/**
	 * Option prefix of 1.x (`hikkoshi.<key>`). Still read as a fallback until 2.0.
	 *
	 * @deprecated 1.3 use OPTION_PREFIX; removal planned for 2.0
	 */
	public const PREFIX = 'hikkoshi.';

	/** @var array<string, true> messages already written to the error log in this process */
	private static array $logged = [];

	/** Master switch: only a real `true` switches Hikkoshi on. */
	public static function active(App $kirby): bool
	{
		return self::get($kirby, 'active', false) === true;
	}

	/** Sets I18n's language for this request from language(). Called before any text is produced. */
	public static function applyLanguage(App $kirby): void
	{
		I18n::setLanguage(self::language($kirby));
	}

	/**
	 * A boolean option. Only a real true or false counts: the string 'false'
	 * (typical for env-driven config) is not a switch, so it is reported and
	 * the default is used.
	 */
	public static function flag(App $kirby, string $key, bool $default): bool
	{
		$value = self::get($kirby, $key, $default);
		if (is_bool($value)) {
			return $value;
		}
		self::invalid($kirby, 'Hikkoshi: ' . self::setKey($kirby, $key) . ' must be true or false — using ' . var_export($default, true) . '.');
		return $default;
	}

	/**
	 * Reads an option under the sayamaapps.hikkoshi.* spelling, then under the
	 * 1.x hikkoshi.* spelling, which is read silently (README, Deprecated).
	 * Returns $default when neither is set.
	 */
	public static function get(App $kirby, string $key, mixed $default = null): mixed
	{
		$value = $kirby->option(self::OPTION_PREFIX . $key);
		if ($value !== null) {
			return $value;
		}

		return $kirby->option(self::PREFIX . $key) ?? $default;
	}

	/**
	 * The `allowedHosts` option: hosts that may be redirect targets, lowercased.
	 * A single string is accepted as a one-element list.
	 *
	 * @return list<string>
	 */
	public static function hosts(App $kirby): array
	{
		$option = self::get($kirby, 'allowedHosts', []);
		$list = is_array($option) ? $option : [$option];
		$hosts = [];
		foreach ($list as $host) {
			if (is_string($host) && trim($host) !== '') {
				$hosts[] = strtolower(trim($host));
			}
		}
		return array_values(array_unique($hosts));
	}

	/**
	 * A broken option: with Kirby's `debug` option on it throws, so the
	 * developer sees it on the page; otherwise it is logged once per process
	 * and the caller uses the default.
	 *
	 * The exception is PHP's \InvalidArgumentException, as in 1.x: Kirby's own
	 * exception classes do not extend it, and a site's catch would miss them.
	 */
	public static function invalid(App $kirby, string $message): void
	{
		if ($kirby->option('debug') === true) {
			throw new InvalidArgumentException($message);
		}
		self::log($message);
	}

	/**
	 * Language of the plugin's text: the `language` option, otherwise the current
	 * language of a multi-language site, otherwise its default language,
	 * otherwise the first two letters of Kirby's `locale` option, otherwise
	 * English. Only languages Hikkoshi ships (I18n::LANGUAGES) are returned.
	 */
	public static function language(App $kirby): string
	{
		$candidates = [self::get($kirby, 'language')];
		try {
			$candidates[] = $kirby->language()?->code();
			$candidates[] = $kirby->defaultLanguage()?->code();
		} catch (Throwable) {
			// No languages configured.
		}
		$locale = $kirby->option('locale');
		if (is_array($locale)) {
			$locale = $locale[LC_ALL] ?? reset($locale);
		}
		$candidates[] = is_string($locale) ? substr($locale, 0, 2) : null;

		foreach ($candidates as $candidate) {
			if (is_string($candidate) && in_array(strtolower($candidate), I18n::LANGUAGES, true)) {
				return strtolower($candidate);
			}
		}
		return 'en';
	}

	/**
	 * The list settings of a settings array in their normal form, for the
	 * Kirby-free entry points (Validator, Resolver, the CLI): a single string
	 * becomes a one-element list, entries are trimmed and lowercased, empty
	 * ones dropped. settings() already returns this form; this makes a
	 * hand-written array (README → Test helper for sites) behave the same.
	 */
	public static function lists(array $settings): array
	{
		foreach (['allowedHosts' => " \t\n\r\0\x0B", 'suffixes' => ' ./', 'indexFiles' => ' ./'] as $key => $trim) {
			if (!array_key_exists($key, $settings)) {
				continue;
			}
			$list = [];
			foreach (is_array($settings[$key]) ? $settings[$key] : [$settings[$key]] as $value) {
				if (is_string($value) && trim($value, $trim) !== '') {
					$list[] = strtolower(trim($value, $trim));
				}
			}
			$settings[$key] = array_values(array_unique($list));
		}
		return $settings;
	}

	/** Writes a message to the error log once per process. */
	public static function log(string $message): void
	{
		if (isset(self::$logged[$message])) {
			return;
		}
		self::$logged[$message] = true;
		error_log($message);
	}

	/**
	 * The option name as the site spelled it, for messages: the 1.x hikkoshi.*
	 * name only when the site sets no sayamaapps.hikkoshi.* value for it.
	 */
	public static function setKey(App $kirby, string $key): string
	{
		if ($kirby->option(self::OPTION_PREFIX . $key) === null && $kirby->option(self::PREFIX . $key) !== null) {
			return self::PREFIX . $key;
		}
		return self::OPTION_PREFIX . $key;
	}

	/**
	 * Every setting the Kirby-free classes need, normalised, with the
	 * defaults of docs/SPEC.md §4. A broken value is reported (invalid()) and the
	 * default is used.
	 *
	 * @return array{
	 *   status: int, query: string, allowedHosts: list<string>, resolveChains: bool,
	 *   lowercase: bool, transliterate: bool, suffixes: list<string>, indexFiles: list<string>
	 * }
	 */
	public static function settings(App $kirby): array
	{
		$status = self::get($kirby, 'status', self::DEFAULTS['status']);
		if (!is_int($status) || !in_array($status, [301, 302, 307, 308], true)) {
			self::invalid($kirby, 'Hikkoshi: ' . self::setKey($kirby, 'status') . ' must be 301, 302, 307 or 308 — using 301.');
			$status = self::DEFAULTS['status'];
		}
		$query = self::get($kirby, 'query', self::DEFAULTS['query']);
		if (!in_array($query, ['keep', 'drop'], true)) {
			self::invalid($kirby, 'Hikkoshi: ' . self::setKey($kirby, 'query') . ' must be "keep" or "drop" — using "keep".');
			$query = self::DEFAULTS['query'];
		}
		return [
			'status'        => $status,
			'query'         => $query,
			'allowedHosts'  => self::hosts($kirby),
			'resolveChains' => self::flag($kirby, 'resolveChains', self::DEFAULTS['resolveChains']),
			'lowercase'     => self::flag($kirby, 'lowercase', self::DEFAULTS['lowercase']),
			'transliterate' => self::flag($kirby, 'transliterate', self::DEFAULTS['transliterate']),
			'suffixes'      => self::strings($kirby, 'suffixes', self::DEFAULTS['suffixes']),
			'indexFiles'    => self::strings($kirby, 'indexFiles', self::DEFAULTS['indexFiles']),
		];
	}

	/**
	 * A list option of non-empty strings, lowercased (`suffixes`,
	 * `indexFiles`). A single string is accepted; `[]` switches the feature
	 * off; anything else is reported and the default is used.
	 *
	 * @param list<string> $default
	 * @return list<string>
	 */
	public static function strings(App $kirby, string $key, array $default): array
	{
		$option = self::get($kirby, $key, $default);
		if (is_string($option)) {
			$option = [$option];
		}
		if (!is_array($option)) {
			self::invalid($kirby, 'Hikkoshi: ' . self::setKey($kirby, $key) . ' must be a list of strings — using the default.');
			return $default;
		}
		$list = [];
		foreach ($option as $value) {
			if (is_string($value) && trim($value) !== '') {
				$list[] = strtolower(trim($value, ' ./'));
			}
		}
		return array_values(array_unique($list));
	}
}

// 1.x name for sites that load this file directly, without the plugin (legacy.php).
if (class_exists('Hikkoshi\\Options', false) === false) {
	class_alias(Options::class, 'Hikkoshi\\Options');
}

<?php

namespace Sayamaapps\Hikkoshi;

/**
 * Path normalisation and the character rules for old paths and targets,
 * Kirby-free (SPEC.md §3.1).
 *
 * Both sides of a match go through the same normalize(): the config keys at
 * load time (Validator) and the request path at run time (Resolver). That
 * is what makes `/Über-Uns/`, `/%C3%BCber-uns` and `ueber-uns` one path.
 *
 * A path here never has a leading or trailing slash; the home page is ''.
 */
final class Path
{
	/**
	 * A Kirby page id: lowercase segments without dots (`home`,
	 * `leistungen/beratung`). Lowercase only, as the pre-1.0 release demanded:
	 * Kirby slugs are lowercase, and `Produkte` would never be found.
	 */
	private const ID_SEGMENT = '[a-z0-9_-]+';

	/**
	 * One path segment: letters, digits, `_`, `-`, with at most one dot
	 * (`impressum.html`, `karte-1.pdf`). No `..`, no dot files, no
	 * pattern characters such as `(:all)`, no spaces, no percent signs.
	 */
	private const SEGMENT = '[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)?';

	/** German umlauts and ß as Kirby slugs spell them. */
	public const TRANSLITERATION = [
		'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
		'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
	];

	/**
	 * Is the target an absolute URL on another host? Returns the host
	 * (lowercased, as written, port included) or null for a page id or
	 * anything else (`javascript:` and `mailto:` are not external, they are
	 * invalid — isValidTarget() rejects them).
	 *
	 *   externalHost('https://example.org/x') → 'example.org'
	 *   externalHost('//Example.org')         → 'example.org'
	 *   externalHost('produkte')              → null
	 */
	public static function externalHost(string $target): string|null
	{
		if (preg_match('~^(?:https?:)?//([^/?#]+)~i', trim($target), $m) === 1) {
			return strtolower($m[1]);
		}
		return null;
	}

	/**
	 * May this (already normalised) path be an old path? '' is not valid on
	 * its own — the home page as a source needs a query condition, which the
	 * Validator checks separately.
	 */
	public static function isValidSource(string $path): bool
	{
		return preg_match('~^' . self::SEGMENT . '(?:/' . self::SEGMENT . ')*$~D', $path) === 1;
	}

	/** A Kirby page id as a target: `home`, `produkte`, `leistungen/beratung`. */
	public static function isValidTarget(string $target): bool
	{
		return preg_match('~^' . self::ID_SEGMENT . '(?:/' . self::ID_SEGMENT . ')*$~D', $target) === 1;
	}

	/**
	 * Normalises a path for matching: percent-decoding (also a double
	 * encoding), Unicode NFC when intl is available, `//` collapsed, slashes
	 * trimmed at both ends, optionally lowercased and transliterated
	 * (ü → ue, ß → ss). Whitespace at the ends is removed, inner whitespace
	 * is kept (it fails isValidSource() later).
	 *
	 *   normalize('/Über-Uns/')          → 'ueber-uns'
	 *   normalize('%C3%BCber-uns')       → 'ueber-uns'
	 *   normalize('a//b/')               → 'a/b'
	 *   normalize('')                    → ''
	 *   normalize('Menue', false)        → 'Menue'
	 */
	public static function normalize(string $path, bool $lowercase = true, bool $transliterate = true): string
	{
		$path = trim($path);
		for ($i = 0; $i < 2 && preg_match('/%[0-9a-fA-F]{2}/', $path) === 1; $i++) {
			$path = rawurldecode($path);
		}
		if (class_exists(\Normalizer::class) && !\Normalizer::isNormalized($path, \Normalizer::FORM_C)) {
			$path = \Normalizer::normalize($path, \Normalizer::FORM_C) ?: $path;
		}
		$path = (string)preg_replace('~/{2,}~', '/', $path);
		$path = trim($path, '/');
		if ($lowercase) {
			$path = mb_strtolower($path, 'UTF-8');
		}
		if ($transliterate) {
			$path = strtr($path, self::TRANSLITERATION);
		}
		return $path;
	}

	/** A query array as the part after `?`, RFC 3986 encoded; '' for []. */
	public static function query(array $query): string
	{
		return $query === [] ? '' : http_build_query($query, '', '&', PHP_QUERY_RFC3986);
	}

	/**
	 * Splits a config key into its path part and its query conditions.
	 * `k=v` means "k equals v", a bare `k` means "k is present with any value".
	 * Keys and values are percent-decoded; the path part is NOT normalised
	 * here (the caller does that, so it can pass its settings).
	 *
	 *   split('?page_id=82')         → ['path' => '',          'conditions' => ['page_id' => '82']]
	 *   split('index.php?p=1&feed')  → ['path' => 'index.php', 'conditions' => ['p' => '1', 'feed' => null]]
	 *   split('ueber-uns')           → ['path' => 'ueber-uns', 'conditions' => []]
	 *
	 * @return array{path: string, conditions: array<string, ?string>}
	 */
	public static function split(string $key): array
	{
		$pos = strpos($key, '?');
		if ($pos === false) {
			return ['path' => $key, 'conditions' => []];
		}
		$conditions = [];
		foreach (explode('&', substr($key, $pos + 1)) as $part) {
			if ($part === '') {
				continue;
			}
			$eq = strpos($part, '=');
			if ($eq === false) {
				$conditions[rawurldecode($part)] = null;
			} else {
				$conditions[rawurldecode(substr($part, 0, $eq))] = rawurldecode(substr($part, $eq + 1));
			}
		}
		return ['path' => substr($key, 0, $pos), 'conditions' => $conditions];
	}

	/**
	 * The fallback form of a request path for the second lookup pass
	 * (SPEC.md §3.3): a trailing index file is removed, otherwise a known
	 * suffix of the last segment. Null when neither applies.
	 *
	 *   stripSuffix('impressum.html', ['html','htm','php'], ['index.php','index.html','index.htm']) → 'impressum'
	 *   stripSuffix('blog/index.php', …)   → 'blog'
	 *   stripSuffix('index.html', …)       → ''
	 *   stripSuffix('impressum', …)        → null
	 *   stripSuffix('.html', …)            → null  (nothing left)
	 *
	 * @param list<string> $suffixes without the dot
	 * @param list<string> $indexFiles complete file names
	 */
	public static function stripSuffix(string $path, array $suffixes, array $indexFiles): string|null
	{
		if ($path === '') {
			return null;
		}
		$segments = explode('/', $path);
		$last = (string)end($segments);
		if (in_array($last, $indexFiles, true)) {
			array_pop($segments);
			return implode('/', $segments);
		}
		foreach ($suffixes as $suffix) {
			$suffix = '.' . ltrim((string)$suffix, '.');
			if ($suffix !== '.' && strlen($last) > strlen($suffix) && str_ends_with($last, $suffix)) {
				$segments[count($segments) - 1] = substr($last, 0, -strlen($suffix));
				return implode('/', $segments);
			}
		}
		return null;
	}
}

// 1.x name for sites that load this file directly, without the plugin (legacy.php).
if (class_exists('Hikkoshi\\Path', false) === false) {
	class_alias(Path::class, 'Hikkoshi\\Path');
}

<?php

namespace Sayamaapps\Hikkoshi;

/**
 * Validates and normalises a redirect list, Kirby-free (SPEC.md §3.2).
 *
 * Input is what site/config/hikkoshi.php (or the pre-1.0 file
 * site/config/weiterleitungen.php) returns: an array `old path => target`.
 * Output is the list of normalised entries plus the findings (errors,
 * warnings, infos). Entries with an error are dropped from the list; the
 * caller decides whether errors are fatal (Validator::assert() throws
 * ConfigException, as the pre-1.0 release did on every request).
 *
 * Accepted value forms (SPEC.md §6):
 *   'old' => 'new'                                    page id, global status and query mode
 *   'old' => 'https://other.example/x'                only when the host is in allowedHosts
 *   'old' => 410                                      gone
 *   'old' => ['to' => 'new', 'status' => 302, 'query' => 'drop']
 *   'old' => ['status' => 410]
 * Accepted key forms: 'path', 'path?k=v', '?page_id=82', 'index.php?p=1&feed'.
 *
 * @package   Kirby Hikkoshi
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-hikkoshi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
final class Validator
{
	/** Keys the array form of an entry may have. */
	private const ENTRY_KEYS = ['to', 'status', 'query'];
	public const ERROR = 'error';
	public const INFO = 'info';
	public const QUERY_MODES = ['keep', 'drop'];

	/** Status codes an entry may carry. 410 means "gone", no target. */
	public const STATUSES = [301, 302, 307, 308, 410];
	public const WARNING = 'warning';

	/**
	 * validate(), but throws ConfigException when any finding is an error.
	 * Warnings and infos are left to the caller (ConfigException::findings()
	 * is not reached then; use validate() to see them).
	 *
	 * @return list<array> the entries
	 */
	public static function assert(mixed $config, array $settings = []): array
	{
		$result = self::validate($config, $settings);
		if (self::hasErrors($result['findings'])) {
			throw new ConfigException($result['findings']);
		}
		return $result['entries'];
	}

	/**
	 * Loops (error) and chains (warning, or info + resolved with
	 * resolveChains). Only entries WITHOUT conditions can be the next hop: a
	 * redirect to a page id carries no query condition of its own, so a
	 * conditional entry is not counted (with query `keep` it can still catch
	 * a forwarded query; README → Validation names that limit). The target
	 * `home` is never captured either: its redirect lands on `/`, which the
	 * source `home` (`/home`) does not match (SPEC.md §3.2.2).
	 *
	 * @param list<array> $entries modified in place when resolving
	 */
	private static function chains(array &$entries, array $settings, array &$findings): void
	{
		$plain = [];   // source => index, entries without conditions
		foreach ($entries as $index => $entry) {
			if ($entry['conditions'] === []) {
				$plain[$entry['source']] = $index;
			}
		}

		$inLoop = [];   // sources already reported as part of a loop
		$resolved = [];
		foreach ($entries as $index => $entry) {
			if ($entry['external'] || $entry['target'] === null) {
				continue;
			}
			$start = $entry['conditions'] === [] ? $entry['source'] : $entry['key'];
			$chain = [$start];
			$visited = $entry['conditions'] === [] ? [$entry['source'] => true] : [];
			$current = $entry['target'];
			$final = $entry;
			$loop = false;
			while ($current !== 'home' && isset($plain[$current])) {
				$chain[] = $current;
				if (isset($visited[$current])) {
					$loop = true;
					break;
				}
				$visited[$current] = true;
				$final = $entries[$plain[$current]];
				if ($final['external'] || $final['target'] === null) {
					break;
				}
				$current = $final['target'];
			}
			if ($loop) {
				if (isset($inLoop[$entry['source']])) {
					continue;
				}
				foreach ($chain as $member) {
					$inLoop[$member] = true;
				}
				$findings[] = self::finding(self::ERROR, 'loop', ['chain' => implode(' → ', $chain)], $entry['key']);
				continue;
			}
			if (count($chain) < 2) {
				continue;   // the target is not a source: no chain
			}
			// The chain ends at the final target (a page id, a URL or 410).
			$chain[] = $final['target'] ?? '410';
			$text = implode(' → ', $chain);
			if ($settings['resolveChains'] === true) {
				$resolved[$index] = $final;
				$findings[] = self::finding(self::INFO, 'chain.resolved', ['chain' => $text, 'target' => $final['target'] ?? '410'], $entry['key']);
			} else {
				$findings[] = self::finding(self::WARNING, 'chain', ['chain' => $text], $entry['key']);
			}
		}

		// Loops make the list invalid; do not resolve anything then.
		if ($inLoop !== []) {
			return;
		}
		foreach ($resolved as $index => $final) {
			$entries[$index]['target'] = $final['target'];
			$entries[$index]['external'] = $final['external'];
			if ($final['target'] === null) {
				$entries[$index]['status'] = 410;
				$entries[$index]['query'] = 'drop';
			}
		}
	}

	/**
	 * A finding. `code` is the translation key without the `finding.`
	 * prefix; `message` is translated in the current I18n language.
	 *
	 * @return array{level:string, code:string, message:string, key:?string, values:array}
	 */
	public static function finding(string $level, string $code, array $values, string|null $key): array
	{
		return [
			'level'   => $level,
			'code'    => $code,
			'message' => I18n::t('finding.' . $code, $values),
			'key'     => $key,
			'values'  => $values,
		];
	}

	/** True when at least one finding has the error level. */
	public static function hasErrors(array $findings): bool
	{
		foreach ($findings as $finding) {
			if (($finding['level'] ?? null) === self::ERROR) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The identity of a source for duplicate detection: the normalised path
	 * plus the conditions in a canonical order (`?k=v&k2`).
	 */
	public static function identity(string $source, array $conditions): string
	{
		if ($conditions === []) {
			return $source;
		}
		ksort($conditions);
		$parts = [];
		foreach ($conditions as $name => $value) {
			$parts[] = $value === null ? $name : $name . '=' . $value;
		}
		return $source . '?' . implode('&', $parts);
	}

	/** Only the findings of one level, in order. */
	public static function ofLevel(array $findings, string $level): array
	{
		return array_values(array_filter($findings, static fn (array $f) => ($f['level'] ?? null) === $level));
	}

	/**
	 * Parses the value side of an entry. Appends a finding and returns null
	 * when the value is unusable.
	 *
	 * @return ?array{target:?string, status:int, query:string, external:bool}
	 */
	private static function parseValue(mixed $value, array $settings, string $entryText, string $key, array &$findings): array|null
	{
		$status = $settings['status'];
		$query = $settings['query'];
		$target = null;

		if (is_string($value)) {
			$target = trim($value);
		} elseif (is_int($value)) {
			if ($value !== 410) {
				$findings[] = self::finding(self::ERROR, 'entry.invalid', ['entry' => $entryText], $key);
				return null;
			}
			$status = 410;
		} elseif (is_array($value)) {
			foreach (array_keys($value) as $name) {
				if (!in_array($name, self::ENTRY_KEYS, true)) {
					$findings[] = self::finding(self::ERROR, 'entry.invalid', ['entry' => $entryText], $key);
					return null;
				}
			}
			if (array_key_exists('status', $value)) {
				if (!is_int($value['status']) || !in_array($value['status'], self::STATUSES, true)) {
					$findings[] = self::finding(self::ERROR, 'status.invalid', ['status' => var_export($value['status'], true), 'entry' => $entryText], $key);
					return null;
				}
				$status = $value['status'];
			}
			if (array_key_exists('query', $value)) {
				if (!is_string($value['query']) || !in_array($value['query'], self::QUERY_MODES, true)) {
					$findings[] = self::finding(self::ERROR, 'query.invalid', ['query' => var_export($value['query'], true), 'entry' => $entryText], $key);
					return null;
				}
				$query = $value['query'];
			}
			if ($status === 410) {
				if (array_key_exists('to', $value)) {
					$findings[] = self::finding(self::ERROR, 'entry.invalid', ['entry' => $entryText], $key);
					return null;
				}
			} elseif (!array_key_exists('to', $value)) {
				$findings[] = self::finding(self::ERROR, 'target.missing', ['entry' => $entryText], $key);
				return null;
			} elseif (!is_string($value['to'])) {
				$findings[] = self::finding(self::ERROR, 'target.invalid', ['entry' => $entryText], $key);
				return null;
			} else {
				$target = trim($value['to']);
			}
		} else {
			$findings[] = self::finding(self::ERROR, 'target.invalid', ['entry' => $entryText], $key);
			return null;
		}

		if ($status === 410) {
			return ['target' => null, 'status' => 410, 'query' => 'drop', 'external' => false];
		}

		$host = Path::externalHost((string)$target);
		if ($host !== null) {
			$allowed = $settings['allowedHosts'];
			if (!in_array($host, $allowed, true) && !in_array(preg_replace('/:\d+$/', '', $host), $allowed, true)) {
				$findings[] = self::finding(self::ERROR, 'target.foreign', ['host' => $host, 'entry' => $entryText], $key);
				return null;
			}
			// Whitespace or a control character inside the URL: Kirby would
			// send a mangled Location (`x__Set-Cookie:…`) without complaint.
			if (preg_match('/[\s\x00-\x1F\x7F]/', (string)$target) === 1) {
				$findings[] = self::finding(self::ERROR, 'target.invalid', ['entry' => $entryText], $key);
				return null;
			}
			// Kirby's Uri accepts only a lowercase scheme: `HTTPS://…` would
			// throw on every request to the old path.
			$target = (string)preg_replace_callback('~^https?:~i', static fn (array $m) => strtolower($m[0]), (string)$target);
			return ['target' => $target, 'status' => $status, 'query' => $query, 'external' => true];
		}

		if (!Path::isValidTarget((string)$target)) {
			$findings[] = self::finding(self::ERROR, 'target.invalid', ['entry' => $entryText], $key);
			return null;
		}
		return ['target' => $target, 'status' => $status, 'query' => $query, 'external' => false];
	}

	/**
	 * Validates the list. Never throws (except on programmer error).
	 *
	 * @param $config the value the config file returned
	 * @param $settings Options::settings() shape; missing keys come from Options::DEFAULTS
	 * @return array{entries: list<array{key:string, source:string, conditions:array<string,?string>, target:?string, status:int, query:string, external:bool}>,
	 *               findings: list<array{level:string, code:string, message:string, key:?string, values:array}>}
	 */
	public static function validate(mixed $config, array $settings = []): array
	{
		$settings = Options::lists($settings + Options::DEFAULTS);
		/** @var list<array{level: string, code: string, message: string, key: string|null, values: array<array-key, mixed>}> $findings */
		$findings = [];

		if (!is_array($config)) {
			$findings[] = self::finding(self::ERROR, 'config.notArray', [], null);
			return ['entries' => [], 'findings' => $findings];
		}

		/** @var list<array{key: string, source: string, conditions: array<string, string|null>, target: string|null, status: int, query: string, external: bool}> $entries */
		$entries = [];
		$seen = [];   // identity => index into $entries
		foreach ($config as $key => $value) {
			$entryText = I18n::entry($key, $value);

			// PHP silently turns the key '2024' into the int 2024 — not
			// distinguishable from a forgotten key (['produkte']).
			if (is_int($key)) {
				$findings[] = self::finding(self::ERROR, 'source.numeric', ['entry' => $entryText], null);
				continue;
			}

			$split = Path::split($key);
			$source = Path::normalize($split['path'], $settings['lowercase'], $settings['transliterate']);
			$conditions = $split['conditions'];

			if ($source === '') {
				if ($conditions === []) {
					$findings[] = self::finding(self::ERROR, 'source.empty', ['entry' => $entryText], $key);
					continue;
				}
			} elseif (!Path::isValidSource($source)) {
				$findings[] = self::finding(self::ERROR, 'source.invalid', ['entry' => $entryText], $key);
				continue;
			}

			$parsed = self::parseValue($value, $settings, $entryText, $key, $findings);
			if ($parsed === null) {
				continue;
			}

			if (!$parsed['external'] && $parsed['target'] !== null && $conditions === [] && $parsed['target'] === $source) {
				$findings[] = self::finding(self::ERROR, 'target.self', ['entry' => $entryText], $key);
				continue;
			}

			$identity = self::identity($source, $conditions);
			if (isset($seen[$identity])) {
				$first = $entries[$seen[$identity]];
				$findings[] = self::finding(self::ERROR, 'source.duplicate', [
					'source' => $identity,
					'entry'  => $entryText,
					'target' => (string)($first['target'] ?? $first['status']),
				], $key);
				continue;
			}

			$seen[$identity] = count($entries);
			$entries[] = [
				'key'        => $key,
				'source'     => $source,
				'conditions' => $conditions,
				'target'     => $parsed['target'],
				'status'     => $parsed['status'],
				'query'      => $parsed['query'],
				'external'   => $parsed['external'],
			];
		}

		self::chains($entries, $settings, $findings);

		/**
		 * @var list<array{key: string, source: string, conditions: array<string, string|null>, target: string|null, status: int, query: string, external: bool}> $entries
		 * @var list<array{level: string, code: string, message: string, key: string|null, values: array<array-key, mixed>}> $findings
		 */
		return ['entries' => $entries, 'findings' => $findings];
	}
}

// 1.x name for sites that load this file directly, without the plugin (legacy.php).
if (class_exists('Hikkoshi\\Validator', false) === false) {
	class_alias(Validator::class, 'Hikkoshi\\Validator');
}

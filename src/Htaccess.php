<?php

namespace Hikkoshi;

/**
 * Converts Apache redirect rules into a Hikkoshi list, Kirby-free
 * (SPEC.md §3.7).
 *
 * Reads text and returns data; it never touches files. Handled: Redirect,
 * RedirectPermanent, RedirectTemp, RedirectMatch, RewriteCond
 * (%{QUERY_STRING} only) and RewriteRule with an R or G flag. Everything else
 * (internal rewrites, IfModule, headers) is ignored silently; only redirects
 * that cannot be converted are reported as skipped — including a RewriteRule
 * to an absolute URL without R, which Apache answers with a redirect too.
 * Prefix rules (Redirect, or a regex without its closing `$`) are converted
 * for the exact path and reported as partial.
 */
final class Htaccess
{
	/**
	 * RewriteRule flags a redirect may carry: handled (R, G, QSD) or without
	 * effect on the list. Any other flag changes what Apache does in a way
	 * the list cannot express, so the rule is skipped (`flag`).
	 */
	private const FLAGS = [
		'R', 'REDIRECT', 'G', 'GONE', 'L', 'LAST', 'END', 'NC', 'NOCASE', 'NE', 'NOESCAPE',
		'QSA', 'QSAPPEND', 'QSD', 'QSDISCARD', 'E', 'ENV', 'B', 'BNP', 'BCTLS', 'BNE',
		'DPI', 'DISCARDPATH', 'NS', 'NOSUBREQ', 'PT', 'PASSTHROUGH',
	];

	/** Characters that make a regex more than a literal path. */
	private const META = '.*+?()[]{}|\\^$';

	/** Ends the current OR-chain: Apache ORs its members, the chains are ANDed. */
	private static function closeChain(array &$state): void
	{
		if ($state['open'] === []) {
			return;
		}
		$state['chains'][] = array_merge(...$state['open']);
		$state['open'] = [];
	}

	/** @param list<array{0: int, 1: string}> $result */
	private static function collect(array &$result, int $line, string $text): void
	{
		$text = trim($text);
		if ($text === '' || $text[0] === '#') {
			return;
		}
		$result[] = [$line, $text];
	}

	/** RewriteCond: remembers an option group for the next RewriteRule. */
	private static function condition(array &$state, string $rest): void
	{
		if (preg_match('/^(\S+)\s+(\S+)(?:\s+\[([^\]]*)\])?$/', $rest, $m) !== 1 || $m[1] !== '%{QUERY_STRING}') {
			$state['poisoned'] = true;
			return;
		}
		$options = self::queryOptions($m[2]);
		if ($options === null) {
			$state['poisoned'] = true;
			return;
		}
		$state['open'][] = $options;
		if (!self::hasFlag($m[3] ?? '', 'OR')) {
			self::closeChain($state);
		}
	}

	/**
	 * `skipped`: nothing converted. `partial`: converted for the exact path,
	 * but Apache matched the paths below it too (reason `prefix`).
	 *
	 * @return array{entries: array<string, mixed>, skipped: list<array{line:int, text:string, reason:string}>, partial: list<array{line:int, text:string, reason:string}>}
	 */
	public static function convert(string $text): array
	{
		$state = [
			'entries'  => [],
			'skipped'  => [],
			'partial'  => [],
			'chains'   => [],   // closed RewriteCond OR-chains, each a list of options (cond strings)
			'open'     => [],   // the OR-chain still waiting for a cond without [OR]
			'poisoned' => false,
		];

		foreach (self::directives($text) as [$line, $body]) {
			if (preg_match('/^(\S+)\s*(.*)$/s', $body, $m) !== 1) {
				continue;
			}
			$name = strtolower($m[1]);
			$rest = trim($m[2]);

			switch ($name) {
				case 'rewritecond':
					self::condition($state, $rest);
					break;
				case 'rewriterule':
					self::rule($state, $line, $body, $rest);
					break;
				case 'redirect':
					self::redirect($state, $line, $body, $rest, false);
					break;
				case 'redirectpermanent':
					self::redirect($state, $line, $body, 'permanent ' . $rest, false);
					break;
				case 'redirecttemp':
					self::redirect($state, $line, $body, 'temp ' . $rest, false);
					break;
				case 'redirectmatch':
					self::redirect($state, $line, $body, $rest, true);
					break;
			}
		}

		return ['entries' => $state['entries'], 'skipped' => $state['skipped'], 'partial' => $state['partial']];
	}

	/**
	 * Target rules: `/` → home, `/path` → path, a trailing `?` drops the
	 * query, http(s) URLs verbatim. Null when the target cannot be converted
	 * (also `//host/path`: protocol-relative, not a path of this site).
	 *
	 * @return array{to: string, drop: bool}|null
	 */
	private static function destination(string $raw): array|null
	{
		$target = trim($raw);
		if ($target === '' || $target === '-' || str_contains($target, '$1') || str_contains($target, '%1')) {
			return null;
		}
		$drop = str_ends_with($target, '?');
		if ($drop) {
			$target = substr($target, 0, -1);
		}
		if (preg_match('~^https?://~i', $target) === 1) {
			return ['to' => $target, 'drop' => $drop];
		}
		if (!str_starts_with($target, '/') || str_starts_with($target, '//')) {
			return null;
		}
		// A query of its own replaces the request's; the Validator accepts no `?` in a page id.
		if (str_contains($target, '?')) {
			return null;
		}
		$to = trim($target, '/');
		return ['to' => $to === '' ? 'home' : $to, 'drop' => $drop];
	}

	/**
	 * Logical directives as [first line number (1-based), trimmed text].
	 * Continuation lines (ending in a backslash) are joined; blank lines and
	 * comments are dropped.
	 *
	 * @return list<array{0: int, 1: string}>
	 */
	private static function directives(string $text): array
	{
		$result = [];
		$joined = null;
		$startLine = 0;
		foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $index => $raw) {
			$line = trim($raw);
			if ($joined === null) {
				$startLine = $index + 1;
				$joined = '';
			}
			if (str_ends_with($line, '\\')) {
				$joined .= rtrim(substr($line, 0, -1)) . ' ';
				continue;
			}
			$joined .= $line;
			self::collect($result, $startLine, $joined);
			$joined = null;
		}
		if ($joined !== null) {
			self::collect($result, $startLine, $joined);
		}
		return $result;
	}

	/** The value of flag NAME (`R` or `R=301`): '' for a bare flag, null when absent. */
	private static function flagValue(string $flags, string $name): string|null
	{
		foreach (explode(',', $flags) as $flag) {
			[$flagName, $value] = array_pad(explode('=', trim($flag), 2), 2, null);
			if (strtoupper(trim((string)$flagName)) === $name) {
				return $value === null ? '' : trim($value);
			}
		}
		return null;
	}

	private static function hasFlag(string $flags, string $name): bool
	{
		foreach (explode(',', $flags) as $flag) {
			if (strtoupper(trim($flag)) === $name) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Entry keys of one rule: the path alone, or one key per combination of
	 * the condition chains (`path?name=value&…`).
	 *
	 * @param list<list<string>> $chains
	 * @return list<string>
	 */
	private static function keys(string $path, array $chains): array
	{
		if ($chains === []) {
			return [$path];
		}
		$combinations = [[]];
		foreach ($chains as $options) {
			$next = [];
			foreach ($combinations as $combination) {
				foreach ($options as $option) {
					$next[] = [...$combination, $option];
				}
			}
			$combinations = $next;
		}
		return array_map(
			static fn (array $combination): string => $path . '?' . implode('&', $combination),
			$combinations
		);
	}

	/** Are all flags of the list one the converter handles or may ignore (self::FLAGS)? */
	private static function knownFlags(string $flags): bool
	{
		foreach (explode(',', $flags) as $flag) {
			$name = strtoupper(trim(explode('=', $flag, 2)[0]));
			if ($name !== '' && !in_array($name, self::FLAGS, true)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * A regex that is a literal path (`^/alt/?$` → 'alt'), or null when it
	 * is a real pattern. The WordPress home forms become ''. Without a
	 * leading `^` the regex may match anywhere in the path: null. Without a
	 * closing `$` it matches the paths below as well: $anchored is false.
	 */
	private static function literal(string $pattern, bool &$anchored): string|null
	{
		$s = $pattern;
		if (!str_starts_with($s, '^')) {
			return null;
		}
		$s = substr($s, 1);
		$anchored = str_ends_with($s, '$');
		if ($anchored) {
			$s = substr($s, 0, -1);
		}
		if ($s === '(index\.php)?') {
			return '';
		}
		// `\/?` first: `/?` also ends it and would leave the backslash behind.
		if (str_ends_with($s, '\/?')) {
			$s = substr($s, 0, -3);
		} elseif (str_ends_with($s, '/?')) {
			$s = substr($s, 0, -2);
		}

		$out = '';
		$length = strlen($s);
		for ($i = 0; $i < $length; $i++) {
			$c = $s[$i];
			if ($c === '\\') {
				$next = $s[$i + 1] ?? '';
				if ($next === '.' || $next === '-' || $next === '/') {
					$out .= $next;
					$i++;
					continue;
				}
				return null;
			}
			if (str_contains(self::META, $c)) {
				return null;
			}
			$out .= $c;
		}
		return trim($out, '/');
	}

	/** A converted line that Apache applied to the paths below the entry's path as well. */
	private static function note(array &$state, int $line, string $body): void
	{
		$state['partial'][] = ['line' => $line, 'text' => $body, 'reason' => 'prefix'];
	}

	/**
	 * One QUERY_STRING pattern as its options, each `name=value` or a bare
	 * `name` ("present, any value"). Null when the pattern is not one of the
	 * accepted forms (the group is then poisoned).
	 *
	 * @return list<string>|null
	 */
	private static function queryOptions(string $pattern): array|null
	{
		$core = $pattern;
		if (str_ends_with($core, '(&|$)')) {
			$core = substr($core, 0, -5);
		} elseif (str_ends_with($core, '$')) {
			$core = substr($core, 0, -1);
		}
		if (str_starts_with($core, '(^|&)')) {
			$core = substr($core, 5);
		} elseif (str_starts_with($core, '^')) {
			$core = substr($core, 1);
		}
		if (preg_match('/^([A-Za-z0-9_.-]+)(?:=(.*))?$/', $core, $m) !== 1) {
			return null;
		}
		$name = $m[1];
		$value = $m[2] ?? '';
		if ($value === '') {
			return [$name];
		}
		$alternatives = preg_match('/^\((.*)\)$/', $value, $a) === 1 ? explode('|', $a[1]) : [$value];

		$options = [];
		foreach ($alternatives as $alternative) {
			$literal = str_replace('\.', '.', $alternative);
			if (preg_match('/^[A-Za-z0-9_%.-]+$/', $literal) !== 1) {
				return null;
			}
			$options[] = $name . '=' . $literal;
		}
		return $options;
	}

	/** Redirect and RedirectMatch: `[status] old target`. */
	private static function redirect(array &$state, int $line, string $body, string $rest, bool $regex): void
	{
		$tokens = preg_split('/\s+/', $rest, -1, PREG_SPLIT_NO_EMPTY) ?: [];
		$token = '';
		// Redirect: any first token that is not a path is a status (`303`, `seeother` → skipped).
		// RedirectMatch: the regex comes first when there is no status; a two-token form
		// only has a status when the word is one of the status spellings.
		$hasToken = $regex
			? count($tokens) >= 3 || (count($tokens) === 2 && preg_match('/^(\d+|permanent|temp|gone)$/i', $tokens[0]) === 1)
			: count($tokens) >= 2 && !str_starts_with($tokens[0], '/');
		if ($hasToken) {
			$token = array_shift($tokens);
		}
		$status = self::statusOf($token);
		if ($status === null) {
			self::skip($state, $line, $body, 'status');
			return;
		}
		$old = $tokens[0] ?? '';
		$target = $tokens[1] ?? '';

		$anchored = true;
		if ($regex) {
			$path = self::literal($old, $anchored);
		} else {
			$anchored = false;   // mod_alias Redirect matches the path and everything below it
			$path = str_starts_with($old, '/') ? trim(substr($old, 1), '/') : null;
		}
		if ($path === null || $path === '') {
			self::skip($state, $line, $body, 'pattern');
			return;
		}
		$value = null;
		if ($status === 410) {
			$value = 410;
		} else {
			$destination = self::destination($target);
			if ($destination === null) {
				self::skip($state, $line, $body, 'target');
				return;
			}
			// mod_alias replaces the request's query with a query of the target's own (there is no QSA here).
			if (str_contains($destination['to'], '?')) {
				$destination['drop'] = true;
			}
			$value = self::value($status, $destination);
		}
		if (self::store($state, [$path], $value, $line, $body) && !$anchored) {
			self::note($state, $line, $body);
		}
	}

	/**
	 * The PHP source of a config file returning $entries (SPEC.md §3.7).
	 * The header goes into a doc comment: a star followed by a slash would
	 * end it and turn the rest of the line into code (a directory named `a*`
	 * puts one into the path of `hikkoshi convert`), so it is broken up with
	 * a backslash, and control characters are dropped.
	 */
	public static function render(array $entries, string $header = ''): string
	{
		$out = "<?php\n";
		if ($header === '') {
			$out .= "\n";
		} else {
			$out .= "/**\n";
			foreach (explode("\n", $header) as $line) {
				$line = str_replace('*/', '*\\/', I18n::printable($line));
				$out .= rtrim(' * ' . $line) . "\n";
			}
			$out .= " */\n";
		}
		$out .= "return [\n";
		foreach ($entries as $key => $value) {
			$out .= '    ' . var_export($key, true) . ' => ' . I18n::export($value) . ",\n";
		}
		$out .= "];\n";
		return $out;
	}

	/** RewriteRule: a redirect only with an R or G flag; conditions apply to it, then the group resets. */
	private static function rule(array &$state, int $line, string $body, string $rest): void
	{
		self::closeChain($state);
		$chains = $state['chains'];
		$poisoned = $state['poisoned'];
		$state['chains'] = [];
		$state['open'] = [];
		$state['poisoned'] = false;

		if (preg_match('/^(\S+)(?:\s+([^\[\s]\S*))?(?:\s+\[([^\]]*)\])?$/', $rest, $m) !== 1) {
			self::skip($state, $line, $body, 'target');
			return;
		}
		$pattern = $m[1];
		$target = $m[2] ?? '';
		$flags = $m[3] ?? '';
		$gone = self::hasFlag($flags, 'G') || self::hasFlag($flags, 'GONE');
		$redirect = $gone ? '410' : (self::flagValue($flags, 'R') ?? self::flagValue($flags, 'REDIRECT'));
		if ($redirect === null) {
			// An absolute URL without R: Apache redirects (302) unless the
			// host is the site's own — not decidable here, so it is reported.
			if (preg_match('~^https?://~i', $target) === 1) {
				self::skip($state, $line, $body, 'target');
			}
			return;   // an internal rewrite, not a redirect
		}
		// Only redirects are judged by their flags, so this follows the R/G decision above.
		if (!self::knownFlags($flags)) {
			self::skip($state, $line, $body, 'flag');
			return;
		}
		if ($poisoned) {
			self::skip($state, $line, $body, 'condition');
			return;
		}
		$status = self::statusOf($redirect);
		if ($status === null) {
			self::skip($state, $line, $body, 'status');
			return;
		}
		$anchored = true;
		$path = self::literal($pattern, $anchored);
		if ($path === null || ($path === '' && $chains === [])) {
			self::skip($state, $line, $body, 'pattern');
			return;
		}
		$value = null;
		if ($status === 410) {
			$value = 410;
		} else {
			$destination = self::destination($target);
			if ($destination === null) {
				self::skip($state, $line, $body, 'target');
				return;
			}
			// QSD always discards the query. A substitution with a query of its own (an
			// external target `…?x=1`, or a trailing `?` that empties it) replaces the
			// request's query, unless QSA appends the request's query to it again.
			$replaces = $destination['drop'] || str_contains($destination['to'], '?');
			$append = self::hasFlag($flags, 'QSA') || self::hasFlag($flags, 'QSAPPEND');
			$destination['drop'] = self::hasFlag($flags, 'QSD') || self::hasFlag($flags, 'QSDISCARD') ||
				($replaces && !$append);
			$value = self::value($status, $destination);
		}
		if (self::store($state, self::keys($path, $chains), $value, $line, $body) && !$anchored) {
			self::note($state, $line, $body);
		}
	}

	private static function skip(array &$state, int $line, string $body, string $reason): void
	{
		$state['skipped'][] = ['line' => $line, 'text' => $body, 'reason' => $reason];
	}

	/** Status of a Redirect token or an R flag value; '' (no token) is a temporary redirect. */
	private static function statusOf(string $token): int|null
	{
		return match (strtolower($token)) {
			'', '302', 'temp' => 302,
			'301', 'permanent' => 301,
			'307' => 307,
			'308' => 308,
			'410', 'gone' => 410,
			default => null,
		};
	}

	/** Adds the entry under all keys, or skips the line when one key already exists; true when added. */
	private static function store(array &$state, array $keys, mixed $value, int $line, string $body): bool
	{
		$seen = [];
		foreach ($keys as $key) {
			if (array_key_exists($key, $state['entries']) || isset($seen[$key])) {
				self::skip($state, $line, $body, 'duplicate');
				return false;
			}
			$seen[$key] = true;
		}
		foreach ($keys as $key) {
			$state['entries'][$key] = $value;
		}
		return true;
	}

	/** The value shape of SPEC.md §3.7. */
	private static function value(int $status, array $destination): mixed
	{
		if ($status === 410) {
			return 410;
		}
		if ($status === 301 && !$destination['drop']) {
			return $destination['to'];
		}
		$value = ['to' => $destination['to']];
		if ($status !== 301) {
			$value['status'] = $status;
		}
		if ($destination['drop']) {
			$value['query'] = 'drop';
		}
		return $value;
	}
}

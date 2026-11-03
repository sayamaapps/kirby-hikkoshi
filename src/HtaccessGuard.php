<?php

namespace Sayamaapps\Hikkoshi;

/**
 * What Apache reads or sends differently from the list, as predicates for the
 * converter (docs/SPEC.md §3.7). A line that one of them flags is reported
 * (skipped), not imported as a different address.
 *
 * Split out of Htaccess to keep that class small. Internal: not part of the
 * API. It declares its `Hikkoshi\*` alias like every class file of the map
 * (tests/LegacyAliasTest.php), although 1.x never had this class.
 *
 * @internal
 *
 * @package   Kirby Hikkoshi
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-hikkoshi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
final class HtaccessGuard
{
	/**
	 * Does a RedirectMatch target change on its way out? Apache 2.4 escapes the path of that target
	 * once more (`%20` is sent as `%2520`, query and fragment stay as written) and drops a backslash
	 * (`/a\b` is sent as `/ab`). Redirect sends its target as written, so it is not judged here.
	 */
	public static function changesMatchTarget(string $target): bool
	{
		return str_contains($target, '%') || self::dropsBackslash($target);
	}

	/**
	 * Does a RewriteRule target change on its way out? `#` unless NE, `%` and `${`, and a backslash:
	 * see the three predicates below. Other characters are not judged.
	 */
	public static function changesRewriteTarget(string $target, bool $noEscape): bool
	{
		return self::escapesHash($target, $noEscape) || self::expandsTarget($target) || self::dropsBackslash($target);
	}

	/**
	 * Does Apache read the old path of a Redirect, RedirectMatch or RewriteRule decoded, and the list
	 * with another result? Apache compares the pattern with the decoded path: `^/k%C3%B6ln$` matches
	 * `/k%25C3%25B6ln` and never `/köln`. The list decodes its keys (`k%C3%B6ln` → `koeln`) and would
	 * match `/köln`, `/koeln` and `/k%C3%B6ln` instead. So a `%` in an old path is not judged any
	 * further: it is reported, wherever it sits (the Validator accepts no `%` in a path either).
	 */
	public static function decodesSource(string $path): bool
	{
		return str_contains($path, '%');
	}

	/**
	 * Does Apache drop a backslash of a RewriteRule or RedirectMatch target? `https://x.example/a\b`
	 * is sent as `https://x.example/ab` (`a\\b` of a RewriteRule as `a%5cb`), also with NE, while a
	 * browser reads a `\` of an http(s) path as `/`. The list would send the backslash as written.
	 */
	private static function dropsBackslash(string $target): bool
	{
		return str_contains($target, '\\');
	}

	/**
	 * Does Apache change a `#` of the RewriteRule target? Without NE (`noescape`) it escapes
	 * the substitution, so the Location carries `%23` (a path segment) where the file reads
	 * a fragment. With NE the target is sent as written. Other characters are not judged here.
	 */
	private static function escapesHash(string $target, bool $noEscape): bool
	{
		return str_contains($target, '#') && !$noEscape;
	}

	/**
	 * Does Apache change a `%` or `${` of the RewriteRule target? Whatever the flags, it replaces
	 * `%0`–`%9` (backreferences of the last RewriteCond; `%20` reads as `%2` and `0`), `%{VAR}`
	 * (server variable) and `${map:key}` (RewriteMap). Without NE it also escapes every other `%`
	 * to `%25`. The list has no such expansion, and the outcome depends on the text, the flags and
	 * the conditions, so a target with a `%` is not judged any further: it is reported. (`$0`–`$9`
	 * are backreferences of the rule's pattern; Htaccess::destination() rejects them.)
	 */
	private static function expandsTarget(string $target): bool
	{
		return str_contains($target, '%') || str_contains($target, '${');
	}
}

// Declared like every class file (legacy.php); there is no 1.x use of this name.
if (class_exists('Hikkoshi\\HtaccessGuard', false) === false) {
	class_alias(HtaccessGuard::class, 'Hikkoshi\\HtaccessGuard');
}

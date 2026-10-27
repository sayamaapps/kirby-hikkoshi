<?php

/**
 * Resolver (src/Resolver.php), Kirby-free. Covers SPEC.md §8.2: path
 * normalisation in the lookup, the suffix pass, umlauts and percent-encoding,
 * lowercase and transliterate off, query conditions (WordPress .htaccess), the
 * specificity order, conditionsMatch(), targetQuery(), appendQuery().
 *
 *   php tests/ResolverTest.php
 *
 * Entries come from Validator::assert() with the default settings unless a
 * case says otherwise.
 */
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../src/I18n.php';
use Sayamaapps\Hikkoshi\Options;
use Sayamaapps\Hikkoshi\Resolver;
use Sayamaapps\Hikkoshi\Validator;

#[CoversClass(\Sayamaapps\Hikkoshi\Resolver::class)]
#[CoversClass(\Sayamaapps\Hikkoshi\Validator::class)]
#[CoversClass(\Sayamaapps\Hikkoshi\Options::class)]
final class ResolverTest extends TestCase
{
	public function testSuite(): void
	{
		// ── menue: path normalisation in the lookup (§8.2) ──────────────────────
		$menue = rResolver(['menue' => 'speisekarte']);

		foreach (['/menue', '/menue/', 'menue', '/MENUE', '/Menue/'] as $path) {
			expect('menue', rKey($menue, $path), "match $path: key menue");
			expect('exact', rHit($menue->match($path))['pass'] ?? null, "match $path: exact pass");
		}

		expect(null, rKey($menue, '/menue/unterseite'), '/menue/unterseite: no match');

		expect(null, rKey($menue, '/menu'), '/menu: no match');

		expect(null, rKey($menue, '/menues'), '/menues: no match');

		// Query: the entry has no conditions, so any query matches; remaining is the whole query.
		$query = ['utm_source' => 'qr', 'probe' => ['1']];

		$hit = rHit($menue->match('/menue', $query));

		expect(['key' => 'menue', 'pass' => 'exact', 'path' => 'menue', 'remaining' => $query], $hit, 'menue with a query: remaining is the whole query');

		expect($query, Resolver::targetQuery($menue->match('/menue', $query)), 'keep: targetQuery returns the whole remaining query');

		$dropping = rResolver(['menue' => ['to' => 'speisekarte', 'query' => 'drop']]);

		expect([], Resolver::targetQuery($dropping->match('/menue', $query)), 'entry with query drop: targetQuery is []');

		// The hit of the match is the entry itself.
		$match = $menue->match('/menue');

		expect('speisekarte', $match['entry']['target'], 'match: entry carries its target');

		expect(['entry', 'pass', 'path', 'remaining'], array_keys($match), 'match: shape entry, pass, path, remaining');

		// ── Suffix pass (§8.2, §3.3 step 2) ──────────────────────────────────────
		$suffix = rResolver(['impressum' => 'legal', 'blog' => 'news']);

		foreach (['/impressum.html', '/Impressum.HTML', '/impressum.htm', '/impressum.php'] as $path) {
			$match = $suffix->match($path);
			expect('impressum', $match['entry']['key'] ?? null, "suffix $path: key impressum");
			expect('suffix', $match['pass'] ?? null, "suffix $path: suffix pass");
			expect('impressum', $match['path'] ?? null, "suffix $path: path impressum");
		}

		foreach (['/blog/index.php', '/blog/index.html'] as $path) {
			$match = $suffix->match($path);
			expect('blog', $match['entry']['key'] ?? null, "index file $path: key blog");
			expect('suffix', $match['pass'] ?? null, "index file $path: suffix pass");
		}

		expect(null, rKey($suffix, '/impressum.pdf'), '/impressum.pdf: no match (pdf is no suffix)');

		expect(null, rKey(rResolver(['impressum' => 'legal'], ['suffixes' => []]), '/impressum.html', []), 'suffixes [] switches the suffix pass off');

		expect(null, rKey(rResolver(['blog' => 'news'], ['indexFiles' => []]), '/blog/index.php', []), 'indexFiles [] switches the index pass off');

		expect('impressum', rKey(rResolver(['impressum' => 'legal'], ['suffixes' => '.HTML']), '/impressum.html', []), "suffixes as a single string '.HTML' works like ['html']");

		expect('blog', rKey(rResolver(['blog' => 'news'], ['indexFiles' => 'index.php']), '/blog/index.php', []), "indexFiles as a single string works like ['index.php']");

		// ── Pre-1.0 contract: 'impressum.html' catches only itself (§3.3 step 3) ───
		$pre10 = rResolver(['impressum.html' => 'impressum']);

		expect('impressum.html', rKey($pre10, '/impressum.html'), '/impressum.html: exact hit');

		expect(null, rKey($pre10, '/impressum'), "/impressum is NOT caught by 'impressum.html' (no reverse pass)");

		expect(null, rKey($pre10, '/impressum.php'), "/impressum.php is NOT caught by 'impressum.html'");

		// ── Umlauts and percent-encoding (§8.2) ─────────────────────────────────
		$umlaut = rResolver(['ueber-uns' => 'about']);

		foreach (['/über-uns', '/%C3%BCber-uns', '/Ü%62er-uns', '/%25C3%25BCber-uns'] as $path) {
			expect('ueber-uns', rKey($umlaut, $path), "umlaut/encoding $path: hit ueber-uns");
		}

		$noTranslit = rResolver(['ueber-uns' => 'about'], ['transliterate' => false]);

		expect(null, rKey($noTranslit, '/über-uns', []), 'transliterate off: /über-uns does not match');

		expect('ueber-uns', rKey($noTranslit, '/ueber-uns'), 'transliterate off: /ueber-uns matches');

		// ── lowercase off (§8.2, §4) ────────────────────────────────────────────
		$exactCase = rResolver(['Menue' => 'x'], ['lowercase' => false]);

		expect('Menue', rKey($exactCase, '/Menue'), 'lowercase off: /Menue hits the key Menue');

		expect(null, rKey($exactCase, '/menue'), 'lowercase off: /menue does not hit');

		// ── Conditions, WordPress .htaccess (§8.2) ─────────────────────────────────────
		$wp = rResolver([
			'?page_id=13' => 'ueber-uns',
			'?page_id=14' => 'kontakt',
			'?p=14' => 'kontakt',
			'?pagename=contact' => 'kontakt',
			'?feed' => 'home',
			'index.php?p=1' => 'home',
		]);

		expect(['key' => '?page_id=13', 'pass' => 'exact', 'path' => '', 'remaining' => []], rHit($wp->match('', ['page_id' => '13'])), "('', page_id=13): exact, condition key removed from remaining");

		// Condition keys are removed from remaining; the rest stays.
		expect(['key' => '?page_id=13', 'pass' => 'exact', 'path' => '', 'remaining' => ['utm' => 'x']], rHit($wp->match('/', ['page_id' => '13', 'utm' => 'x'])), "('/', page_id=13, utm=x): remaining is utm only");

		expect(['key' => '?page_id=14', 'pass' => 'suffix', 'path' => '', 'remaining' => []], rHit($wp->match('/index.php', ['page_id' => '14'])), "('/index.php', page_id=14): suffix pass to the home query");

		expect('?feed', rKey($wp, '', ['feed' => 'rss2']), "('', feed=rss2): ?feed");

		expect('?feed', rKey($wp, '', ['feed' => '']), "('', feed=''): ?feed (present, any value)");

		expect(null, rKey($wp, '', ['page_id' => '99']), "('', page_id=99): no match");

		expect(null, rKey($wp, '', []), "('', []): no match");

		expect('index.php?p=1', rKey($wp, '/index.php', ['p' => '1']), "('/index.php', p=1): exact beats suffix");

		expect(null, rKey($wp, '', ['p' => '1']), "('', p=1): null (the home page does not strip to index.php)");

		expect('?p=14', rKey($wp, '', ['p' => '14']), '(\'\', p=14) hits the home query ?p=14');

		// ── Specificity: more conditions first (§8.2) ───────────────────────────
		$specificFirst = rResolver(['a?k=v' => 'specific', 'a' => 'general']);

		$specificLast = rResolver(['a' => 'general', 'a?k=v' => 'specific']);

		foreach (['first' => $specificFirst, 'last' => $specificLast] as $order => $resolver) {
			expect('a?k=v', rKey($resolver, '/a', ['k' => 'v']), "specificity ($order in config): k=v hits the specific entry");
			$match = $resolver->match('/a', ['k' => 'w']);
			expect('a', $match['entry']['key'] ?? null, "specificity ($order in config): k=w falls back to the general entry");
			expect(['k' => 'w'], $match['remaining'] ?? null, "specificity ($order in config): remaining of the general entry is the whole query");
			expect('a', rKey($resolver, '/a', []), "specificity ($order in config): no query gives the general entry");
		}

		$two = rResolver(['a?k=1&l=2' => 'x']);

		expect('a?k=1&l=2', rKey($two, '/a', ['k' => '1', 'l' => '2']), 'two conditions: both hold');

		expect(null, rKey($two, '/a', ['k' => '1']), 'two conditions: one missing, no match');

		$arrayValue = rResolver(['a?k=v' => 'specific']);

		expect(null, rKey($arrayValue, '/a', ['k' => ['v']]), "array query value ['v'] never equals 'v'");

		// ── conditionsMatch directly (§8.2) ─────────────────────────────────────
		expect(true, Resolver::conditionsMatch([], ['anything' => 'x']), 'conditionsMatch: no conditions always holds');

		expect(true, Resolver::conditionsMatch([], []), 'conditionsMatch: no conditions, no query');

		expect(true, Resolver::conditionsMatch(['k' => null], ['k' => '']), 'conditionsMatch: present with an empty value');

		expect(true, Resolver::conditionsMatch(['k' => '1'], ['k' => 1]), 'conditionsMatch: int value compares as string');

		expect(false, Resolver::conditionsMatch(['k' => '1'], ['k' => ['1']]), 'conditionsMatch: array never equals a string');

		expect(false, Resolver::conditionsMatch(['k' => null], []), 'conditionsMatch: a missing key fails');

		expect(false, Resolver::conditionsMatch(['k' => '1'], ['k' => '2']), 'conditionsMatch: different value fails');

		// ── appendQuery (§3.3, §8.2) ────────────────────────────────────────────
		expect('http://h/p', Resolver::appendQuery('http://h/p', []), 'appendQuery: [] appends nothing');

		expect('http://h?u=q', Resolver::appendQuery('http://h', ['u' => 'q']), 'appendQuery: home URL without a slash');

		expect('https://x/p?a=1&b=2', Resolver::appendQuery('https://x/p?a=1', ['b' => '2']), 'appendQuery: joins with &');

		expect('https://x/p?b=2', Resolver::appendQuery('https://x/p?', ['b' => '2']), 'appendQuery: url ending in ?');

		expect('http://h/p?a=1#f', Resolver::appendQuery('http://h/p#f', ['a' => '1']), 'appendQuery: fragment kept at the end');

		$round = Resolver::appendQuery('http://h/p', ['probe' => ['1', '2'], 'x' => 'a b']);

		parse_str((string)parse_url($round, PHP_URL_QUERY), $back);

		expect(['probe' => ['1', '2'], 'x' => 'a b'], $back, 'appendQuery: array parameter round-trips through parse_str');

		// ── 410 entries (§8.2) ──────────────────────────────────────────────────
		$gone = rResolver(['menue' => 410]);

		$match = $gone->match('/menue', ['utm' => 'x']);

		expect([], Resolver::targetQuery($match), '410: targetQuery is [] even with remaining parameters');

		expect(['utm' => 'x'], $match['remaining'], '410: remaining is still computed');

		// ── Accessors (§3.3) ────────────────────────────────────────────────────
		$all = rResolver(['menue' => 'speisekarte', 'impressum.html' => 'impressum', '?page_id=82' => 'impressum']);

		expect(3, count($all->entries()), 'entries(): all three entries');

		expect(['menue', 'impressum.html', '?page_id=82'], array_column($all->entries(), 'key'), 'entries(): grouped by source, all keys present');

		$defaults = (new Resolver([]))->settings();

		expect(Options::DEFAULTS, $defaults, 'settings(): defaults when nothing is given');

		expect(302, (new Resolver([], ['status' => 302]))->settings()['status'], 'settings(): given values override the defaults');

		expect(Options::DEFAULTS['suffixes'], (new Resolver([], ['status' => 302]))->settings()['suffixes'], 'settings(): missing keys come from the defaults');

		// Match path is the normalised path, the entry is the validated one.
		expect(['key' => 'impressum.html', 'pass' => 'exact', 'path' => 'impressum.html', 'remaining' => []], rHit($pre10->match('/Impressum.HTML')) ?? null, 'match path is normalised, pass exact on the key impressum.html');
	}
}

/**
 * The part of a match the cases compare: the key of the hit, the pass, the
 * normalised path that hit and the remaining query. Null for no match.
 */
function rHit(array|null $match): array|null
{
	if ($match === null) {
		return null;
	}
	return [
		'key' => $match['entry']['key'],
		'pass' => $match['pass'],
		'path' => $match['path'],
		'remaining' => $match['remaining'],
	];
}

/** Shorthand for the key of the hit, or null. */
function rKey(Resolver $resolver, string $path, array $query = []): string|null
{
	$match = $resolver->match($path, $query);
	return $match === null ? null : $match['entry']['key'];
}

/** A resolver over a list with the given validation and matching settings. */
function rResolver(array $list, array $settings = []): Resolver
{
	return new Resolver(Validator::assert($list, $settings), $settings);
}

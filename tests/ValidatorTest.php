<?php

/**
 * Validator (src/Validator.php) and ConfigException (src/ConfigException.php),
 * Kirby-free. Covers SPEC.md §8.1: every check of the pre-1.0 validator test
 * with the adaptations of §8.1 (normalised keys accepted, chains are warnings,
 * 410 and array forms, external hosts, duplicates, query conditions), plus two
 * sample lists and config/hikkoshi.example.php.
 *
 *   php tests/ValidatorTest.php
 *
 * German expectations run after I18n::setLanguage('de'); the language is reset
 * to 'en' before finish().
 */
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../src/I18n.php';
require_once __DIR__ . '/../src/Options.php';
require_once __DIR__ . '/../src/Path.php';
require_once __DIR__ . '/../src/ConfigException.php';
require_once __DIR__ . '/../src/Validator.php';
require_once __DIR__ . '/../src/Resolver.php';
use Hikkoshi\ConfigException;
use Hikkoshi\I18n;
use Hikkoshi\Validator;

#[CoversNothing]
final class ValidatorTest extends TestCase
{
	public function testSuite(): void
	{
		// ── Valid lists ─────────────────────────────────────────────────────────// "valid list unchanged" (§8.1): keys, sources and targets come back as written.
		$gueltig = [
			'menue' => 'speisekarte',
			'produkt-c.html' => 'produkte',
			'speisekarte/karte-1.pdf' => 'speisekarte',
			'index.html' => 'home',
			'alt/tief/seite' => 'leistungen/beratung',
		];

		$result = Validator::validate($gueltig);

		expect([], $result['findings'], 'valid list: no findings');

		expect(array_keys($gueltig), array_column($result['entries'], 'key'), 'valid list: keys in config order');

		expect(array_keys($gueltig), array_column($result['entries'], 'source'), 'valid list: sources unchanged');

		expect(array_values($gueltig), array_column($result['entries'], 'target'), 'valid list: targets unchanged');

		expect([301, 301, 301, 301, 301], array_column($result['entries'], 'status'), 'valid list: status 301 by default');

		expect(['keep', 'keep', 'keep', 'keep', 'keep'], array_column($result['entries'], 'query'), 'valid list: query keep by default');

		expect([false, false, false, false, false], array_column($result['entries'], 'external'), 'valid list: nothing external');

		expect([[], [], [], [], []], array_column($result['entries'], 'conditions'), 'valid list: no conditions');

		expect($gueltig, array_column(Validator::assert($gueltig), 'target', 'key'), 'assert(): valid list returns the entries');

		expect([], Validator::assert([]), 'assert([]) returns []');

		expect([], Validator::validate([])['findings'], 'empty list: no findings');

		// ── Sample lists of 14 and 12 entries (§8.1) ────────────────────────────// A list in the style of a Jimdo export (14 entries)
		$jimdo_list = [
			'kurs-a.html' => 'produkte',
			'kurs-b.html' => 'produkte',
			'kurs-c.html' => 'produkt-c',
			'galerie-1.html' => 'team',
			'galerie-2.html' => 'team',
			'archiv-alt' => 'archiv',
			'archiv/2019' => 'archiv',
			'shop' => 'produkte',
			'downloads/karte-1.pdf' => 'speisekarte',
			'downloads/karte-2.pdf' => 'speisekarte',
			'impressum.html' => 'impressum',
			'datenschutz.html' => 'datenschutz',
			'kontakt.html' => 'kontakt',
			'index.html' => 'home',
		];

		$result = Validator::validate($jimdo_list);

		expect([], $result['findings'], 'Jimdo-style list (14 entries): zero findings');

		expect(14, count($result['entries']), 'Jimdo-style list: 14 entries');

		// A list in the style of a Wix export (12 entries)
		$wix_list = [
			'news'                    => 'home',
			'events'                  => 'home',
			'event-details'           => 'home',
			'team'                    => 'home',
			'faq'                     => 'home',
			'bookings'                => 'home',
			'about'                   => 'home',
			'contact'                 => 'home',
			'privacy-policy'          => 'datenschutz',
			'terms-and-conditions'    => 'impressum',
			'refund-policy'           => 'impressum',
			'accessibility-statement' => 'home',
		];

		$result = Validator::validate($wix_list);

		expect([], $result['findings'], 'Wix-style list (12 entries): zero findings');

		expect(12, count($result['entries']), 'Wix-style list: 12 entries');

		// config/hikkoshi.example.php must be valid (§6).
		$example = require __DIR__ . '/../config/hikkoshi.example.php';

		$result = Validator::validate($example);

		expect([], $result['findings'], 'config/hikkoshi.example.php: zero findings');

		expect(count($example), count($result['entries']), 'config/hikkoshi.example.php: every entry kept');

		// ── Numeric keys (§8.1) ─────────────────────────────────────────────────
		I18n::setLanguage('de');

		$result = Validator::validate(['2024' => 'archiv']);

		expect(['source.numeric'], codes($result['findings']), "'2024' => 'archiv': one error source.numeric");

		expect('error', $result['findings'][0]['level'], "'2024': level error");

		holds(str_contains($result['findings'][0]['message'], 'rein numerisch'), "'2024': message contains 'rein numerisch'");

		expect([], $result['entries'], "'2024': entry dropped");

		$result = Validator::validate(['produkte']);

		expect(['source.numeric'], codes($result['findings']), "['produkte'] (missing key): source.numeric");

		$result = Validator::validate(['archiv/2024' => 'archiv']);

		expect([], $result['findings'], "'archiv/2024' => 'archiv' is accepted");

		expect('archiv/2024', $result['entries'][0]['key'] ?? null, "'archiv/2024' kept as key");

		// ── Still rejected sources (§8.1 a) ─────────────────────────────────────// Each key with its code. The pre-1.0 rule for a segment: [a-z0-9-], one dot.
		$rejected = [
			'../secret' => 'source.invalid',
			'a/../secret' => 'source.invalid',
			'.hidden' => 'source.invalid',
			'a..html' => 'source.invalid',
			'a.b.c' => 'source.invalid',
			'a(*)' => 'source.invalid',
			'(:all)' => 'source.invalid',
			'blog/(:any)' => 'source.invalid',
			'https://example.org' => 'source.invalid',
			'mit leerzeichen' => 'source.invalid',
			'' => 'source.empty',
		];

		foreach ($rejected as $key => $code) {
			$result = Validator::validate([$key => 'produkte']);
			expect([$code], codes($result['findings']), 'rejected source ' . var_export($key, true) . ': ' . $code);
			expect([], $result['entries'], 'rejected source ' . var_export($key, true) . ': entry dropped');
		}

		// The message of an invalid old path names the entry (§5: Ungültiger alter Pfad).
		$result = Validator::validate(['../secret' => 'produkte']);

		expect("Ungültiger alter Pfad: '../secret' => 'produkte'", $result['findings'][0]['message'], 'German message of source.invalid');

		// ── Now accepted with their normalised source (§8.1 b) ──────────────────
		$normalised = [
			'Menue' => ['menue', []],
			'Impressum.HTML' => ['impressum.html', []],
			'über-uns' => ['ueber-uns', []],
			'%c3%bcber-uns' => ['ueber-uns', []],
			'a%2ehtml' => ['a.html', []],
			'/menue' => ['menue', []],
			'menue/' => ['menue', []],
			'a//b' => ['a/b', []],
			'a?b=1' => ['a', ['b' => '1']],
		];

		foreach ($normalised as $key => [$source, $conditions]) {
			$result = Validator::validate([$key => 'produkte']);
			expect([], $result['findings'], 'accepted key ' . var_export($key, true) . ': no findings');
			expect($source, $result['entries'][0]['source'] ?? null, 'accepted key ' . var_export($key, true) . ': source ' . $source);
			expect($conditions, $result['entries'][0]['conditions'] ?? null, 'accepted key ' . var_export($key, true) . ': conditions');
			expect($key, $result['entries'][0]['key'] ?? null, 'accepted key ' . var_export($key, true) . ': key as written');
		}

		// Lowercase off: the case is kept (pre-1.0 behaviour).
		$result = Validator::validate(['Menue' => 'produkte'], ['lowercase' => false]);

		expect('Menue', $result['entries'][0]['source'] ?? null, 'lowercase false: Menue keeps its case');

		// Transliterate off: an umlaut is no valid segment.
		$result = Validator::validate(['über-uns' => 'produkte'], ['transliterate' => false]);

		expect(['source.invalid'], codes($result['findings']), 'transliterate false: über-uns rejected');

		// ── Invalid targets (§8.1) ──────────────────────────────────────────────
		foreach (['/produkte', 'produkte/', 'produkte.html', '../produkte', 'Produkte', 'produkte?x=1', '', '(:all)', null] as $target) {
			$result = Validator::validate(['menue' => $target]);
			expect(['target.invalid'], codes($result['findings']), 'invalid target ' . var_export($target, true) . ': target.invalid');
			expect([], $result['entries'], 'invalid target ' . var_export($target, true) . ': entry dropped');
		}

		$result = Validator::validate(['menue' => ['produkte']]);

		expect(['entry.invalid'], codes($result['findings']), "target ['produkte'] (list value): entry.invalid");

		$result = Validator::validate(['menue' => 301]);

		expect(['entry.invalid'], codes($result['findings']), 'target 301 (int, not 410): entry.invalid');

		holds(str_starts_with($result['findings'][0]['message'], 'Ungültiger Eintrag: '), 'entry.invalid message is translated (de)');

		// External targets without the allow-list (§8.1).
		foreach (['https://example.org', '//example.org'] as $target) {
			$result = Validator::validate(['menue' => $target]);
			expect(['target.foreign'], codes($result['findings']), 'foreign target ' . $target . ': target.foreign');
			expect('example.org', $result['findings'][0]['values']['host'] ?? null, 'foreign target ' . $target . ': host value');
		}

		// A config that is not an array (§8.1).
		foreach (['produkte', 1] as $config) {
			$result = Validator::validate($config);
			expect(['config.notArray'], codes($result['findings']), 'config ' . var_export($config, true) . ': config.notArray');
			expect([], $result['entries'], 'config ' . var_export($config, true) . ': no entries');
			expect(null, $result['findings'][0]['key'], 'config.notArray has no key');
		}

		expect('Die Weiterleitungsliste muss ein Array alt => neu sein.', Validator::validate('produkte')['findings'][0]['message'], 'config.notArray message (de)');

		expect(['config.notArray'], codes(Validator::validate(null)['findings']), 'config null: config.notArray');

		// ── Self redirect (§8.1) ────────────────────────────────────────────────
		$result = Validator::validate(['menue' => 'menue']);

		expect(['target.self'], codes($result['findings']), "['menue' => 'menue']: target.self");

		holds(str_contains($result['findings'][0]['message'], 'sich selbst'), 'target.self message contains "sich selbst" (de)');

		expect(['target.self'], codes(Validator::validate(['Menue/' => 'menue'])['findings']), "['Menue/' => 'menue'] is self after normalisation");

		// ── Loops (§8.1) ────────────────────────────────────────────────────────
		$result = Validator::validate(['a' => 'b', 'b' => 'a']);

		expect(['loop'], codes($result['findings']), 'loop a → b → a: exactly one loop error');

		expect('error', $result['findings'][0]['level'], 'loop: level error');

		expect('a → b → a', $result['findings'][0]['values']['chain'], 'loop: chain value');

		expect('Weiterleitungsschleife: a → b → a', $result['findings'][0]['message'] ?? null, 'loop message (de)');

		expect('a', $result['findings'][0]['key'], 'loop: key of the entry concerned');

		$threeWay = Validator::validate(['a' => 'b', 'b' => 'c', 'c' => 'a']);

		expect(['loop'], codes($threeWay['findings']), 'three-way loop a→b→c→a: one loop error');

		$threw = null;

		try {
			Validator::assert(['a' => 'b', 'b' => 'a']);
		} catch (ConfigException $e) {
			$threw = $e;
		}

		holds($threw instanceof ConfigException, 'assert(loop) throws ConfigException');

		holds($threw instanceof LogicException, 'ConfigException is a LogicException (a site test catches that type)');

		expect('Weiterleitungsschleife: a → b → a', $threw?->getMessage(), 'ConfigException message is the loop line');

		expect(1, count($threw?->errors() ?? []), 'ConfigException errors(): one');

		expect(1, count($threw?->findings() ?? []), 'ConfigException findings(): one');

		// ── Chains: warnings without resolveChains (§8.1, §3.2.2) ───────────────
		I18n::setLanguage('de');

		$result = Validator::validate(['a' => 'b', 'b' => 'c']);

		expect(['chain'], codes($result['findings']), 'chain a → b → c: one chain finding, no error');

		expect('warning', $result['findings'][0]['level'], 'chain: level warning');

		expect('Weiterleitungskette: a → b → c. Das Ziel direkt eintragen.', $result['findings'][0]['message'], 'chain message exact (de)');

		expect('a', $result['findings'][0]['key'], 'chain: key a');

		expect(['a', 'b'], array_column($result['entries'], 'key'), 'chain: both entries kept');

		expect(['b', 'c'], array_column($result['entries'], 'target'), 'chain: targets unchanged');

		$assertChain = Validator::assert(['a' => 'b', 'b' => 'c']);

		expect(['a', 'b'], array_column($assertChain, 'key'), 'assert(chain): does not throw, returns both entries');

		$reversed = Validator::validate(['b' => 'c', 'a' => 'b']);

		expect(['chain'], codes($reversed['findings']), 'chain in reversed order: same warning');

		expect('a → b → c', $reversed['findings'][0]['values']['chain'], 'chain in reversed order: same text');

		$archiv = Validator::validate(['a' => 'archiv/2024', 'archiv/2024' => 'archiv']);

		expect(['chain'], codes($archiv['findings']), 'chain through a path with digits: warning');

		$three = Validator::validate(['a' => 'b', 'b' => 'c', 'c' => 'd']);

		expect(['chain', 'chain'], codes($three['findings']), 'three hops: two warnings');

		expect('a → b → c → d', $three['findings'][0]['values']['chain'], 'three hops: a → b → c → d');

		expect('b → c → d', $three['findings'][1]['values']['chain'], 'three hops: b → c → d');

		expect(['a', 'b', 'c'], array_column($three['entries'], 'key'), 'three hops: all entries kept');

		$conditional = Validator::validate(['?page_id=1' => 'start', 'start' => 'produkte']);

		expect(['chain'], codes($conditional['findings']), 'query-condition entry into a chain: warning');

		expect('?page_id=1 → start → produkte', $conditional['findings'][0]['values']['chain'], 'query-condition chain text');

		expect('?page_id=1', $conditional['findings'][0]['key'], 'query-condition chain key is the key as written');

		// A redirect to `home` lands on `/`, which the source `home` (`/home`) never matches.
		expect([], Validator::validate(['?page_id=1' => 'home', 'home' => 'start'])['findings'], 'target home is not captured by the source home: no chain');

		$homeResolve = Validator::validate(['index.html' => 'home', 'home' => 'speisekarte'], ['resolveChains' => true]);

		expect([], $homeResolve['findings'], 'target home with resolveChains: nothing to resolve');

		expect('home', vEntry($homeResolve['entries'], 'index.html')['target'] ?? null, 'target home with resolveChains: index.html still goes home');

		I18n::setLanguage('en');

		expect('Redirect chain: a → b → c. Enter the final target directly.', Validator::validate(['a' => 'b', 'b' => 'c'])['findings'][0]['message'], 'chain message (en)');

		// No chain (§8.1).
		expect([], Validator::validate(['produkte-alt' => 'produkte', 'shop' => 'produkte'])['findings'], 'two sources, one target: no chain');

		expect([], Validator::validate(['impressum.html' => 'impressum'])['findings'], 'impressum.html → impressum: no chain');

		expect([], Validator::validate(['a' => 'b', 'b.html' => 'c'])['findings'], "'b.html' is not the source 'b': no chain");

		expect([], Validator::validate(['a' => 'index', 'index?p=1' => 'b'])['findings'], 'a conditional entry never captures a target: no chain');

		// ── resolveChains (§8.1, §4) ────────────────────────────────────────────
		$resolved = Validator::validate(['a' => 'b', 'b' => 'c'], ['resolveChains' => true]);

		expect(['chain.resolved'], codes($resolved['findings']), 'resolveChains: one chain.resolved info');

		expect('info', $resolved['findings'][0]['level'], 'resolveChains: level info');

		expect('c', vEntry($resolved['entries'], 'a')['target'] ?? null, 'resolveChains: a takes the final target c');

		expect('c', vEntry($resolved['entries'], 'b')['target'] ?? null, 'resolveChains: b unchanged (b → c)');

		expect('c', $resolved['findings'][0]['values']['target'], 'resolveChains: finding target value');

		$resolved410 = Validator::validate(['a' => 'b', 'b' => 410], ['resolveChains' => true]);

		$a = vEntry($resolved410['entries'], 'a');

		expect(410, $a['status'] ?? null, 'resolveChains to 410: a becomes status 410');

		expect(true, array_key_exists('target', $a) && $a['target'] === null, 'resolveChains to 410: a target null');

		expect('drop', $a['query'] ?? null, 'resolveChains to 410: a query drop');

		expect('a → b → 410', $resolved410['findings'][0]['values']['chain'], 'resolveChains to 410: chain text ends in 410');

		$loopResolve = Validator::validate(['a' => 'b', 'b' => 'a'], ['resolveChains' => true]);

		expect(['loop'], codes($loopResolve['findings']), 'loop with resolveChains: loop error, nothing resolved');

		expect('b', vEntry($loopResolve['entries'], 'a')['target'] ?? null, 'loop with resolveChains: a keeps its target');

		// ── Value forms (§8.1, §6) ──────────────────────────────────────────────
		$result = Validator::validate(['m' => 410]);

		expect([], $result['findings'], 'value 410: no findings');

		expect([null, 410, 'drop', false], [$result['entries'][0]['target'], $result['entries'][0]['status'], $result['entries'][0]['query'], $result['entries'][0]['external']], 'value 410: target null, status 410, query drop');

		$result = Validator::validate(['m' => ['status' => 410]]);

		expect([null, 410, 'drop'], [$result['entries'][0]['target'], $result['entries'][0]['status'], $result['entries'][0]['query']], "['status' => 410] is gone too");

		$result = Validator::validate(['m' => ['to' => 'x', 'status' => 308, 'query' => 'drop']]);

		expect([], $result['findings'], 'array form with status 308 and query drop: no findings');

		expect(['x', 308, 'drop'], [$result['entries'][0]['target'], $result['entries'][0]['status'], $result['entries'][0]['query']], 'array form: target x, status 308, query drop');

		$result = Validator::validate(['m' => ['to' => 'x']], ['status' => 302]);

		expect(302, $result['entries'][0]['status'] ?? null, "['to' => 'x'] inherits status 302 from settings");

		$result = Validator::validate(['m' => ['to' => 'x']]);

		expect(301, $result['entries'][0]['status'] ?? null, "['to' => 'x'] defaults to status 301");

		$result = Validator::validate(['m' => 'x'], ['status' => 307, 'query' => 'drop']);

		expect([307, 'drop'], [$result['entries'][0]['status'], $result['entries'][0]['query']], 'string value takes status and query from settings');

		$result = Validator::validate(['m' => ['to' => 'x', 'status' => 200]]);

		expect(['status.invalid'], codes($result['findings']), 'status 200: status.invalid');

		expect('200', $result['findings'][0]['values']['status'], 'status.invalid value is var_export text');

		expect('m', $result['findings'][0]['key'], "status.invalid key is the entry key 'm'");

		$result = Validator::validate(['m' => ['to' => 'x', 'query' => 'maybe']]);

		expect(['query.invalid'], codes($result['findings']), "query 'maybe': query.invalid");

		expect("'maybe'", $result['findings'][0]['values']['query'], 'query.invalid value is var_export text');

		$result = Validator::validate(['m' => ['status' => 410, 'to' => 'x']]);

		expect(['entry.invalid'], codes($result['findings']), '410 with a target: entry.invalid');

		$result = Validator::validate(['m' => ['status' => 302]]);

		expect(['target.missing'], codes($result['findings']), 'status without target: target.missing');

		$result = Validator::validate(['m' => ['to' => 'x', 'foo' => 1]]);

		expect(['entry.invalid'], codes($result['findings']), 'unknown array key: entry.invalid');

		I18n::setLanguage('de');

		$result = Validator::validate(['m' => ['to' => 'x', 'status' => 200]]);

		expect("Ungültiger Status 200: 'm' => ['to' => 'x', 'status' => 200]. Erlaubt: 301, 302, 307, 308, 410.", $result['findings'][0]['message'], 'array entry printed on one line (de)');

		// ── External targets with allowedHosts (§8.1) ───────────────────────────
		$settings = ['allowedHosts' => ['example.org']];

		$result = Validator::validate(['m' => 'https://Example.org/x'], $settings);

		expect([], $result['findings'], 'allowed external target: no findings');

		expect([true, 'https://Example.org/x', 301], [$result['entries'][0]['external'], $result['entries'][0]['target'], $result['entries'][0]['status']], 'allowed external: external true, target kept as written');

		$result = Validator::validate(['m' => 'https://example.org:8080/y'], $settings);

		expect([], $result['findings'], 'port-less host allows host:8080');

		expect(true, $result['entries'][0]['external'] ?? null, 'port-less host: external');

		$result = Validator::validate(['m' => '//example.org'], $settings);

		expect([], $result['findings'], '//example.org allowed by example.org');

		expect(true, $result['entries'][0]['external'] ?? null, '//host: external');

		$result = Validator::validate(['m' => 'https://other.example/x'], $settings);

		expect(['target.foreign'], codes($result['findings']), 'host not listed: target.foreign');

		$result = Validator::validate(['m' => 'HTTPS://example.org/x', 'n' => 'Http://example.org/y'], $settings);

		expect([], $result['findings'], 'uppercase scheme: no findings');

		expect(['https://example.org/x', 'http://example.org/y'], array_column($result['entries'], 'target'), 'uppercase scheme is lowercased (Kirby throws on HTTPS:)');

		foreach (["https://example.org/x\r\nSet-Cookie: a=b", 'https://example.org/a b', "https://example.org/a\tb", "https://example.org/a\x00b", "https://example.org/a\x1bb"] as $target) {
			expect(['target.invalid'], codes(Validator::validate(['m' => $target], $settings)['findings']), 'URL with whitespace or a control character: target.invalid (' . json_encode($target) . ')');
		}

		expect([], Validator::validate(['m' => ' https://example.org/x '], $settings)['findings'], 'whitespace around a URL is trimmed, not an error');

		$result = Validator::validate(['m' => 'https://Example.org/x'], ['allowedHosts' => ' Example.org ']);

		expect([], $result['findings'], 'allowedHosts as a single string (as the option allows): no TypeError, host allowed');

		expect(['target.foreign'], codes(Validator::validate(['m' => 'https://example.org/x'], ['allowedHosts' => ''])['findings']), "allowedHosts '' allows nothing");

		$result = Validator::validate(['m' => ['to' => 'https://Example.org/x', 'query' => 'drop']], $settings);

		expect([], $result['findings'], 'array form external target: no findings');

		expect(true, $result['entries'][0]['external'] ?? null, 'array form external target: external');

		expect('drop', $result['entries'][0]['query'] ?? null, 'array form external target keeps its query mode');

		// ── Duplicates (§8.1) ───────────────────────────────────────────────────
		$result = Validator::validate(['menue' => 'a', 'Menue/' => 'b']);

		expect(['source.duplicate'], codes($result['findings']), "['menue' => 'a', 'Menue/' => 'b']: source.duplicate");

		expect('menue', $result['findings'][0]['values']['source'], 'duplicate values: source menue');

		expect('a', $result['findings'][0]['values']['target'], 'duplicate values: target of the first entry');

		expect(['menue'], array_column($result['entries'], 'key'), 'duplicate: only the first entry survives');

		expect('a', $result['entries'][0]['target'], 'duplicate: the first entry wins');

		$result = Validator::validate(['?p=1&q=2' => 'x', '?q=2&p=1' => 'y']);

		expect(['source.duplicate'], codes($result['findings']), 'same conditions in another order: duplicate');

		expect('?p=1&q=2', $result['findings'][0]['values']['source'], 'duplicate of conditions: source is the canonical identity');

		$result = Validator::validate(['menue' => 410, 'Menue' => 'b']);

		expect(['source.duplicate'], codes($result['findings']), 'duplicate of a 410: source.duplicate');

		expect('410', $result['findings'][0]['values']['target'], 'duplicate of a 410 reports target 410');

		I18n::setLanguage('en');

		// ── source.empty (§8.1) ─────────────────────────────────────────────────
		expect(['source.empty'], codes(Validator::validate(['' => 'x'])['findings']), "'' => 'x': source.empty");

		expect(['source.empty'], codes(Validator::validate(['?' => 'x'])['findings']), "'?' => 'x': source.empty");

		$result = Validator::validate(['?page_id=82' => 'impressum']);

		expect([], $result['findings'], "'?page_id=82' => 'impressum' is accepted");

		expect('', $result['entries'][0]['source'] ?? null, "'?page_id=82': source is the home page ''");

		expect(['page_id' => '82'], $result['entries'][0]['conditions'] ?? null, "'?page_id=82': condition page_id = 82");

		// ── Messages in English (§5 defaults) ───────────────────────────────────
		expect("Invalid redirect: 'menue' => '/produkte'", Validator::validate(['menue' => '/produkte'])['findings'][0]['message'], 'English target.invalid message');

		// ── identity() (§3.2) ───────────────────────────────────────────────────
		expect('a', Validator::identity('a', []), "identity('a', []) = a");

		expect('?p&q=2', Validator::identity('', ['q' => '2', 'p' => null]), "identity('', ['q'=>'2','p'=>null]) = ?p&q=2 (ksorted)");

		expect('menue?a=1&b', Validator::identity('menue', ['b' => null, 'a' => '1']), 'identity with a source and conditions');

		// ── hasErrors / ofLevel / finding (§3.2) ────────────────────────────────
		$mixed = [
			Validator::finding(Validator::WARNING, 'chain', ['chain' => 'a → b → c'], 'a'),
			Validator::finding(Validator::INFO, 'chain.resolved', ['chain' => 'x', 'target' => 'y'], 'x'),
			Validator::finding(Validator::ERROR, 'loop', ['chain' => 'a → b → a'], 'a'),
			Validator::finding(Validator::WARNING, 'chain', ['chain' => 'p → q → r'], 'p'),
		];

		expect(true, Validator::hasErrors($mixed), 'hasErrors: true with an error');

		expect(false, Validator::hasErrors(array_slice($mixed, 0, 2)), 'hasErrors: false with warnings and infos only');

		expect(false, Validator::hasErrors([]), 'hasErrors: false for no findings');

		expect(['chain', 'chain'], codes(Validator::ofLevel($mixed, Validator::WARNING)), 'ofLevel warning: two in order');

		expect(['loop'], codes(Validator::ofLevel($mixed, Validator::ERROR)), 'ofLevel error: the loop');

		expect([], Validator::ofLevel($mixed, 'nope'), 'ofLevel unknown level: []');

		expect(['level', 'code', 'message', 'key', 'values'], array_keys($mixed[0]), 'finding has the shape level, code, message, key, values');

		expect('Redirect chain: a → b → c. Enter the final target directly.', $mixed[0]['message'], 'finding() translates in the current language (en here)');

		// ── ConfigException (§3.2.3) ────────────────────────────────────────────
		$findings = [
			Validator::finding(Validator::WARNING, 'chain', ['chain' => 'a → b → c'], 'a'),
			Validator::finding(Validator::ERROR, 'loop', ['chain' => 'x → y → x'], 'x'),
			Validator::finding(Validator::ERROR, 'target.self', ['entry' => "'m' => 'm'"], 'm'),
		];

		$exception = new ConfigException($findings);

		expect("Redirect loop: x → y → x\nRedirect to itself: 'm' => 'm'", $exception->getMessage(), 'ConfigException: messages of the errors, one per line');

		expect(3, count($exception->findings()), 'ConfigException findings(): all, warnings too');

		expect(['loop', 'target.self'], codes($exception->errors()), 'ConfigException errors(): only the errors');

		holds($exception instanceof LogicException, 'ConfigException extends LogicException');

		I18n::setLanguage('en');
	}
}

/** The entry with this config key, or null. */
function vEntry(array $entries, string $key): array|null
{
	foreach ($entries as $entry) {
		if ($entry['key'] === $key) {
			return $entry;
		}
	}
	return null;
}

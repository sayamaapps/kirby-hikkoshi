<?php

/**
 * Path normalisation and character rules (src/Path.php), Kirby-free.
 *   php tests/PathTest.php
 */
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../src/I18n.php';
require_once __DIR__ . '/../src/Path.php';
use Hikkoshi\Path;

#[CoversNothing]
final class PathTest extends TestCase
{
	public function testSuite(): void
	{
		// ── normalize ───────────────────────────────────────────────────────────
		expect('menue', Path::normalize('menue'), 'plain path unchanged');

		expect('menue', Path::normalize('/menue/'), 'slashes trimmed');

		expect('menue', Path::normalize('  /menue  '), 'whitespace trimmed');

		expect('a/b', Path::normalize('a//b/'), 'double slash collapsed');

		expect('', Path::normalize(''), 'empty stays empty');

		expect('', Path::normalize('/'), 'root is empty');

		expect('menue', Path::normalize('Menue'), 'lowercased');

		expect('impressum.html', Path::normalize('Impressum.HTML'), 'suffix lowercased too');

		expect('Menue', Path::normalize('Menue', false), 'lowercase off keeps case');

		expect('ueber-uns', Path::normalize('über-uns'), 'umlaut transliterated');

		expect('ueber-uns', Path::normalize('Über-Uns'), 'uppercase umlaut, lowercased and transliterated');

		expect('Ueber-Uns', Path::normalize('Über-Uns', false), 'lowercase off: Ü → Ue');

		expect('über-uns', Path::normalize('über-uns', true, false), 'transliterate off keeps umlaut');

		expect('strasse', Path::normalize('straße'), 'ß → ss');

		expect('ueber-uns', Path::normalize('%C3%BCber-uns'), 'percent-decoded then transliterated');

		expect('ueber-uns', Path::normalize('%25C3%25BCber-uns'), 'double encoding decoded');

		expect('a.html', Path::normalize('a%2ehtml'), 'encoded dot decoded');

		expect('mit leerzeichen', Path::normalize('mit%20leerzeichen'), 'encoded space becomes a space (invalid later)');

		expect('leistungen/beratung', Path::normalize('/Leistungen/Beratung/'), 'nested path');

		if (class_exists(Normalizer::class)) {
		    expect('ueber-uns', Path::normalize("u\u{0308}ber-uns"), 'NFD umlaut composed, then transliterated');
		}

		// ── split ───────────────────────────────────────────────────────────────
		expect(['path' => 'ueber-uns', 'conditions' => []], Path::split('ueber-uns'), 'no query');

		expect(['path' => '', 'conditions' => ['page_id' => '82']], Path::split('?page_id=82'), 'home with condition');

		expect(['path' => 'index.php', 'conditions' => ['p' => '1', 'feed' => null]], Path::split('index.php?p=1&feed'), 'two conditions, one bare');

		expect(['path' => '', 'conditions' => ['pagename' => 'contact']], Path::split('?pagename=contact'), 'string value');

		expect(['path' => 'a', 'conditions' => ['k' => 'a b']], Path::split('a?k=a%20b'), 'value percent-decoded');

		expect(['path' => 'a', 'conditions' => ['k' => '']], Path::split('a?k='), 'empty value is a value, not "any"');

		expect(['path' => 'a', 'conditions' => []], Path::split('a?'), 'bare question mark: no conditions');

		expect(['path' => 'a', 'conditions' => ['k' => '1']], Path::split('a?&k=1&'), 'empty parts skipped');

		// ── stripSuffix ─────────────────────────────────────────────────────────
		$suffixes = ['html', 'htm', 'php'];

		$index = ['index.php', 'index.html', 'index.htm'];

		expect('impressum', Path::stripSuffix('impressum.html', $suffixes, $index), '.html removed');

		expect('impressum', Path::stripSuffix('impressum.php', $suffixes, $index), '.php removed');

		expect('blog/post', Path::stripSuffix('blog/post.htm', $suffixes, $index), 'nested .htm removed');

		expect('blog', Path::stripSuffix('blog/index.php', $suffixes, $index), 'index file removed');

		expect('', Path::stripSuffix('index.html', $suffixes, $index), 'index file at root gives home');

		expect(null, Path::stripSuffix('impressum', $suffixes, $index), 'nothing to strip');

		expect(null, Path::stripSuffix('karte.pdf', $suffixes, $index), 'unknown suffix untouched');

		expect(null, Path::stripSuffix('.html', $suffixes, $index), 'suffix only: nothing left, null');

		expect(null, Path::stripSuffix('', $suffixes, $index), 'empty path: null');

		expect(null, Path::stripSuffix('impressum.html', [], []), 'everything off: null');

		expect('impressum', Path::stripSuffix('impressum.html', ['.html'], []), 'suffix given with a dot works too');

		expect('index', Path::stripSuffix('index.html', $suffixes, []), 'no index files: index.html is an ordinary .html file, stripped to index');

		// ── isValidSource (after normalisation) ─────────────────────────────────
		foreach (['menue', 'produkt-c.html', 'speisekarte/karte-1.pdf', 'index.html',
		          'alt/tief/seite', 'archiv/2024', 'wp_content', 'Menue', 'a.b'] as $ok) {
		    holds(Path::isValidSource($ok), "valid source: $ok");
		}

		foreach (['../secret', 'a/../secret', '.hidden', 'a..html', 'a.b.c', 'a%2ehtml', '%c3%bcber-uns', 'a(*)', '(:all)',
		          'blog/(:any)', 'https://example.org', '/menue', 'menue/', 'a//b', 'mit leerzeichen', 'über-uns', 'a?b=1', ''] as $bad) {
		    holds(!Path::isValidSource($bad), 'invalid source: ' . var_export($bad, true));
		}

		// ── isValidTarget ───────────────────────────────────────────────────────
		foreach (['home', 'produkte', 'leistungen/beratung', 'archiv/2024', 'in-erinnerung'] as $ok) {
		    holds(Path::isValidTarget($ok), "valid target: $ok");
		}

		foreach (['https://example.org', '//example.org', '/produkte', 'produkte/', 'produkte.html', '../produkte',
		          'produkte?x=1', '', '(:all)', 'javascript:alert(1)', 'mailto:a@b.c', 'tel:+49', 'Produkte'] as $bad) {
		    holds(!Path::isValidTarget($bad), 'invalid target: ' . var_export($bad, true));
		}

		// ── externalHost ────────────────────────────────────────────────────────
		expect('example.org', Path::externalHost('https://example.org/x?y=1'), 'https host');

		expect('example.org', Path::externalHost('http://Example.ORG'), 'http host lowercased');

		expect('example.org', Path::externalHost('//example.org'), 'protocol-relative is external');

		expect('example.org:8080', Path::externalHost('https://example.org:8080/'), 'port kept');

		expect(null, Path::externalHost('produkte'), 'page id is not external');

		expect(null, Path::externalHost('/produkte'), 'absolute path is not external');

		expect(null, Path::externalHost('javascript:alert(1)'), 'javascript: is not external (and invalid)');

		expect(null, Path::externalHost('mailto:a@b.c'), 'mailto: is not external');

		// ── query ───────────────────────────────────────────────────────────────
		expect('', Path::query([]), 'empty query');

		expect('utm_source=qr', Path::query(['utm_source' => 'qr']), 'one parameter');

		expect('a=1&b=x%20y', Path::query(['a' => '1', 'b' => 'x y']), 'RFC 3986 encoding');

		parse_str(Path::query(['probe' => ['1']]), $back);

		expect(['probe' => ['1']], $back, 'array parameter round-trips through parse_str');
	}
}

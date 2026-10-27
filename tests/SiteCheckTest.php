<?php

/**
 * The site test helper with real Kirby (docs/SPEC.md §8.4): $site->hikkoshiCheck()
 * and SiteCheck::run(), in throwaway site directories, in-process (no server,
 * no sockets, no outbound HTTP).
 *
 *   php tests/SiteCheckTest.php
 *
 * A list with an error throws while the App is built (the routes are read
 * then), so the loop case builds the App with a valid list first and swaps
 * the list through a Closure option before the check runs.
 */
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/helpers.php';
use Kirby\Cms\App;
use Sayamaapps\Hikkoshi\ConfigException;

/** The pre-1.0 list without the draft target and the team entry. */
const SC_CLEAN_LIST = [
	'menue' => 'speisekarte',
	'produkt-c.html' => 'produkte',
	'tageskarte/karte-1.pdf' => 'speisekarte',
	'alt/tief/seite' => 'leistungen/beratung',
	'index.html' => 'home',
];

#[CoversClass(\Sayamaapps\Hikkoshi\SiteCheck::class)]
#[CoversClass(\Sayamaapps\Hikkoshi\ConfigException::class)]
#[CoversClass(\Sayamaapps\Hikkoshi\Validator::class)]
final class SiteCheckTest extends TestCase
{
	public function testSuite(): void
	{
		// --- off -----------------------------------------------------------------
		withSite(function (string $tmp): void {
			hikkoshi_write_config($tmp, ['menue' => 'speisekarte']);
			$off = hikkoshi_kirby($tmp);
			expect(['inactive'], codes($off->site()->hikkoshiCheck()), 'off: exactly [inactive]');
		});

		// --- clean list ----------------------------------------------------------
		withSite(function (string $tmp): void {
			$kirby = scKirby($tmp, SC_CLEAN_LIST);
			expect([], $kirby->site()->hikkoshiCheck(), 'the clean list (without the draft and team) gives []');
			expect([], \Sayamaapps\Hikkoshi\SiteCheck::run($kirby), 'SiteCheck::run() gives [] as well');
		});

		// --- missing and draft targets -------------------------------------------
		withSite(function (string $tmp): void {
			hikkoshi_page($tmp, '6_boom', 'boom', ['title' => 'Boom']);
			$kirby = scKirby($tmp, ['produkt-b.html' => 'produkt-b']);
			expect(['page.missing'], codes($kirby->site()->hikkoshiCheck()), 'draft target: exactly [page.missing]');

			$missing = scKirby($tmp, ['menue' => 'gibtsnicht']);
			expect(['page.missing'], codes($missing->site()->hikkoshiCheck()), 'missing page: exactly [page.missing]');
		});

		// --- chains and loops ----------------------------------------------------
		withSite(function (string $tmp): void {
			$chain = scKirby($tmp, ['menue' => 'speisekarte', 'speisekarte' => 'produkte']);
			$findings = $chain->site()->hikkoshiCheck();
			expect(['chain', 'check.hop'], codes($findings), 'chain: codes are chain (warning) and check.hop, nothing else');
			expect('warning', $findings[0]['level'] ?? null, 'chain: the chain finding is a warning');
			expect('error', $findings[1]['level'] ?? null, 'chain: check.hop is an error');

			expect(['chain'], codes(\Sayamaapps\Hikkoshi\SiteCheck::run($chain, false)), 'run(false) skips the render checks: [chain] only');

			// A loop is found when the App is built with a valid list, the list is then swapped.
			$GLOBALS['scList'] = ['menue' => 'speisekarte'];
			$loop = null;
			quietLog($tmp, function () use ($tmp, &$loop): void {
				$loop = hikkoshi_kirby($tmp, ['hikkoshi.active' => true, 'hikkoshi.redirects' => fn () => $GLOBALS['scList']]);
			});
			$GLOBALS['scList'] = ['a' => 'b', 'b' => 'a'];
			expect(['loop'], codes($loop->site()->hikkoshiCheck()), 'loop: exactly [loop], no render checks');
			unset($GLOBALS['scList']);
		});

		// --- a list with an error cannot be built ---------------------------------
		withSite(function (string $tmp): void {
			$thrown = null;
			try {
				scKirby($tmp, ['a' => 'b', 'b' => 'a']);
			} catch (ConfigException $e) {
				$thrown = $e;
			}
			holds($thrown instanceof ConfigException, 'a loop: the App cannot be built');
		});

		// --- closure list --------------------------------------------------------
		withSite(function (string $tmp): void {
			$kirby = hikkoshi_kirby($tmp, ['hikkoshi.active' => true, 'hikkoshi.redirects' => fn () => ['menue' => 'speisekarte']]);
			expect([], $kirby->site()->hikkoshiCheck(), "'hikkoshi.redirects' as a Closure works");
		});

		// --- 410, external, query condition --------------------------------------
		withSite(function (string $tmp): void {
			hikkoshi_page($tmp, '5_impressum', 'default', ['title' => 'Impressum']);
			$kirby = scKirby($tmp, [
				'karte-alt' => 410,
				'extern' => 'https://other.example/x',
				'?page_id=82' => 'impressum',
			], ['hikkoshi.allowedHosts' => ['other.example']]);
			expect([], $kirby->site()->hikkoshiCheck(), 'a 410 entry, an allowed external target and a query condition give []');
		});

		// --- error page and a throwing template as targets ------------------------
		withSite(function (string $tmp): void {
			hikkoshi_page($tmp, '6_boom', 'boom', ['title' => 'Boom']);
			file_put_contents($tmp . '/site/templates/boom.php', '<?php throw new RuntimeException("Boom");');

			$error = scKirby($tmp, ['menue' => 'error']);
			$findings = $error->site()->hikkoshiCheck();
			expect(['check.target'], codes($findings), 'error page as target: exactly [check.target]');
			expect(404, $findings[0]['values']['actual'] ?? null, 'error page as target: actual 404');

			$boom = scKirby($tmp, ['menue' => 'boom']);
			$findings = $boom->site()->hikkoshiCheck();
			expect(['check.target'], codes($findings), 'throwing template as target: exactly [check.target]');
			expect(500, $findings[0]['values']['actual'] ?? null, 'throwing template as target: actual 500');
		});

		// --- a status or redirect set by the target's template ----------------------
		withSite(function (string $tmp): void {
			hikkoshi_page($tmp, '6_wartung', 'wartung', ['title' => 'Wartung']);
			file_put_contents($tmp . '/site/templates/wartung.php', '<?php $kirby->response()->code(503); echo "Down";');
			$findings = scKirby($tmp, ['menue' => 'wartung'])->site()->hikkoshiCheck();
			expect(['check.target'], codes($findings), 'template sets 503: exactly [check.target]');
			expect(503, $findings[0]['values']['actual'] ?? null, 'template sets 503: actual 503');
		});

		withSite(function (string $tmp): void {
			hikkoshi_page($tmp, '6_umzug', 'umzug', ['title' => 'Umzug']);
			file_put_contents($tmp . '/site/templates/umzug.php', '<?php $kirby->response()->redirect("/team", 302);');
			expect(['check.hop'], codes(scKirby($tmp, ['menue' => 'umzug'])->site()->hikkoshiCheck()), 'template redirects: exactly [check.hop]');
		});

		withSite(function (string $tmp): void {
			hikkoshi_page($tmp, '6_ok', 'ok', ['title' => 'Ok']);
			file_put_contents($tmp . '/site/templates/ok.php', '<?php $kirby->response()->code(200); echo "Fine";');
			expect([], scKirby($tmp, ['menue' => 'ok'])->site()->hikkoshiCheck(), 'template sets 200 explicitly: []');
		});

		withSite(function (string $tmp): void {
			hikkoshi_page($tmp, '6_wartung', 'wartung', ['title' => 'Wartung']);
			file_put_contents($tmp . '/site/templates/wartung.php', '<?php $kirby->response()->code(503); echo "Down";');
			$kirby = scKirby($tmp, ['alt-a' => 'wartung', 'alt-b' => 'speisekarte']);
			$findings = $kirby->site()->hikkoshiCheck();
			expect(['check.target'], codes($findings), 'isolation: one finding for two entries, no false check.status');
			expect('alt-a', $findings[0]['key'] ?? null, 'isolation: the finding belongs to the first entry');
			expect(null, $kirby->response()->code(), 'afterwards the App Responder has no code again');
		});

		withSite(function (string $tmp): void {
			$kirby = scKirby($tmp, SC_CLEAN_LIST);
			$kirby->response()->code(418);
			$kirby->site()->hikkoshiCheck();
			expect(null, $kirby->response()->code(), 'after hikkoshiCheck() the Responder code is null');
		});

		// --- shape and German messages -------------------------------------------
		withSite(function (string $tmp): void {
			$kirby = scKirby($tmp, ['menue' => 'gibtsnicht']);
			$findings = $kirby->site()->hikkoshiCheck();
			expect(1, count($findings), 'shape: one finding');
			$finding = $findings[0];
			expect(['level', 'code', 'message', 'key', 'values'], array_keys($finding), 'shape: keys of a finding');
			expect('error', $finding['level'], 'shape: level');
			expect('page.missing', $finding['code'], 'shape: code');
			expect('menue', $finding['key'], 'shape: key is the config key');
			expect(['target' => 'gibtsnicht', 'entry' => "'menue' => 'gibtsnicht'"], $finding['values'], 'shape: values');
			holds(str_contains($finding['message'], 'does not exist or is a draft'), 'shape: English message by default');

			$german = scKirby($tmp, ['menue' => 'gibtsnicht'], ['hikkoshi.language' => 'de']);
			$finding = $german->site()->hikkoshiCheck()[0] ?? [];
			holds(str_contains((string)($finding['message'] ?? ''), 'existiert nicht oder ist ein Entwurf'), 'German message with hikkoshi.language de');
			expect('page.missing', $finding['code'] ?? null, 'German: the same code');
		});
	}
}

/** Runs $fn with the php error_log sent to a file, so warnings do not clutter the output. */
function quietLog(string $tmp, callable $fn): void
{
	$previous = ini_get('error_log');
	ini_set('error_log', $tmp . '/php-error.log');
	try {
		$fn();
	} finally {
		ini_set('error_log', (string)$previous);
	}
}

/** A fresh instance with Hikkoshi on, the list from a file, errors in the log only. */
function scKirby(string $tmp, array $list, array $extra = []): App
{
	hikkoshi_write_config($tmp, $list);
	$kirby = null;
	quietLog($tmp, function () use ($tmp, $extra, &$kirby): void {
		$kirby = hikkoshi_kirby($tmp, ['hikkoshi.active' => true] + $extra);
	});
	return $kirby;
}

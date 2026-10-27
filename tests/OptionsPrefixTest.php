<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Hikkoshi\Options;

/**
 * The option prefix (OPT-1): sayamaapps.hikkoshi.<key> is read first, the 1.x
 * spelling hikkoshi.<key> is a fallback with a deprecation, and null counts as
 * not set.
 */
#[CoversClass(\Sayamaapps\Hikkoshi\Options::class)]
final class OptionsPrefixTest extends TestCase
{
	/** @return list<string> the messages of the E_USER_DEPRECATED errors raised by $fn */
	private function deprecations(callable $fn): array
	{
		$messages = [];
		set_error_handler(static function (int $number, string $message) use (&$messages): bool {
			if ($number === E_USER_DEPRECATED) {
				$messages[] = $message;
				return true;
			}
			return false;
		}, E_USER_DEPRECATED);
		try {
			$fn();
		} finally {
			restore_error_handler();
		}
		return $messages;
	}

	public function testDefaultWhenNeitherSpellingIsSet(): void
	{
		withSite(function (string $tmp): void {
			$kirby = hikkoshi_kirby($tmp, []);

			expect(false, Options::active($kirby), 'unset: off');
			expect(301, Options::get($kirby, 'status', 301), 'unset: the default at the call site');
		});
	}

	public function testLegacySpellingStillWorksWithDeprecation(): void
	{
		withSite(function (string $tmp): void {
			$messages = $this->deprecations(function () use ($tmp) {
				$kirby = hikkoshi_kirby($tmp, ['hikkoshi.active' => true, 'debug' => true]);
				expect(true, Options::active($kirby), 'legacy spelling switches Hikkoshi on');
			});

			// The route is built when the App is created, so the message may come more than once.
			holds(in_array('Hikkoshi: hikkoshi.active is deprecated, use sayamaapps.hikkoshi.active', $messages, true), 'legacy spelling is deprecated');
		});
	}

	public function testNewSpellingIsReadWithoutDeprecation(): void
	{
		withSite(function (string $tmp): void {
			$messages = $this->deprecations(function () use ($tmp) {
				$kirby = hikkoshi_kirby($tmp, ['sayamaapps.hikkoshi.active' => true, 'debug' => true]);
				expect(true, Options::active($kirby), 'new spelling switches Hikkoshi on');
			});

			expect([], $messages, 'new spelling raises no deprecation');
		});
	}

	public function testNewSpellingWinsOverLegacy(): void
	{
		withSite(function (string $tmp): void {
			$kirby = hikkoshi_kirby($tmp, ['sayamaapps.hikkoshi.active' => true, 'hikkoshi.active' => false]);

			expect(true, Options::active($kirby), 'new spelling wins over the legacy one');
		});
	}

	public function testNullFallsBackToTheLegacySpelling(): void
	{
		withSite(function (string $tmp): void {
			$kirby = hikkoshi_kirby($tmp, ['sayamaapps.hikkoshi.status' => null, 'hikkoshi.status' => 302]);

			expect(302, Options::get($kirby, 'status', 301), 'null in the new spelling is not set');
		});
	}

	public function testPrefixConstantsKeepTheirValues(): void
	{
		// PREFIX stayed 'hikkoshi.' in 1.x; code that builds option keys from it must keep working.
		expect('hikkoshi.', Options::PREFIX, 'PREFIX keeps the 1.x value');
		expect('sayamaapps.hikkoshi.', Options::OPTION_PREFIX, 'OPTION_PREFIX is the 2.0 prefix');
	}
}

<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Hikkoshi\Options;

/**
 * Options are read as sayamaapps.hikkoshi.<key>, with the 1.x name hikkoshi.<key>
 * as fallback. The new name wins when both are set. Reading the 1.x name raises
 * no deprecation, and messages name the option as the site spelled it.
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

	protected function tearDown(): void
	{
		hikkoshi_teardown();
	}

	public function testDefaultWhenNotSet(): void
	{
		withSite(function (string $tmp): void {
			$kirby = hikkoshi_kirby($tmp, []);

			expect(false, Options::active($kirby), 'unset: off');
			expect(301, Options::get($kirby, 'status', 301), 'unset: the default at the call site');
		});
	}

	public function testMessagesNameTheSpellingTheSiteUses(): void
	{
		withSite(function (string $tmp): void {
			$message = static function (array $options) use ($tmp): string {
				try {
					Options::settings(hikkoshi_kirby($tmp, $options + ['debug' => true]));
				} catch (InvalidArgumentException $e) {
					return $e->getMessage();
				}
				return '';
			};

			holds(str_contains($message(['sayamaapps.hikkoshi.status' => 200]), 'sayamaapps.hikkoshi.status must be'), 'new spelling named in the message');
			holds(str_contains($message(['hikkoshi.status' => 200]), 'Hikkoshi: hikkoshi.status must be'), '1.x spelling named as the site wrote it');
			holds(!str_contains($message(['hikkoshi.status' => 200]), 'sayamaapps.'), 'a 1.x site gets no new name in the message');
		});
	}

	public function testNestedOneXFormStillWorks(): void
	{
		withSite(function (string $tmp): void {
			$kirby = hikkoshi_kirby($tmp, ['hikkoshi' => ['active' => true, 'status' => 302]]);

			expect(true, Options::active($kirby), "nested 'hikkoshi' => ['active' => true] switches on");
			expect(302, Options::get($kirby, 'status', 301), "nested 'hikkoshi' => ['status' => 302] is read");
		});
	}

	public function testNewNameWinsWhenBothAreSet(): void
	{
		withSite(function (string $tmp): void {
			$kirby = hikkoshi_kirby($tmp, ['hikkoshi.status' => 302, 'sayamaapps.hikkoshi.status' => 307]);

			expect(307, Options::get($kirby, 'status', 301), 'both set: the new name wins');
		});
	}

	public function testNewRedirectsOptionWorks(): void
	{
		withSite(function (string $tmp): void {
			$kirby = hikkoshi_kirby($tmp, [
				'sayamaapps.hikkoshi.active' => true,
				'sayamaapps.hikkoshi.redirects' => fn () => ['menue' => 'speisekarte'],
			]);

			[$status, , $location] = hikkoshi_render($kirby, '/menue');
			expect(301, $status, 'sayamaapps.hikkoshi.redirects answers /menue with 301');
			holds(is_string($location) && str_ends_with($location, '/speisekarte'), 'and points to speisekarte');
		});
	}

	public function testNullCountsAsNotSet(): void
	{
		withSite(function (string $tmp): void {
			$kirby = hikkoshi_kirby($tmp, ['sayamaapps.hikkoshi.status' => null, 'hikkoshi.query' => null]);

			expect(301, Options::get($kirby, 'status', 301), 'null is not set: the default');
			expect('keep', Options::get($kirby, 'query', 'keep'), 'null under 1.x is not set: the default');
		});
	}

	public function testPrefixConstants(): void
	{
		expect('sayamaapps.hikkoshi.', Options::OPTION_PREFIX, 'OPTION_PREFIX is the new spelling');
		expect('hikkoshi.', Options::PREFIX, 'PREFIX keeps the 1.x value');
	}

	public function testRaisesNoDeprecation(): void
	{
		withSite(function (string $tmp): void {
			$messages = $this->deprecations(function () use ($tmp): void {
				$kirby = hikkoshi_kirby($tmp, ['hikkoshi.active' => true, 'sayamaapps.hikkoshi.status' => 302, 'debug' => true]);
				Options::get($kirby, 'status', 301);
				Options::get($kirby, 'active', false);
			});

			expect([], $messages, 'reading both spellings raises no deprecation at runtime');
		});
	}

	public function testReadsTheNewPrefix(): void
	{
		withSite(function (string $tmp): void {
			$kirby = hikkoshi_kirby($tmp, ['sayamaapps.hikkoshi.active' => true]);

			expect(true, Options::active($kirby), 'sayamaapps.hikkoshi.active switches Hikkoshi on');
		});
	}

	public function testReadsTheOneXPrefix(): void
	{
		withSite(function (string $tmp): void {
			$kirby = hikkoshi_kirby($tmp, ['hikkoshi.active' => true, 'hikkoshi.status' => 302]);

			expect(true, Options::active($kirby), 'hikkoshi.active still switches Hikkoshi on');
			expect(302, Options::get($kirby, 'status', 301), 'hikkoshi.status is read as the fallback');
		});
	}

	public function testSetKeyNamesTheSpellingTheSiteUses(): void
	{
		withSite(function (string $tmp): void {
			expect('sayamaapps.hikkoshi.status', Options::setKey(hikkoshi_kirby($tmp, []), 'status'), 'nothing set: the new name');
			expect('sayamaapps.hikkoshi.status', Options::setKey(hikkoshi_kirby($tmp, ['sayamaapps.hikkoshi.status' => 307]), 'status'), 'new name set: the new name');
			expect('hikkoshi.status', Options::setKey(hikkoshi_kirby($tmp, ['hikkoshi.status' => 302]), 'status'), 'only 1.x set: the 1.x name');
			expect('sayamaapps.hikkoshi.status', Options::setKey(hikkoshi_kirby($tmp, ['hikkoshi.status' => 302, 'sayamaapps.hikkoshi.status' => 307]), 'status'), 'both set: the new name');
		});
	}
}

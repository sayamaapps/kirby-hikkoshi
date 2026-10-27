<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sayamaapps\Hikkoshi\Options;

/**
 * The option prefix of 1.x: `hikkoshi.<key>` is read, nothing else. A null
 * value counts as not set, and reading it raises no deprecation.
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

	public function testNullCountsAsNotSet(): void
	{
		withSite(function (string $tmp): void {
			$kirby = hikkoshi_kirby($tmp, ['hikkoshi.status' => null]);

			expect(301, Options::get($kirby, 'status', 301), 'null is not set: the default');
		});
	}

	public function testPrefixConstantKeepsItsValue(): void
	{
		expect('hikkoshi.', Options::PREFIX, 'PREFIX keeps the 1.x value');
	}

	public function testRaisesNoDeprecation(): void
	{
		withSite(function (string $tmp): void {
			$messages = $this->deprecations(function () use ($tmp): void {
				$kirby = hikkoshi_kirby($tmp, ['hikkoshi.active' => true, 'debug' => true]);
				Options::get($kirby, 'status', 301);
			});

			expect([], $messages, 'reading hikkoshi.* raises no deprecation at runtime');
		});
	}

	public function testReadsTheHikkoshiPrefix(): void
	{
		withSite(function (string $tmp): void {
			$kirby = hikkoshi_kirby($tmp, ['hikkoshi.active' => true]);

			expect(true, Options::active($kirby), 'hikkoshi.active switches Hikkoshi on');
		});
	}
}

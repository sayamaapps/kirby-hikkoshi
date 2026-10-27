<?php

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Every class file declares its 1.x name itself, so a file loaded on its own
 * (without index.php or legacy.php, as 1.x sites required src/ directly) still
 * provides Hikkoshi\*. Each file runs in its own PHP process: the test process
 * has the classes loaded already.
 */
#[CoversNothing]
final class LegacyAliasTest extends TestCase
{
	public function testEveryClassFileDeclaresItsLegacyNameWhenLoadedAlone(): void
	{
		$root = dirname(__DIR__);
		foreach (require $root . '/autoload.php' as $path) {
			$short = basename($path, '.php');
			$code = 'require ' . var_export($root . '/' . $path, true)
				. '; echo class_exists(' . var_export('Hikkoshi\\' . $short, true) . ', false) ? "yes" : "no";';

			$output = [];
			exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>&1', $output, $status);

			$this->assertSame(0, $status, $path . ' loads on its own');
			$this->assertSame('yes', implode("\n", $output), 'Hikkoshi\\' . $short . ' exists after loading ' . $path . ' alone');
		}
	}

	/**
	 * As index.php loads the plugin: every 1.x name exists right after legacy.php,
	 * before any class is used, and instanceof and catch work with the 1.x name
	 * (neither autoloads). Run in its own process: the test process has them loaded.
	 */
	public function testEveryOldNameExistsAfterThePluginFileAndInstanceofAndCatchWorkWithIt(): void
	{
		$root = dirname(__DIR__);
		$names = [];
		foreach (require $root . '/autoload.php' as $path) {
			$names[] = 'Hikkoshi\\' . basename($path, '.php');
		}

		$code = 'require ' . var_export($root . '/vendor/autoload.php', true) . ';'
			. ' Kirby\\Filesystem\\F::loadClasses(require ' . var_export($root . '/autoload.php', true) . ', ' . var_export($root, true) . ');'
			. ' require ' . var_export($root . '/legacy.php', true) . ';'
			. ' foreach (' . var_export($names, true) . ' as $name) { if (class_exists($name, false) === false) { echo "missing $name\n"; } }'
			. ' $e = new \Sayamaapps\Hikkoshi\ConfigException([]);'
			. ' echo $e instanceof \Hikkoshi\ConfigException ? "instanceof ok\n" : "instanceof failed\n";'
			. ' try { throw $e; } catch (\Hikkoshi\ConfigException $caught) { echo "catch ok\n"; }';

		$output = [];
		exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>&1', $output, $status);

		$this->assertSame(0, $status, 'the plugin file loads');
		$this->assertSame("instanceof ok\ncatch ok", implode("\n", $output), 'no 1.x name is missing, and instanceof and catch match with the 1.x name');
	}
}

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
}

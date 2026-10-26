<?php

/**
 * `hikkoshi check`, SPEC.md §8.6. Kirby-free: no vendor/, no index.php.
 *
 * Runs CheckCommand::run() in-process on php://memory streams and a temp
 * directory, and the real bin/hikkoshi once per case through proc_open.
 */
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../src/I18n.php';
require_once __DIR__ . '/../src/Options.php';
require_once __DIR__ . '/../src/Path.php';
require_once __DIR__ . '/../src/ConfigException.php';
require_once __DIR__ . '/../src/Validator.php';
require_once __DIR__ . '/../src/Htaccess.php';
require_once __DIR__ . '/../src/CheckCommand.php';
require_once __DIR__ . '/../src/ConvertCommand.php';
use Hikkoshi\CheckCommand;
use Hikkoshi\I18n;

#[CoversNothing]
final class CheckCommandTest extends TestCase
{
	public function testSuite(): void
	{
		I18n::setLanguage('en');

		/* ------------------------------------------------------------------------
		 * File lookup
		 * --------------------------------------------------------------------- */
		$dir = cc_tmp();

		try {
			[$code, $out, $err] = cc_run([], $dir);
			expect(2, $code, 'no file anywhere → exit 2');
			holds(str_contains($err, 'No redirect list found'), 'no file anywhere → message on stderr');
			expect('', $out, 'no file anywhere → nothing on stdout');
		} finally {
			cc_remove($dir);
		}

		$dir = cc_tmp();

		try {
			cc_write($dir . '/site/config/weiterleitungen.php', "<?php\n\nreturn ['a' => 'b', 'c' => 'd', 'e' => 'f'];\n");
			[$code, $out, $err] = cc_run([], $dir);
			expect(0, $code, 'valid weiterleitungen.php → exit 0');
			holds(str_contains($out, 'Checking ') && str_contains($out, 'weiterleitungen.php'), 'prints cli.file with the file');
			holds(str_contains($out, 'OK: 3 entries, no findings.'), 'prints cli.ok');
			expect('', $err, 'valid list → nothing on stderr');
		} finally {
			cc_remove($dir);
		}

		$dir = cc_tmp();

		try {
			cc_write($dir . '/site/config/hikkoshi.php', "<?php\nreturn ['x' => 'y'];\n");
			cc_write($dir . '/site/config/weiterleitungen.php', "<?php\nreturn ['a' => 'b', 'c' => 'd', 'e' => 'f'];\n");
			[$code, $out] = cc_run([], $dir);
			holds(str_contains($out, 'OK: 1 entries, no findings.'), 'hikkoshi.php wins over weiterleitungen.php');
			holds(str_contains($out, 'hikkoshi.php'), 'the checked file is hikkoshi.php');
			expect(0, $code, 'hikkoshi.php valid → exit 0');
		} finally {
			cc_remove($dir);
		}

		$dir = cc_tmp();

		try {
			cc_write($dir . '/site/config/list.php', "<?php\nreturn ['a' => 'b'];\n");
			[$code, $out] = cc_run(['site/config/list.php'], $dir);
			expect(0, $code, 'explicit relative FILE → exit 0');
			holds(str_contains($out, 'OK: 1 entries, no findings.'), 'explicit relative FILE is read');

			[$code, $out] = cc_run([$dir . '/site/config/list.php'], '/');
			expect(0, $code, 'explicit absolute FILE → exit 0');

			[$code, $out, $err] = cc_run(['missing.php'], $dir);
			expect(2, $code, 'missing explicit FILE → exit 2');
			holds(str_contains($err, 'File not found'), 'missing explicit FILE → File not found');
			expect('', $out, 'missing explicit FILE → nothing on stdout');

			[$code, $out, $err] = cc_run(['two.php', 'three.php'], $dir);
			expect(2, $code, 'two positional arguments → exit 2');
			holds(str_contains($err, 'Usage:'), 'two positional arguments → usage on stderr');
		} finally {
			cc_remove($dir);
		}

		/* ------------------------------------------------------------------------
		 * Findings
		 * --------------------------------------------------------------------- */
		$dir = cc_tmp();

		try {
			cc_write($dir . '/site/config/weiterleitungen.php', "<?php\nreturn ['a' => 'b', 'b' => 'a'];\n");
			[$code, $out, $err] = cc_run([], $dir);
			expect(1, $code, 'loop → exit 1');
			holds(str_contains($out, 'error: Redirect loop: a → b → a'), 'loop → error line');
			holds(str_contains($out, '2 entries, 1 errors, 0 warnings.'), 'loop → summary line');
			expect('', $err, 'loop → stderr stays empty');
		} finally {
			cc_remove($dir);
		}

		$dir = cc_tmp();

		try {
			cc_write($dir . '/site/config/weiterleitungen.php', "<?php\nreturn ['a' => 'b', 'b' => 'c'];\n");
			[$code, $out] = cc_run([], $dir);
			expect(0, $code, 'chain → exit 0 (warning only)');
			holds(str_contains($out, 'warning: Redirect chain: a → b → c.'), 'chain → warning line');

			[$code] = cc_run(['--strict'], $dir);
			expect(1, $code, 'chain with --strict → exit 1');

			[$code, $out] = cc_run(['--resolve-chains'], $dir);
			expect(0, $code, 'chain with --resolve-chains → exit 0');
			holds(str_contains($out, 'info: Redirect chain a → b → c resolved to c.'), 'chain with --resolve-chains → info line');
			holds(!str_contains($out, 'warning:'), 'chain with --resolve-chains → no warning');
		} finally {
			cc_remove($dir);
		}

		$dir = cc_tmp();

		try {
			cc_write($dir . '/site/config/weiterleitungen.php', "<?php\nreturn ['a' => 'https://other.example/x'];\n");
			[$code, $out] = cc_run([], $dir);
			expect(1, $code, 'foreign host without --allow-host → exit 1');
			holds(str_contains($out, 'error: Foreign domain other.example is not allowed as a target'), 'foreign host → error line');

			[$code, $out] = cc_run(['--allow-host=other.example'], $dir);
			expect(0, $code, '--allow-host makes the foreign host pass');
			holds(str_contains($out, 'OK: 1 entries, no findings.'), '--allow-host → no findings');

			[$code] = cc_run(['--allow-host=OTHER.example', '--allow-host=third.example'], $dir);
			expect(0, $code, '--allow-host is case-insensitive and repeatable');
		} finally {
			cc_remove($dir);
		}

		$dir = cc_tmp();

		try {
			cc_write($dir . '/site/config/weiterleitungen.php', "<?php\nreturn 'not a list';\n");
			[$code, $out] = cc_run([], $dir);
			expect(1, $code, 'non-array list → exit 1');
			holds(str_contains($out, 'error: The redirect list must be an array'), 'non-array list → error line');
		} finally {
			cc_remove($dir);
		}

		/* ------------------------------------------------------------------------
		 * Flags and language
		 * --------------------------------------------------------------------- */
		$dir = cc_tmp();

		try {
			cc_write($dir . '/site/config/weiterleitungen.php', "<?php\nreturn ['a' => 'b', 'b' => 'a'];\n");
			[$code, $out, $err] = cc_run(['--bogus'], $dir);
			expect(2, $code, 'unknown flag → exit 2');
			holds(str_contains($err, 'Usage:'), 'unknown flag → usage on stderr');
			expect('', $out, 'unknown flag → nothing on stdout');

			I18n::setLanguage('de');
			try {
				[$code, $out] = cc_run(['--lang=de'], $dir);
				expect(1, $code, '--lang=de accepted, loop still exit 1');
				holds(str_contains($out, 'Fehler: Weiterleitungsschleife: a → b → a'), '--lang=de → German messages');
			} finally {
				I18n::setLanguage('en');
			}
		} finally {
			cc_remove($dir);
		}

		$dir = cc_tmp();

		try {
			cc_write($dir . '/site/config/weiterleitungen.php', "<?php\nthrow new RuntimeException('boom');\n");
			[$code, $out, $err] = cc_run([], $dir);
			expect(1, $code, 'a config that throws → exit 1');
			holds(str_contains($err, 'boom'), 'a config that throws → its message on stderr');
			holds(!str_contains($out, 'boom'), 'a config that throws → the message is not printed on stdout');
			holds(str_contains($out, 'Checking '), 'a config that throws → cli.file is printed first');
		} finally {
			cc_remove($dir);
		}

		/* ------------------------------------------------------------------------
		 * The real binary
		 * --------------------------------------------------------------------- */
		$dir = cc_tmp();

		try {
			$loop = $dir . '/site/config/weiterleitungen.php';
			cc_write($loop, "<?php\nreturn ['a' => 'b', 'b' => 'a'];\n");
			[$code, $out, $err] = cc_binary(['check', $loop]);
			expect(1, $code, 'bin check loop → exit 1');
			holds(str_contains($out, 'error: Redirect loop: a → b → a'), 'bin check loop → error line on stdout');
			holds(str_contains($out, '2 entries, 1 errors, 0 warnings.'), 'bin check loop → summary');
			expect('', $err, 'bin check loop → stderr empty');

			cc_write($dir . '/site/config/ok.php', "<?php\nreturn ['a' => 'b'];\n");
			[$code, $out] = cc_binary(['check', $dir . '/site/config/ok.php']);
			expect(0, $code, 'bin check valid → exit 0');
			holds(str_contains($out, 'OK: 1 entries, no findings.'), 'bin check valid → OK line');

			[$code, $out] = cc_binary(['check', 'site/config/ok.php'], $dir);
			expect(0, $code, 'bin check relative FILE from the project directory → exit 0');

			[$code, $out] = cc_binary(['check', '--lang=de', $loop]);
			expect(1, $code, 'bin check --lang=de → exit 1');
			holds(str_contains($out, 'Fehler: Weiterleitungsschleife'), 'bin check --lang=de → German output');

			[$code, $out] = cc_binary(['check'], $dir);
			expect(1, $code, 'bin check without FILE uses site/config under the working directory');

			[$code, $out, $err] = cc_binary([]);
			expect(2, $code, 'bin without arguments → exit 2');
			holds(str_contains($err, 'Usage:') && str_contains($err, 'hikkoshi check'), 'bin without arguments → usage on stderr');

			[$code, $out] = cc_binary(['help']);
			expect(0, $code, 'bin help → exit 0');
			holds(str_contains($out, 'Usage:'), 'bin help → usage on stdout');

			[$code, $out, $err] = cc_binary(['nope']);
			expect(2, $code, 'bin unknown command → exit 2');
			holds(str_contains($err, 'Unknown command: nope'), 'bin unknown command → message on stderr');
		} finally {
			cc_remove($dir);
		}
	}
}

/** A fresh temp directory with site/config/ inside; the caller removes it. */
function cc_tmp(): string
{
	$dir = sys_get_temp_dir() . '/hikkoshi-check-' . bin2hex(random_bytes(6));
	mkdir($dir . '/site/config', 0777, true);
	return $dir;
}

/** Removes a temp tree created by cc_tmp(). Only ever called on such a directory. */
function cc_remove(string $dir): void
{
	if (!is_dir($dir)) {
		return;
	}
	foreach (scandir($dir) ?: [] as $name) {
		if ($name === '.' || $name === '..') {
			continue;
		}
		$path = $dir . '/' . $name;
		is_dir($path) ? cc_remove($path) : unlink($path);
	}
	rmdir($dir);
}

function cc_write(string $path, string $code): void
{
	if (!is_dir(dirname($path))) {
		mkdir(dirname($path), 0777, true);
	}
	file_put_contents($path, $code);
}

/** @return array{0: int, 1: string, 2: string} exit code, stdout, stderr of CheckCommand::run() */
function cc_run(array $args, string $cwd): array
{
	$out = fopen('php://memory', 'w+');
	$err = fopen('php://memory', 'w+');
	$code = CheckCommand::run($args, $out, $err, $cwd);
	rewind($out);
	rewind($err);
	$result = [$code, (string)stream_get_contents($out), (string)stream_get_contents($err)];
	fclose($out);
	fclose($err);
	return $result;
}

/** @return array{0: int, 1: string, 2: string} exit code, stdout, stderr of bin/hikkoshi */
function cc_binary(array $argv, string|null $cwd = null): array
{
	$root = dirname(__DIR__);
	$pipes = [];
	$process = proc_open(
		array_merge([PHP_BINARY, $root . '/bin/hikkoshi'], $argv),
		[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
		$pipes,
		$cwd ?? $root
	);
	if (!is_resource($process)) {
		return [-1, '', 'proc_open failed'];
	}
	$out = (string)stream_get_contents($pipes[1]);
	fclose($pipes[1]);
	$err = (string)stream_get_contents($pipes[2]);
	fclose($pipes[2]);
	$code = proc_close($process);
	return [$code, $out, $err];
}

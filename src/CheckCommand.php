<?php

namespace Sayamaapps\Hikkoshi;

/**
 * `hikkoshi check [FILE] [--lang=..] [--resolve-chains] [--allow-host=..] [--strict]`
 *
 * Kirby-free (docs/SPEC.md §3.8). Loads a redirect list,
 * runs the Validator and prints the findings. Never writes a file.
 *
 * @package   Kirby Hikkoshi
 * @author    Simon Räder
 * @link      https://github.com/sayamaapps/kirby-hikkoshi
 * @copyright Simon Räder
 * @license   https://opensource.org/licenses/MIT
 */
class CheckCommand
{
	/** `require`s a config file; its return value is the redirect list. */
	private static function load(string $file): mixed
	{
		return require $file;
	}

	/** An absolute FILE stays as it is; a relative one is taken from the working directory. */
	public static function resolvePath(string $file, string $cwd): string
	{
		return str_starts_with($file, '/') ? $file : $cwd . '/' . $file;
	}

	/**
	 * @param list<string> $args arguments after the command name
	 * @param resource $out
	 * @param resource $err
	 * @return int exit code: 0 no errors, 1 errors (or warnings with --strict), 2 usage / file not found
	 */
	public static function run(array $args, $out, $err, string $cwd): int
	{
		$file = null;
		$resolveChains = false;
		$strict = false;
		$hosts = [];

		foreach ($args as $arg) {
			if (str_starts_with($arg, '--lang=')) {
				continue;   // applied by bin/hikkoshi already
			}
			if ($arg === '--resolve-chains') {
				$resolveChains = true;
			} elseif ($arg === '--strict') {
				$strict = true;
			} elseif (str_starts_with($arg, '--allow-host=')) {
				$host = strtolower(trim(substr($arg, 13)));
				if ($host !== '') {
					$hosts[] = $host;
				}
			} elseif (str_starts_with($arg, '--') || $file !== null) {
				fwrite($err, I18n::t('cli.usage') . "\n");
				return 2;
			} else {
				$file = $arg;
			}
		}

		if ($file !== null) {
			$path = self::resolvePath($file, $cwd);
			if (!is_file($path)) {
				fwrite($err, I18n::printable(I18n::t('cli.fileMissing', ['file' => $file])) . "\n");
				return 2;
			}
		} else {
			$tried = [];
			$path = null;
			foreach (Options::FILES as $name) {
				$candidate = $cwd . '/site/config/' . $name;
				$tried[] = $candidate;
				if ($path === null && is_file($candidate)) {
					$path = $candidate;
				}
			}
			if ($path === null) {
				fwrite($err, I18n::printable(I18n::t('cli.noFile', ['files' => $tried])) . "\n");
				return 2;
			}
		}

		fwrite($out, I18n::printable(I18n::t('cli.file', ['file' => $path])) . "\n");

		try {
			$config = self::load($path);
		} catch (\Throwable $e) {
			fwrite($err, I18n::printable($e->getMessage()) . "\n");
			return 1;
		}

		$settings = array_replace(Options::DEFAULTS, [
			'resolveChains' => $resolveChains,
			'allowedHosts'  => $hosts,
		]);
		$result = Validator::validate($config, $settings);
		$findings = $result['findings'];

		// Messages quote the entries: no escape sequences into the terminal.
		foreach ($findings as $finding) {
			fwrite($out, I18n::t('level.' . $finding['level']) . ': ' . I18n::printable($finding['message']) . "\n");
		}

		$entries = count($result['entries']);
		if ($findings === []) {
			fwrite($out, I18n::t('cli.ok', ['entries' => $entries]) . "\n");
		} else {
			$errors = count(Validator::ofLevel($findings, Validator::ERROR));
			$warnings = count(Validator::ofLevel($findings, Validator::WARNING));
			fwrite($out, I18n::t('cli.summary', [
				'entries'  => $entries,
				'errors'   => $errors,
				'warnings' => $warnings,
			]) . "\n");
		}

		$errors = count(Validator::ofLevel($findings, Validator::ERROR));
		$warnings = count(Validator::ofLevel($findings, Validator::WARNING));
		if ($errors > 0 || ($strict && $warnings > 0)) {
			return 1;
		}
		return 0;
	}
}

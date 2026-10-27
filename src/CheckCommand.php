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
	/** Every file the command looks at without a FILE argument, in the order of Options::FILES. */
	private static function candidates(string $cwd): array
	{
		return array_map(static fn (string $name): string => $cwd . '/site/config/' . $name, Options::FILES);
	}

	/** The first of candidates() that exists, or null. */
	private static function defaultFile(string $cwd): string|null
	{
		foreach (self::candidates($cwd) as $candidate) {
			if (is_file($candidate)) {
				return $candidate;
			}
		}
		return null;
	}

	/** `require`s a config file; its return value is the redirect list. */
	private static function load(string $file): mixed
	{
		return require $file;
	}

	/**
	 * The switches and the FILE of the command line, or null for a usage error
	 * (an unknown switch, a second FILE). `--lang=` is applied by bin/hikkoshi already.
	 *
	 * @param list<string> $args
	 * @return array{file: string|null, resolveChains: bool, strict: bool, hosts: list<string>}|null
	 */
	private static function parse(array $args): array|null
	{
		$parsed = ['file' => null, 'resolveChains' => false, 'strict' => false, 'hosts' => []];
		foreach ($args as $arg) {
			if (str_starts_with($arg, '--lang=')) {
				continue;
			}
			if ($arg === '--resolve-chains') {
				$parsed['resolveChains'] = true;
			} elseif ($arg === '--strict') {
				$parsed['strict'] = true;
			} elseif (str_starts_with($arg, '--allow-host=')) {
				$host = strtolower(trim(substr($arg, 13)));
				if ($host !== '') {
					$parsed['hosts'][] = $host;
				}
			} elseif (str_starts_with($arg, '--') || $parsed['file'] !== null) {
				return null;
			} else {
				$parsed['file'] = $arg;
			}
		}
		return $parsed;
	}

	/** One line per finding, then the summary. Messages quote the entries: no escape sequences into the terminal. */
	private static function report(array $findings, int $entries, mixed $out): void
	{
		foreach ($findings as $finding) {
			fwrite($out, I18n::t('level.' . $finding['level']) . ': ' . I18n::printable($finding['message']) . "\n");
		}

		if ($findings === []) {
			fwrite($out, I18n::t('cli.ok', ['entries' => $entries]) . "\n");
			return;
		}
		$errors = count(Validator::ofLevel($findings, Validator::ERROR));
		$warnings = count(Validator::ofLevel($findings, Validator::WARNING));
		fwrite($out, I18n::t('cli.summary', [
			'entries'  => $entries,
			'errors'   => $errors,
			'warnings' => $warnings,
		]) . "\n");
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
	public static function run(array $args, mixed $out, mixed $err, string $cwd): int
	{
		$options = self::parse($args);
		if ($options === null) {
			fwrite($err, I18n::t('cli.usage') . "\n");
			return 2;
		}

		if ($options['file'] !== null) {
			$path = self::resolvePath($options['file'], $cwd);
			if (!is_file($path)) {
				fwrite($err, I18n::printable(I18n::t('cli.fileMissing', ['file' => $options['file']])) . "\n");
				return 2;
			}
		} else {
			$path = self::defaultFile($cwd);
			if ($path === null) {
				fwrite($err, I18n::printable(I18n::t('cli.noFile', ['files' => self::candidates($cwd)])) . "\n");
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
			'resolveChains' => $options['resolveChains'],
			'allowedHosts'  => $options['hosts'],
		]);
		$result = Validator::validate($config, $settings);
		$findings = $result['findings'];
		self::report($findings, count($result['entries']), $out);

		$errors = count(Validator::ofLevel($findings, Validator::ERROR));
		$warnings = count(Validator::ofLevel($findings, Validator::WARNING));
		if ($errors > 0 || ($options['strict'] && $warnings > 0)) {
			return 1;
		}
		return 0;
	}
}

// 1.x name for sites that load this file directly, without the plugin (legacy.php).
if (class_exists('Hikkoshi\\CheckCommand', false) === false) {
	class_alias(CheckCommand::class, 'Hikkoshi\\CheckCommand');
}

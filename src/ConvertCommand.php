<?php

namespace Hikkoshi;

/**
 * `hikkoshi convert FILE [--lang=..]` — prints the Hikkoshi list as PHP to
 * stdout, skipped and partly converted lines to stderr. Kirby-free (SPEC.md §3.9). Never
 * writes a file.
 */
final class ConvertCommand
{
	/**
	 * @param list<string> $args
	 * @param resource $out
	 * @param resource $err
	 * @return int exit code: 0 all converted, 1 lines skipped or converted partially, 2 usage / file not found
	 */
	public static function run(array $args, $out, $err, string $cwd): int
	{
		$file = null;
		foreach ($args as $arg) {
			if (str_starts_with($arg, '--lang=')) {
				continue;   // applied by bin/hikkoshi already
			}
			if (str_starts_with($arg, '--') || $file !== null) {
				fwrite($err, I18n::t('cli.usage') . "\n");
				return 2;
			}
			$file = $arg;
		}
		if ($file === null) {
			fwrite($err, I18n::t('cli.usage') . "\n");
			return 2;
		}

		$path = str_starts_with($file, '/') ? $file : $cwd . '/' . $file;
		$text = is_file($path) ? file_get_contents($path) : false;
		if ($text === false) {
			fwrite($err, I18n::printable(I18n::t('cli.fileMissing', ['file' => $file])) . "\n");
			return 2;
		}

		$result = Htaccess::convert($text);
		fwrite($out, Htaccess::render(
			$result['entries'],
			"Converted from {$file} by hikkoshi convert.\nReview before use and run: hikkoshi check"
		));

		foreach ($result['skipped'] as $skip) {
			// The line comes from the .htaccess: no escape sequences into the terminal.
			fwrite($err, I18n::printable(I18n::t('cli.skipped', [
				'line'   => $skip['line'],
				'reason' => I18n::t('reason.' . $skip['reason']),
				'text'   => $skip['text'],
			])) . "\n");
		}
		foreach ($result['partial'] as $note) {
			fwrite($err, I18n::printable(I18n::t('cli.partial', [
				'line'   => $note['line'],
				'reason' => I18n::t('reason.' . $note['reason']),
				'text'   => $note['text'],
			])) . "\n");
		}
		$skipped = count($result['skipped']);
		$partial = count($result['partial']);
		fwrite($err, I18n::t('cli.convertSummary', [
			'entries' => count($result['entries']),
			'skipped' => $skipped,
		]) . "\n");
		if ($partial > 0) {
			fwrite($err, I18n::t('cli.partialSummary', ['partial' => $partial]) . "\n");
		}

		return $skipped > 0 || $partial > 0 ? 1 : 0;
	}
}

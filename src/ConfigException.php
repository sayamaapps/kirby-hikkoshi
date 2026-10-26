<?php

namespace Hikkoshi;

/**
 * A redirect list with at least one error-level finding (SPEC.md §3.2.3).
 *
 * Extends LogicException on purpose: the pre-1.0 release threw LogicException,
 * and a site's own tests catch exactly that type. The message lists every error,
 * one per line, in the
 * current I18n language; findings() gives the structured list (errors AND
 * warnings) for a test suite or the CLI.
 *
 * Kirby-free (SPEC.md §2).
 */
final class ConfigException extends \LogicException
{
	/** @var list<array{level:string, code:string, message:string, key:?string, values:array}> */
	private array $findings;

	/** @param list<array{level:string, code:string, message:string, key:?string, values:array}> $findings */
	public function __construct(array $findings)
	{
		$this->findings = array_values($findings);
		$messages = [];
		foreach ($this->findings as $finding) {
			if (($finding['level'] ?? '') === Validator::ERROR) {
				$messages[] = $finding['message'];
			}
		}
		parent::__construct(implode("\n", $messages));
	}

	/** Only the error-level findings. */
	public function errors(): array
	{
		return array_values(array_filter($this->findings, static fn (array $f) => $f['level'] === Validator::ERROR));
	}

	/** All findings of the validation run, errors and warnings, in order. */
	public function findings(): array
	{
		return $this->findings;
	}
}

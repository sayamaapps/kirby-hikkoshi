<?php

/**
 * English strings — the default and the fallback for missing keys.
 * `{name}` placeholders are filled in by Hikkoshi\I18n::t(). tests/I18nTest.php
 * checks that every language file has the same keys and placeholders as this
 * one. Keys are the contract (SPEC.md §5); never rename one without a
 * CHANGELOG entry.
 *
 * `{entry}` is always the config entry as I18n::entry() prints it:
 * 'old' => 'new'.
 */
return [
    // Finding levels (Validator, SiteCheck, CLI).
    'level.error'   => 'error',
    'level.warning' => 'warning',
    'level.info'    => 'info',

    // Validator findings (SPEC.md §3.2). Code = key after "finding.".
    'finding.config.notArray'  => 'The redirect list must be an array old path => target.',
    'finding.source.numeric'   => 'Old path missing or purely numeric: {entry}',
    'finding.source.invalid'   => 'Invalid old path: {entry}',
    'finding.source.empty'     => 'The home page can only be an old path together with a query condition (such as \'?page_id=82\'): {entry}',
    'finding.source.duplicate' => 'Duplicate old path {source}: {entry} (already points to {target})',
    'finding.entry.invalid'    => 'Invalid entry: {entry}. Allowed are a page id, 410, or an array with the keys to, status and query.',
    'finding.target.invalid'   => 'Invalid redirect: {entry}',
    'finding.target.missing'   => 'Entry without target: {entry}',
    'finding.target.foreign'   => 'Foreign domain {host} is not allowed as a target: {entry}. Add it to hikkoshi.allowedHosts.',
    'finding.target.self'      => 'Redirect to itself: {entry}',
    'finding.status.invalid'   => 'Invalid status {status}: {entry}. Allowed: 301, 302, 307, 308, 410.',
    'finding.query.invalid'    => 'Invalid query setting {query}: {entry}. Allowed: keep, drop.',
    'finding.loop'             => 'Redirect loop: {chain}',
    'finding.chain'            => 'Redirect chain: {chain}. Enter the final target directly.',
    'finding.chain.resolved'   => 'Redirect chain {chain} resolved to {target}.',

    // SiteCheck findings (SPEC.md §3.6).
    'finding.inactive'       => 'Hikkoshi is off: set \'hikkoshi.active\' => true.',
    'finding.page.missing'   => 'Target page {target} does not exist or is a draft: {entry}',
    'finding.check.noMatch'  => 'The old path {source} does not match its own entry: {entry}',
    'finding.check.status'   => '{source} answers {actual} instead of {expected}',
    'finding.check.location' => '{source} redirects to {actual} instead of {expected}',
    'finding.check.target'   => 'Target {target} answers {actual} instead of 200',
    'finding.check.hop'      => 'Target {target} of {entry} is itself redirected',

    // 410 response (snippets/hikkoshi/gone.php).
    'gone.title' => 'Page removed',
    'gone.text'  => 'This page has been removed permanently.',

    // CLI (bin/hikkoshi, CheckCommand, ConvertCommand).
    'cli.usage' => "Usage:
  hikkoshi check [FILE] [--lang=en|de] [--resolve-chains] [--allow-host=HOST]... [--strict]
  hikkoshi convert FILE [--lang=en|de]

check    validates a redirect list (default: site/config/hikkoshi.php, then site/config/weiterleitungen.php).
         Exit 0 = no errors, 1 = errors (with --strict also warnings), 2 = usage or file not found.
convert  reads Apache Redirect, RedirectMatch and RewriteRule lines from an .htaccess and prints
         the Hikkoshi list as PHP. Never edits files. Exit 0 = all converted, 1 = lines skipped, 2 = usage.",
    'cli.file'           => 'Checking {file}',
    'cli.unknownCommand' => 'Unknown command: {command}',
    'cli.fileMissing'    => 'File not found: {file}',
    'cli.noFile'         => 'No redirect list found (tried {files}).',
    'cli.ok'             => 'OK: {entries} entries, no findings.',
    'cli.summary'        => '{entries} entries, {errors} errors, {warnings} warnings.',
    'cli.skipped'        => 'Skipped line {line} ({reason}): {text}',
    'cli.convertSummary' => '{entries} entries converted, {skipped} lines skipped.',
    'cli.partial'        => 'Line {line} converted for the exact path only ({reason}): {text}',
    'cli.partialSummary' => '{partial} lines converted for the exact path only; add entries for the paths below them or keep those rules in .htaccess.',

    // Reasons why the converter skipped a line (Htaccess::convert()).
    'reason.pattern'   => 'pattern is not a literal path',
    'reason.condition' => 'condition is not a query-string condition',
    'reason.target'    => 'target cannot be converted',
    'reason.status'    => 'status is not a redirect status',
    'reason.flag'      => 'flag cannot be expressed in the list',
    'reason.duplicate' => 'old path already converted',
    'reason.prefix'    => 'Apache also redirected the paths below it',
];

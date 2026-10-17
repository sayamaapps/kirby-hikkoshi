<?php
/**
 * Translations: every language has the same keys and placeholders as
 * English, no value is empty, and the helpers follow the language.
 *   php tests/I18nTest.php
 * Owned by the architect (shared file).
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../src/I18n.php';

use Hikkoshi\I18n;

$placeholders = function (string $text): array {
    preg_match_all('/\{(\w+)\}/', $text, $m);
    $names = array_unique($m[1]);
    sort($names);
    return $names;
};

$en = I18n::table('en');
holds(count($en) > 30, 'English table loaded');
foreach (I18n::LANGUAGES as $language) {
    $table = I18n::table($language);
    expect([], array_values(array_diff(array_keys($en), array_keys($table))), "$language: no missing keys");
    expect([], array_values(array_diff(array_keys($table), array_keys($en))), "$language: no extra keys");
    foreach ($en as $key => $text) {
        holds(isset($table[$key]) && is_string($table[$key]) && trim($table[$key]) !== '', "$language.$key not empty");
        expect($placeholders($text), $placeholders($table[$key] ?? ''), "$language.$key: same placeholders as English");
    }
}
expect([], I18n::table('fr'), 'unknown language: no table');

// Every key the code relies on exists (SPEC.md §5).
foreach ([
    'finding.config.notArray', 'finding.source.numeric', 'finding.source.invalid', 'finding.source.empty',
    'finding.source.duplicate', 'finding.entry.invalid', 'finding.target.invalid', 'finding.target.missing',
    'finding.target.foreign', 'finding.target.self', 'finding.status.invalid', 'finding.query.invalid',
    'finding.loop', 'finding.chain', 'finding.chain.resolved',
    'finding.inactive', 'finding.page.missing', 'finding.check.noMatch', 'finding.check.status',
    'finding.check.location', 'finding.check.target', 'finding.check.hop',
    'gone.title', 'gone.text',
    'cli.usage', 'cli.file', 'cli.unknownCommand', 'cli.fileMissing', 'cli.noFile', 'cli.ok', 'cli.summary',
    'cli.skipped', 'cli.convertSummary',
    'reason.pattern', 'reason.condition', 'reason.target', 'reason.status', 'reason.flag', 'reason.duplicate',
    'level.error', 'level.warning', 'level.info',
] as $key) {
    holds(isset($en[$key]), "key $key exists");
}

I18n::setLanguage('fr');
expect('en', I18n::language(), 'unknown language falls back to English');
expect('Redirect loop: a → b → a', I18n::t('finding.loop', ['chain' => 'a → b → a']), 'English with placeholder');
expect('no.such.key', I18n::t('no.such.key'), 'missing key returns the key');
expect("a[31mb\nc\td", I18n::printable("a\x1b[31m\x07b\r\nc\td\x7f"), 'printable() keeps newline and tab, drops ESC, BEL, CR, DEL (the ESC sequence loses its ESC)');
expect('ab', I18n::printable("a\u{9b}b"), 'printable() drops a C1 control (CSI) in UTF-8');
expect('Über-uns ✓', I18n::printable('Über-uns ✓'), 'printable() leaves other UTF-8 alone');
expect('a?b', I18n::printable("a\x1b\xffb"), 'printable() on invalid UTF-8: invalid byte becomes ?, C0 controls dropped');
expect('a?[2J', I18n::printable("a\x9b[2J"), 'printable() turns a raw C1 byte (CSI on 8-bit terminals) into ?');
expect("'a' => 'b'", I18n::entry('a', 'b'), 'entry() prints like var_export');
expect("'a' => 410", I18n::entry('a', 410), 'entry() with an int value');
expect("'a' => ['to' => 'x', 'status' => 302]", I18n::entry('a', ['to' => 'x', 'status' => 302]), 'entry() prints arrays on one line in short syntax');
expect("0 => ['produkte']", I18n::entry(0, ['produkte']), 'entry() prints lists without keys');
expect("'a' => NULL", I18n::entry('a', null), 'entry() prints null like var_export');
expect('No redirect list found (tried a, b).', I18n::t('cli.noFile', ['files' => ['a', 'b']]), 'array values are joined');

I18n::setLanguage('de');
expect('de', I18n::language(), 'German selected');
expect('Weiterleitungskette: a → b → c. Das Ziel direkt eintragen.', I18n::t('finding.chain', ['chain' => 'a → b → c']), 'German chain message matches the pre-1.0 wording');
expect("Weiterleitung auf sich selbst: 'menue' => 'menue'", I18n::t('finding.target.self', ['entry' => I18n::entry('menue', 'menue')]), 'German self message');
expect("Ungültige Weiterleitung: '../secret' => 'produkte'", I18n::t('finding.target.invalid', ['entry' => I18n::entry('../secret', 'produkte')]), 'German invalid message matches the pre-1.0 wording');
I18n::setLanguage('en');

finish('Hikkoshi I18n');

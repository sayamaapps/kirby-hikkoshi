<?php
/**
 * `hikkoshi convert`, SPEC.md §8.7. Kirby-free: no vendor/, no index.php.
 *
 * The converter must never write: every case checks that the temp directory
 * holds only the input file afterwards.
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../src/I18n.php';
require_once __DIR__ . '/../src/Options.php';
require_once __DIR__ . '/../src/Path.php';
require_once __DIR__ . '/../src/ConfigException.php';
require_once __DIR__ . '/../src/Validator.php';
require_once __DIR__ . '/../src/Htaccess.php';
require_once __DIR__ . '/../src/CheckCommand.php';
require_once __DIR__ . '/../src/ConvertCommand.php';

use Hikkoshi\ConvertCommand;
use Hikkoshi\Htaccess;
use Hikkoshi\I18n;

I18n::setLanguage('en');

/** A fresh temp directory; the caller removes it. */
function cv_tmp(): string
{
    $dir = sys_get_temp_dir() . '/hikkoshi-convert-' . bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    return $dir;
}

/** Removes a temp tree created by cv_tmp(). Only ever called on such a directory. */
function cv_remove(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = $dir . '/' . $name;
        is_dir($path) ? cv_remove($path) : unlink($path);
    }
    rmdir($dir);
}

/** @return array{0: int, 1: string, 2: string} exit code, stdout, stderr of ConvertCommand::run() */
function cv_run(array $args, string $cwd): array
{
    $out = fopen('php://memory', 'w+');
    $err = fopen('php://memory', 'w+');
    $code = ConvertCommand::run($args, $out, $err, $cwd);
    rewind($out);
    rewind($err);
    $result = [$code, (string) stream_get_contents($out), (string) stream_get_contents($err)];
    fclose($out);
    fclose($err);
    return $result;
}

/** @return array{0: int, 1: string, 2: string} exit code, stdout, stderr of bin/hikkoshi */
function cv_binary(array $argv, ?string $cwd = null): array
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
    $out = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $err = (string) stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($process);
    return [$code, $out, $err];
}

/**
 * eval() is deliberate: it runs only the PHP that bin/hikkoshi convert printed
 * from our own arrays, to prove the output returns the same list (SPEC §8.7).
 */
function cv_eval(string $php): mixed
{
    return eval(substr($php, strlen('<?php')));
}

/* ------------------------------------------------------------------------
 * Usage and missing files
 * --------------------------------------------------------------------- */

$dir = cv_tmp();
try {
    [$code, $out, $err] = cv_run([], $dir);
    expect(2, $code, 'no FILE → exit 2');
    holds(str_contains($err, 'Usage:'), 'no FILE → usage on stderr');
    expect('', $out, 'no FILE → nothing on stdout');

    [$code, $out, $err] = cv_run(['missing.htaccess'], $dir);
    expect(2, $code, 'missing FILE → exit 2');
    holds(str_contains($err, 'File not found: missing.htaccess'), 'missing FILE → File not found');
    expect('', $out, 'missing FILE → nothing on stdout');

    [$code, $out, $err] = cv_run(['--bogus', 'x'], $dir);
    expect(2, $code, 'unknown flag → exit 2');
    holds(str_contains($err, 'Usage:'), 'unknown flag → usage on stderr');
} finally {
    cv_remove($dir);
}

/* ------------------------------------------------------------------------
 * Conversion
 * --------------------------------------------------------------------- */

$dir = cv_tmp();
try {
    file_put_contents($dir . '/input.htaccess', "RedirectMatch 301 ^/a\$ /b\nRedirect 303 /c /d\n");
    [$code, $out, $err] = cv_run(['input.htaccess'], $dir);

    expect(1, $code, 'a skipped line → exit 1');
    $header = "Converted from input.htaccess by hikkoshi convert.\nReview before use and run: hikkoshi check";
    expect(Htaccess::render(['a' => 'b'], $header), $out, 'stdout is exactly render() with the two-line header');
    holds(str_contains($err, 'Skipped line 2 (status is not a redirect status): Redirect 303 /c /d'), 'stderr names the skipped line');
    holds(str_contains($err, '1 entries converted, 1 lines skipped.'), 'stderr has the convert summary');
    expect(2, substr_count($err, "\n"), 'stderr has exactly two lines (skip and summary)');

    expect(['a' => 'b'], cv_eval($out), 'stdout evaluates to the converted list');
    expect(['input.htaccess'], array_values(array_diff(scandir($dir) ?: [], ['.', '..'])), 'the converter writes nothing');
    expect("RedirectMatch 301 ^/a\$ /b\nRedirect 303 /c /d\n", file_get_contents($dir . '/input.htaccess'), 'the input is unchanged');
} finally {
    cv_remove($dir);
}

$dir = cv_tmp();
try {
    file_put_contents($dir . '/ok.htaccess', "RedirectMatch 301 ^/a\$ /b\nRedirectMatch gone ^/c\$\n");
    [$code, $out, $err] = cv_run(['ok.htaccess'], $dir);
    expect(0, $code, 'only convertible lines → exit 0');
    holds(str_contains($err, '2 entries converted, 0 lines skipped.'), 'only convertible lines → 0 lines skipped');
    expect(['a' => 'b', 'c' => 410], cv_eval($out), 'convertible lines → list with 410');
} finally {
    cv_remove($dir);
}

$dir = cv_tmp();
try {
    file_put_contents($dir . '/abs.htaccess', "RedirectMatch 301 ^/x\$ /y\n");
    [$code, $out] = cv_run([$dir . '/abs.htaccess'], '/');
    expect(0, $code, 'absolute FILE is read');
    holds(str_contains($out, 'Converted from ' . $dir . '/abs.htaccess by hikkoshi convert.'), 'the header names the FILE as given');
} finally {
    cv_remove($dir);
}

$dir = cv_tmp();
try {
    file_put_contents($dir . '/prefix.htaccess', "Redirect 301 /a /b\n");
    [$code, $out, $err] = cv_run(['prefix.htaccess'], $dir);
    expect(1, $code, 'a partly converted line → exit 1');
    expect(['a' => 'b'], cv_eval($out), 'a partly converted line is still in the list');
    holds(str_contains($err, 'Line 1 converted for the exact path only (' . I18n::t('reason.prefix') . '): Redirect 301 /a /b'), 'stderr names the partial line via the translation');
    holds(str_contains($err, 'Apache also redirected the paths below it'), 'the prefix reason is not a bare key');
    holds(str_contains($err, '1 entries converted, 0 lines skipped.'), 'partial line → convert summary unchanged');
    holds(str_contains($err, '1 lines converted for the exact path only; add entries for the paths below them or keep those rules in .htaccess.'), 'partial line → partial summary');
    expect(3, substr_count($err, "\n"), 'stderr has three lines (partial, summary, partial summary)');

    file_put_contents($dir . '/anchored.htaccess', "RedirectMatch 301 ^/a\$ /b\n");
    [$code, $out, $err] = cv_run(['anchored.htaccess'], $dir);
    expect(0, $code, 'an anchored regex → exit 0');
    holds(!str_contains($err, 'exact path only'), 'an anchored regex → no partial line on stderr');
    expect(1, substr_count($err, "\n"), 'an anchored regex → only the convert summary');
} finally {
    cv_remove($dir);
}

$dir = cv_tmp();
try {
    file_put_contents($dir . '/flag.htaccess', "RewriteRule ^old$ /produkte [R=301,S=1]\n");
    [$code, $out, $err] = cv_run(['flag.htaccess'], $dir);
    expect(1, $code, 'unsupported flag → exit 1');
    holds(str_contains($err, 'Skipped line 1 (' . I18n::t('reason.flag') . '): RewriteRule ^old$ /produkte [R=301,S=1]'), 'stderr names the flag skip via the translation');
    holds(str_contains($err, 'flag cannot be expressed'), 'the flag reason is not a bare key');
} finally {
    cv_remove($dir);
}

/* ------------------------------------------------------------------------
 * Hostile file names and lines: no code from the path, no escape sequences
 * --------------------------------------------------------------------- */

$dir = cv_tmp();
try {
    // A directory `a*` puts a star-slash into the path; the file name carries code.
    $name = "a*/ \$GLOBALS['hikkoshiInjected'] = 1; __halt_compiler();.htaccess";
    mkdir($dir . '/a*');
    file_put_contents($dir . '/' . $name, "Redirect 301 /a /b\nRedirect 303 /c\x1b[2J /d\n");
    [$code, $out, $err] = cv_run([$name], $dir);
    expect(1, $code, 'hostile path → converted, one line skipped');
    $significant = array_values(array_filter(token_get_all($out), static fn ($t) => !is_array($t) || $t[0] !== T_WHITESPACE));
    expect([T_OPEN_TAG, T_DOC_COMMENT, T_RETURN], array_map(static fn ($t) => is_array($t) ? $t[0] : $t, array_slice($significant, 0, 3)),
        'hostile path → the header stays one doc comment');
    expect(['a' => 'b'], cv_eval($out), 'hostile path → stdout still evaluates to the list');
    holds(!isset($GLOBALS['hikkoshiInjected']), 'hostile path → nothing from the path ran');
    holds(!str_contains($err, "\x1b"), 'skipped line with ESC → no ESC on stderr');
    holds(str_contains($err, 'Redirect 303 /c[2J /d'), 'skipped line with ESC → the rest of the line is shown');
} finally {
    cv_remove($dir);
}

/* ------------------------------------------------------------------------
 * The WordPress file, through the real binary
 * --------------------------------------------------------------------- */

$expected = [
    '?page_id=13', '?page_id=36', '?page_id=14', '?p=14', '?pagename=contact', '?page_id=80',
    '?page_id=82', '?page_id=2', '?page_id=12', '?page_id=15', '?page_id=16', '?page_id=17', '?p=1', '?feed',
];

$dir = cv_tmp();
try {
    // The rewrite section of wp-site/public/.htaccess (lines 11-90), copied verbatim.
    $block = <<<'HTACCESS'
RewriteEngine on

# www.example.com ist dieselbe Seite, nur mit Vorsilbe. Ohne diese Regel
# liefert der Server beide Adressen als eigene Kopie aus (doppelte Inhalte für
# Suchmaschinen). Deshalb 301 auf die Hauptdomain — vor allen anderen Regeln,
# damit die Weiterleitungen der alten WordPress-Seite auch unter www greifen.
RewriteCond %{HTTP_HOST} ^www\.example\.com$ [NC]
RewriteRule ^(.*)$ https://example.com/$1 [R=301,L]

# Die Standardseite des Hosters mit ausliefern zu lassen wäre unsauber:
# Sie steht nicht unter unserem Zugangsschutz und zeigt Fremdes an.
RewriteRule ^index\.html$ index.php [L]

# ---------- Weiterleitungen von der alten WordPress-Seite ----------
#
# Die alte Seite lief mit "einfachen" Permalinks: Jede Seite war eine
# Query-String-URL (/?page_id=82), nicht ein Pfad. RewriteRule prüft aber nur
# den PFAD — und der ist hier immer leer bzw. "index.php". Die Unterscheidung
# muss deshalb ausdrücklich über %{QUERY_STRING} laufen.
#
# Das "?" am Ende jeder Zielangabe wirft den alten Query-String weg. Ohne es
# hinge "?page_id=82" auch noch an der neuen Adresse — und die Bedingung
# griffe erneut, was eine Weiterleitungsschleife ergäbe.
#
# Diese Regeln stehen VOR den Kirby-Regeln, sonst schluckt die Catch-all-Regel
# am Dateiende die Anfrage vorher. Herkunft und vollständige Liste der alten
# URLs: docs/alte-urls.md

RewriteCond %{QUERY_STRING} (^|&)page_id=13(&|$)
RewriteRule ^(index\.php)?$ /ueber-mich? [R=301,L]

RewriteCond %{QUERY_STRING} (^|&)page_id=36(&|$)
RewriteRule ^(index\.php)?$ /bilder? [R=301,L]

# Kontakt hatte drei Formen: Im alten Menü stand ausgerechnet die dritte.
RewriteCond %{QUERY_STRING} (^|&)page_id=14(&|$) [OR]
RewriteCond %{QUERY_STRING} (^|&)p=14(&|$) [OR]
RewriteCond %{QUERY_STRING} (^|&)pagename=contact(&|$)
RewriteRule ^(index\.php)?$ /kontakt? [R=301,L]

RewriteCond %{QUERY_STRING} (^|&)page_id=80(&|$)
RewriteRule ^(index\.php)?$ /datenschutz? [R=301,L]

RewriteCond %{QUERY_STRING} (^|&)page_id=82(&|$)
RewriteRule ^(index\.php)?$ /impressum? [R=301,L]

# Reste der Theme-Demo (Portfolio, Projekt-Details, Blog, Sample Page,
# "Hello world!"), die alte Startseiten-ID und die WordPress-Feeds. Nirgends
# verlinkt, aber vorhanden — auf die Startseite statt auf eine Fehlerseite.
RewriteCond %{QUERY_STRING} (^|&)page_id=(2|12|15|16|17)(&|$) [OR]
RewriteCond %{QUERY_STRING} (^|&)p=1(&|$) [OR]
RewriteCond %{QUERY_STRING} (^|&)feed=
RewriteRule ^(index\.php)?$ /? [R=301,L]

# Alte Bild-URLs (/wordpress/wp-content/uploads/…) bekommen BEWUSST keine
# Weiterleitung: Kirby vergibt eigene Media-Hashes, eine 1:1-Zuordnung gibt es
# nicht. Ein Bild auf die Startseite umzuleiten wertet Google als Soft-404 —
# ein ehrlicher 404 ist besser.

# Falls die Seite in einem Unterordner läuft, hier setzen:
# RewriteBase /

# Punkt-Dateien und -Ordner sperren (.git, .env …), außer .well-known
RewriteRule (^|/)\.(?!well-known\/) index.php [L]

# Bilder bewusst durch PHP leiten. Ohne diese Zeile liefert der Webserver die
# generierten Bildgrößen direkt aus — am Zugangsschutz vorbei. Ein Portrait
# wäre dann mit der richtigen URL ohne Passwort abrufbar.
RewriteRule ^media/(.*) index.php [L]

# Inhalte, Templates und Kirby selbst nicht direkt ausliefern
RewriteRule ^content/(.*) index.php [L]
RewriteRule ^site/(.*)    index.php [L]
RewriteRule ^kirby/(.*)   index.php [L]

# Alles Übrige an Kirby übergeben, sofern es keine echte Datei ist
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*) index.php [L]
</IfModule>
HTACCESS;
    file_put_contents($dir . '/wpsite.htaccess', $block);

    [$code, $out, $err] = cv_binary(['convert', $dir . '/wpsite.htaccess']);
    expect(1, $code, 'WordPress block via the binary → exit 1 (one skipped line)');
    expect($expected, array_keys(cv_eval($out)), 'WordPress block via the binary → the 14 keys in file order');
    expect(14, count(cv_eval($out)), 'wp-site block → 14 entries');
    expect(1, substr_count($err, 'Skipped line'), 'wp-site block → exactly one Skipped line on stderr');
    $wwwLine = 0;
    foreach (explode("\n", $block) as $i => $blockLine) {
        if (str_starts_with($blockLine, 'RewriteRule ^(.*)$')) {
            $wwwLine = $i + 1;
        }
    }
    holds(str_contains($err, 'Skipped line ' . $wwwLine . ' (' . I18n::t('reason.condition') . '): RewriteRule ^(.*)$ https://example.com/$1 [R=301,L]'), 'wp-site block → the www rule is the skipped line');
} finally {
    cv_remove($dir);
}

$real = dirname(__DIR__, 2) . '/wp-site/public/.htaccess';
if (is_file($real)) {
    [$code, $out, $err] = cv_binary(['convert', $real]);
    expect(1, $code, 'real WordPress file via the binary → exit 1');
    expect(14, count(cv_eval($out)), 'real WordPress file → 14 entries');
    expect(1, substr_count($err, 'Skipped line'), 'real WordPress file → exactly one Skipped line');
} else {
    holds(true, 'real WordPress file not present (real file check skipped)');
}

finish('ConvertCommandTest');

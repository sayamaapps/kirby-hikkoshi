<?php
/**
 * Htaccess converter, SPEC.md §8.5. Kirby-free: no vendor/, no index.php.
 *
 *   php tests/HtaccessTest.php
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../src/I18n.php';
require_once __DIR__ . '/../src/Options.php';
require_once __DIR__ . '/../src/Path.php';
require_once __DIR__ . '/../src/ConfigException.php';
require_once __DIR__ . '/../src/Validator.php';
require_once __DIR__ . '/../src/Htaccess.php';

use Hikkoshi\Htaccess;
use Hikkoshi\I18n;

I18n::setLanguage('en');

/**
 * A WordPress rewrite section: query-string redirects, the www rule and the
 * Kirby catch-all, written out here so the test needs no other file.
 */
const WORDPRESS_FIXTURE = <<<'HTACCESS'
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
# am Dateiende die Anfrage vorher.

RewriteCond %{QUERY_STRING} (^|&)page_id=13(&|$)
RewriteRule ^(index\.php)?$ /ueber-uns? [R=301,L]

RewriteCond %{QUERY_STRING} (^|&)page_id=36(&|$)
RewriteRule ^(index\.php)?$ /galerie? [R=301,L]

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
# generierten Bildgrößen direkt aus — am Zugangsschutz vorbei. Ein geschütztes Bild
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

/**
 * The 14 entries the converter must produce for the WordPress fixture, in file
 * order (SPEC §3.7).
 */
const WORDPRESS_EXPECTED = [
    '?page_id=13' => ['to' => 'ueber-uns', 'query' => 'drop'],
    '?page_id=36' => ['to' => 'galerie', 'query' => 'drop'],
    '?page_id=14' => ['to' => 'kontakt', 'query' => 'drop'],
    '?p=14' => ['to' => 'kontakt', 'query' => 'drop'],
    '?pagename=contact' => ['to' => 'kontakt', 'query' => 'drop'],
    '?page_id=80' => ['to' => 'datenschutz', 'query' => 'drop'],
    '?page_id=82' => ['to' => 'impressum', 'query' => 'drop'],
    '?page_id=2' => ['to' => 'home', 'query' => 'drop'],
    '?page_id=12' => ['to' => 'home', 'query' => 'drop'],
    '?page_id=15' => ['to' => 'home', 'query' => 'drop'],
    '?page_id=16' => ['to' => 'home', 'query' => 'drop'],
    '?page_id=17' => ['to' => 'home', 'query' => 'drop'],
    '?p=1' => ['to' => 'home', 'query' => 'drop'],
    '?feed' => ['to' => 'home', 'query' => 'drop'],
];

/** Line of the www→apex rule inside a text (the one skipped line). */
function wordpress_www_line(string $text): int
{
    foreach (explode("\n", $text) as $i => $line) {
        if (str_starts_with(trim($line), 'RewriteRule ^(.*)$')) {
            return $i + 1;
        }
    }
    return -1;
}

/** Checks one converted WordPress result. */
function check_wordpress(string $label, string $text): void
{
    $result = Htaccess::convert($text);
    expect(WORDPRESS_EXPECTED, $result['entries'], "$label: the 14 entries in file order");
    expect([], $result['partial'], "$label: no partial line");
    expect(1, count($result['skipped']), "$label: exactly one skipped line");
    $skip = $result['skipped'][0] ?? ['line' => -1, 'text' => '', 'reason' => ''];
    expect('condition', $skip['reason'], "$label: skipped reason is condition");
    holds(str_starts_with($skip['text'], 'RewriteRule ^(.*)$'), "$label: skipped text starts with RewriteRule ^(.*)\$");
    expect('RewriteRule ^(.*)$ https://example.com/$1 [R=301,L]', $skip['text'], "$label: skipped text is the www rule");
    expect(wordpress_www_line($text), $skip['line'], "$label: skipped line number");
}

check_wordpress('fixture', WORDPRESS_FIXTURE);

/* ------------------------------------------------------------------------
 * Redirect / RedirectMatch
 * --------------------------------------------------------------------- */

$r = Htaccess::convert('Redirect 301 /alt /neu');
expect(['alt' => 'neu'], $r['entries'], 'Redirect 301 → plain string');
expect([], $r['skipped'], 'Redirect 301 → nothing skipped');
expect([['line' => 1, 'text' => 'Redirect 301 /alt /neu', 'reason' => 'prefix']], $r['partial'], 'Redirect 301 → reported as partial (prefix rule in Apache)');

expect(['a' => 'b'], Htaccess::convert('Redirect permanent /a /b')['entries'], 'Redirect permanent = 301');
expect(['a' => ['to' => 'b', 'status' => 302]], Htaccess::convert('Redirect /a /b')['entries'], 'Redirect without token = 302 array');
expect(['a' => ['to' => 'b', 'status' => 302]], Htaccess::convert('Redirect 302 /a /b')['entries'], 'Redirect 302');
expect(['a' => ['to' => 'b', 'status' => 302]], Htaccess::convert('Redirect temp /a /b')['entries'], 'Redirect temp = 302');
expect(['a' => ['to' => 'b', 'status' => 307]], Htaccess::convert('Redirect 307 /a /b')['entries'], 'Redirect 307');
expect(['a' => ['to' => 'b', 'status' => 308]], Htaccess::convert('Redirect 308 /a /b')['entries'], 'Redirect 308');
expect(['alt' => 410], Htaccess::convert('Redirect gone /alt')['entries'], 'Redirect gone → 410');
expect(['alt' => 410], Htaccess::convert('Redirect 410 /alt')['entries'], 'Redirect 410 → 410');

$r = Htaccess::convert('Redirect 303 /a /b');
expect([], $r['entries'], 'Redirect 303 → no entry');
expect([['line' => 1, 'text' => 'Redirect 303 /a /b', 'reason' => 'status']], $r['skipped'], 'Redirect 303 → skipped status');

$r = Htaccess::convert('Redirect seeother /a /b');
expect([['line' => 1, 'text' => 'Redirect seeother /a /b', 'reason' => 'status']], $r['skipped'], 'Redirect seeother → skipped status');

expect(['a' => ['to' => 'b', 'query' => 'drop']], Htaccess::convert('Redirect 301 /a /b?')['entries'], 'trailing ? = query drop');
expect(['a' => 'home'], Htaccess::convert('Redirect 301 /a /')['entries'], 'target / = home');
expect(['a' => 'https://x.example/p'], Htaccess::convert('Redirect 301 /a https://x.example/p')['entries'], 'external target verbatim');
expect(['a' => ['to' => 'https://x.example/p', 'query' => 'drop']], Htaccess::convert('Redirect 301 /a https://x.example/p?')['entries'], 'external target with ? = drop');

$r = Htaccess::convert('Redirect 301 /a b');
expect([['line' => 1, 'text' => 'Redirect 301 /a b', 'reason' => 'target']], $r['skipped'], 'target without slash → target');
expect([], $r['entries'], 'target without slash → no entry');

$r = Htaccess::convert('Redirect 301 a /b');
expect([['line' => 1, 'text' => 'Redirect 301 a /b', 'reason' => 'pattern']], $r['skipped'], 'old path without slash → pattern');

expect(['alt' => 'neu'], Htaccess::convert('Redirect 301 /alt/ /neu/')['entries'], 'slashes around paths are removed');

$r = Htaccess::convert('Redirect 301 /a /b$1');
expect([['line' => 1, 'text' => 'Redirect 301 /a /b$1', 'reason' => 'target']], $r['skipped'], '$1 in target → target');

expect(['alt' => 'neu'], Htaccess::convert('RedirectMatch 301 ^/alt/?$ /neu')['entries'], 'RedirectMatch literal → key');
expect(['alt.html' => 'neu'], Htaccess::convert('RedirectMatch 301 ^/alt\.html$ /neu')['entries'], 'RedirectMatch escaped dot');

$r = Htaccess::convert('RedirectMatch 301 ^/blog/(.*)$ /news');
expect([['line' => 1, 'text' => 'RedirectMatch 301 ^/blog/(.*)$ /news', 'reason' => 'pattern']], $r['skipped'], 'RedirectMatch with group → pattern');
$r = Htaccess::convert('RedirectMatch 301 ^/a|b$ /x');
expect('pattern', $r['skipped'][0]['reason'] ?? '', 'RedirectMatch alternation → pattern');

expect(['alt' => ['to' => 'neu', 'status' => 302]], Htaccess::convert('RedirectMatch ^/alt/?$ /neu')['entries'], 'RedirectMatch without status: the regex comes first, no token = 302');
expect(['alt' => ['to' => 'neu', 'status' => 302]], Htaccess::convert('RedirectMatch temp ^/alt$ /neu')['entries'], 'RedirectMatch with status word');
expect(['alt' => 410], Htaccess::convert('RedirectMatch gone ^/alt$')['entries'], 'RedirectMatch gone without target → 410');
$r = Htaccess::convert('RedirectMatch 303 ^/alt$ /neu');
expect([['line' => 1, 'text' => 'RedirectMatch 303 ^/alt$ /neu', 'reason' => 'status']], $r['skipped'], 'RedirectMatch 303 → skipped status');

/* Partial: converted for the exact path, but Apache matched the paths below as well */

foreach ([
    'Redirect permanent /a /b' => 'Redirect permanent',
    'Redirect /a /b' => 'Redirect without token (302)',
    'Redirect temp /a /b' => 'Redirect temp',
    'Redirect gone /alt' => 'Redirect gone',
    'Redirect 301 /alt/ /neu/' => 'Redirect with slashes',
    'RedirectPermanent /a /b' => 'RedirectPermanent',
    'RedirectTemp /a /b' => 'RedirectTemp',
    'RedirectMatch 301 ^/alt /neu' => 'RedirectMatch ^ without $',
    'RedirectMatch 301 ^/alt/? /neu' => 'RedirectMatch ^ with /? but without $',
    'RewriteRule ^alt /neu [R=301]' => 'RewriteRule ^ without $',
    'RewriteRule ^alt/ /neu [R=301]' => 'RewriteRule ^alt/ without $',
    'RewriteRule ^alt - [G]' => 'RewriteRule G ^ without $',
] as $rule => $label) {
    $r = Htaccess::convert($rule);
    expect(1, count($r['entries']), "$label → one entry");
    expect([], $r['skipped'], "$label → nothing skipped");
    expect([['line' => 1, 'text' => $rule, 'reason' => 'prefix']], $r['partial'], "$label → partial prefix");
}
expect(['alt' => 'neu'], Htaccess::convert('RewriteRule ^alt/ /neu [R=301]')['entries'], 'RewriteRule ^alt/ → entry alt');

foreach ([
    'RedirectMatch 301 ^/alt$ /neu',
    'RedirectMatch 301 ^/alt/?$ /neu',
    'RedirectMatch 301 ^/alt\.html$ /neu',
    'RewriteRule ^alt$ /neu [R=301]',
    'RewriteRule ^alt/?$ /neu [R=301]',
    'RewriteRule ^alt\/?$ /neu [R=301]',
    'RewriteRule ^alt$ - [G]',
    'Redirect 303 /a /b',
    'Redirect 301 /a b',
    'Redirect 301 a /b',
] as $rule) {
    expect([], Htaccess::convert($rule)['partial'], "$rule → no partial note");
}
$r = Htaccess::convert("RewriteCond %{QUERY_STRING} ^p=1\$\nRewriteRule ^(index\\.php)?\$ /x? [R=301]");
expect([], $r['partial'], 'WordPress home form is anchored → no partial note');

foreach ([
    'RedirectMatch 301 /alt /neu',
    'RedirectMatch 301 alt$ /neu',
    'RewriteRule alt /neu [R=301]',
    'RewriteRule alt$ /neu [R]',
    'RewriteRule /alt$ /neu [R=301]',
] as $rule) {
    $r = Htaccess::convert($rule);
    expect([], $r['entries'], "$rule → no entry");
    expect([['line' => 1, 'text' => $rule, 'reason' => 'pattern']], $r['skipped'], "$rule → skipped pattern (no ^: may match anywhere)");
    expect([], $r['partial'], "$rule → no partial note");
}

$r = Htaccess::convert("Redirect 301 /alt /neu\nRedirect 301 /alt /other\nRedirectMatch 301 ^/alt /third");
expect(['alt' => 'neu'], $r['entries'], 'duplicates → first wins');
expect([['line' => 2, 'text' => 'Redirect 301 /alt /other', 'reason' => 'duplicate'], ['line' => 3, 'text' => 'RedirectMatch 301 ^/alt /third', 'reason' => 'duplicate']], $r['skipped'], 'partial duplicates → skipped duplicate');
expect([['line' => 1, 'text' => 'Redirect 301 /alt /neu', 'reason' => 'prefix']], $r['partial'], 'partial duplicates → no partial note');

expect(['a' => 'b', 'c' => ['to' => 'd', 'status' => 302]], Htaccess::convert("RedirectPermanent /a /b\nRedirectTemp /c /d")['entries'], 'RedirectPermanent = 301, RedirectTemp = 302');
$r = Htaccess::convert('redirectpermanent /a');
expect([['line' => 1, 'text' => 'redirectpermanent /a', 'reason' => 'target']], $r['skipped'], 'RedirectPermanent without target → skipped target (case-insensitive name)');
$r = Htaccess::convert('Redirect 301 /alt //x.example/p');
expect([['line' => 1, 'text' => 'Redirect 301 /alt //x.example/p', 'reason' => 'target']], $r['skipped'], 'protocol-relative target → skipped target, not the page id x.example/p');
expect([], $r['entries'], 'protocol-relative target → no entry');

/* ------------------------------------------------------------------------
 * RewriteRule without conditions
 * --------------------------------------------------------------------- */

expect(['alt' => 'neu'], Htaccess::convert('RewriteRule ^alt/?$ /neu [R=301,L]')['entries'], 'RewriteRule R=301');
expect(['alt' => ['to' => 'neu', 'status' => 302]], Htaccess::convert('RewriteRule ^alt/?$ /neu [R,L]')['entries'], 'RewriteRule [R,L] = 302 array');
$r = Htaccess::convert('RewriteRule ^alt/?$ /neu [L]');
expect([[], []], [$r['entries'], $r['skipped']], 'RewriteRule without R is ignored, not skipped');
$r = Htaccess::convert('RewriteRule ^(.*)$ index.php [L]');
expect([[], []], [$r['entries'], $r['skipped']], 'internal rewrite to index.php is ignored');
expect(['alt' => 410], Htaccess::convert('RewriteRule ^alt$ - [R=410]')['entries'], 'RewriteRule - with R=410 = gone');
expect(['alt' => 410, 'alt2' => 410], Htaccess::convert("RewriteRule ^alt$ - [G]\nRewriteRule ^alt2/?$ - [NC,gone,L]")['entries'], 'G / gone flag = 410');
expect(['?p=3' => 410], Htaccess::convert("RewriteCond %{QUERY_STRING} ^p=3$\nRewriteRule ^$ - [G]")['entries'], 'G flag with a query condition');
expect(['alt' => 'neu'], Htaccess::convert('RewriteRule ^alt$ /neu [redirect=301,L]')['entries'], 'long flag name redirect=301 = R=301');
$r = Htaccess::convert('RewriteRule ^alt$ https://x.example/neu [L]');
expect([['line' => 1, 'text' => 'RewriteRule ^alt$ https://x.example/neu [L]', 'reason' => 'target']], $r['skipped'], 'absolute URL without R (Apache redirects) → skipped target, not silent');
expect([[], []], [Htaccess::convert('RewriteRule ^alt$ /intern.php [L]')['entries'], Htaccess::convert('RewriteRule ^alt$ /intern.php [L]')['skipped']], 'path target without R stays an ignored internal rewrite');
$r = Htaccess::convert('RewriteRule ^alt$ - [R=301]');
expect([['line' => 1, 'text' => 'RewriteRule ^alt$ - [R=301]', 'reason' => 'target']], $r['skipped'], '- with R=301 → target');
$r = Htaccess::convert('RewriteRule ^alt$ [R=301,L]');
expect([['line' => 1, 'text' => 'RewriteRule ^alt$ [R=301,L]', 'reason' => 'target']], $r['skipped'], 'RewriteRule without target but with R flag → skipped target, not silent');
expect([], $r['entries'], 'RewriteRule without target → no entry');
$r = Htaccess::convert('RewriteRule ^alt$ /neu/$1 [R=301]');
expect([['line' => 1, 'text' => 'RewriteRule ^alt$ /neu/$1 [R=301]', 'reason' => 'target']], $r['skipped'], '$1 in RewriteRule target → target');
$r = Htaccess::convert('RewriteRule ^(index\.php)?$ /neu [R=301]');
expect([['line' => 1, 'text' => 'RewriteRule ^(index\.php)?$ /neu [R=301]', 'reason' => 'pattern']], $r['skipped'], 'home without conditions → pattern');
expect(['alt' => 'neu'], Htaccess::convert('RewriteRule ^alt$ /neu [NC,R=301,L]')['entries'], 'flag order irrelevant');
expect(['alt' => ['to' => 'neu', 'query' => 'drop']], Htaccess::convert('RewriteRule ^alt$ /neu [R=301,QSD,L]')['entries'], 'QSD flag → query drop');

/* RewriteRule flags: QSD discards the query, flags the list cannot express are skipped */

expect(['old' => ['to' => 'produkte', 'query' => 'drop']], Htaccess::convert('RewriteRule ^old$ /produkte [R=301,QSD,L]')['entries'], 'QSD → query drop');
expect(['old' => ['to' => 'produkte', 'query' => 'drop']], Htaccess::convert('RewriteRule ^old$ /produkte [R=301,qsdiscard,L]')['entries'], 'qsdiscard (lowercase, long name) → query drop');
expect(['old' => ['to' => 'produkte', 'query' => 'drop']], Htaccess::convert('RewriteRule ^old$ /produkte? [R=301,QSD,L]')['entries'], 'QSD plus trailing ? → one drop');
expect(['old' => ['to' => 'produkte', 'status' => 302, 'query' => 'drop']], Htaccess::convert('RewriteRule ^old$ /produkte [R,QSD]')['entries'], 'R with QSD → 302 array with drop');
expect(['alt' => 'neu'], Htaccess::convert('RewriteRule ^alt$ /neu? [R=301,QSA]')['entries'], 'QSA with a trailing ? keeps the query (plain string)');
expect(['alt' => 'neu'], Htaccess::convert('RewriteRule ^alt$ /neu? [R=301,qsappend]')['entries'], 'qsappend (long name) with a trailing ? keeps the query');
expect(['alt' => ['to' => 'neu', 'query' => 'drop']], Htaccess::convert('RewriteRule ^alt$ /neu? [R=301,QSA,QSD]')['entries'], 'QSA plus QSD with a trailing ? → drop (QSD always drops)');
expect(['alt' => ['to' => 'neu', 'query' => 'drop']], Htaccess::convert('RewriteRule ^alt$ /neu [R=301,QSA,QSD]')['entries'], 'QSA plus QSD without ? → drop');
expect(['alt' => 'neu'], Htaccess::convert('RewriteRule ^alt$ /neu [R=301,QSA]')['entries'], 'QSA without a trailing ? → plain string');
expect(['alt' => ['to' => 'neu', 'query' => 'drop']], Htaccess::convert('RewriteRule ^alt$ /neu? [R=301]')['entries'], 'trailing ? without QSA → drop');
foreach (['RewriteRule ^alt\/?$ /neu [R=301,L]', 'RedirectMatch 301 ^/alt\/?$ /neu'] as $rule) {
    $r = Htaccess::convert($rule);
    expect(['alt' => 'neu'], $r['entries'], "$rule → entry alt");
    expect([[], []], [$r['skipped'], $r['partial']], "$rule → nothing skipped, no partial note");
}
expect(['old' => 410], Htaccess::convert('RewriteRule ^old$ - [G,QSD]')['entries'], '410 rule with QSD → 410');

foreach ([
    'RewriteRule ^old$ /produkte [R=301,CO=name:value:example.com]' => 'cookie flag CO',
    'RewriteRule ^old$ /produkte [R=301,S=1]' => 'skip flag S',
    'RewriteRule ^old$ /produkte [R=301,XYZ]' => 'unknown flag XYZ',
    'RewriteRule ^old$ /produkte [R=301,P]' => 'proxy flag P',
    'RewriteRule ^old$ - [G,T=text/html]' => 'type flag T on a 410 rule',
] as $rule => $label) {
    $r = Htaccess::convert($rule);
    expect([], $r['entries'], "$label → no entry");
    expect([['line' => 1, 'text' => $rule, 'reason' => 'flag']], $r['skipped'], "$label → skipped flag");
}

expect(['old' => 'produkte'], Htaccess::convert('RewriteRule ^old$ /produkte [NC,R=301,NE,L,END,QSA,E=VAR:1]')['entries'], 'ignorable flags (NC, NE, L, END, QSA, E) → converted');
expect([[], []], [Htaccess::convert('RewriteRule ^old$ /intern.php [L,CO=a:b:c]')['entries'], Htaccess::convert('RewriteRule ^old$ /intern.php [L,CO=a:b:c]')['skipped']], 'unsupported flag without R/G → still an ignored internal rewrite');

$r = Htaccess::convert('RewriteRule ^old$ /produkte?x=1 [R=301]');
expect([[], 'target'], [$r['entries'], $r['skipped'][0]['reason'] ?? ''], 'target with its own query → target (the Validator accepts no ? in a page id)');
expect(['alt' => ['to' => 'neu', 'status' => 307]], Htaccess::convert('RewriteRule ^alt$ /neu [R=307,L]')['entries'], 'R=307');

/* An external target with a query of its own: Apache replaces the request's query unless QSA appends it */
$r = Htaccess::convert('RewriteRule ^replace$ https://target.example/new?mode=fixed [R=301,L]');
expect(['replace' => ['to' => 'https://target.example/new?mode=fixed', 'query' => 'drop']], $r['entries'], 'external target with its own query, no QSA → query drop');
expect([[], []], [$r['skipped'], $r['partial']], 'external target with its own query → nothing skipped, no partial note');
expect(['replace' => ['to' => 'https://target.example/new?mode=fixed', 'status' => 302, 'query' => 'drop']],
    Htaccess::convert('RewriteRule ^replace$ https://target.example/new?mode=fixed [R,L]')['entries'],
    'external target with its own query, R (302), no QSA → 302 array with drop');
expect(['append' => 'https://target.example/new?mode=fixed'],
    Htaccess::convert('RewriteRule ^append$ https://target.example/new?mode=fixed [R=301,QSA,L]')['entries'],
    'external target with its own query and QSA → plain string (request query appended)');
expect(['discard' => ['to' => 'https://target.example/new?mode=fixed', 'query' => 'drop']],
    Htaccess::convert('RewriteRule ^discard$ https://target.example/new?mode=fixed [R=301,QSD,L]')['entries'],
    'external target with its own query and QSD → drop (unchanged)');
expect(['plain' => 'https://target.example/new'],
    Htaccess::convert('RewriteRule ^plain$ https://target.example/new [R=301,L]')['entries'],
    'external target without a query → plain string (unchanged)');
expect(['alt' => ['to' => 'https://target.example/new', 'query' => 'drop']],
    Htaccess::convert('RewriteRule ^alt$ https://target.example/new? [R=301,L]')['entries'],
    'external target with a trailing ? → drop (unchanged)');

/* ------------------------------------------------------------------------
 * RewriteCond (%{QUERY_STRING}) groups
 * --------------------------------------------------------------------- */

$rule = "RewriteRule ^(index\\.php)?$ /x? [R=301,L]";
expect(['?page_id=13' => ['to' => 'x', 'query' => 'drop']],
    Htaccess::convert("RewriteCond %{QUERY_STRING} (^|&)page_id=13(&|\$)\n$rule")['entries'],
    'single QUERY_STRING cond → key');

$r = Htaccess::convert("RewriteCond %{QUERY_STRING} (^|&)page_id=14(&|\$) [OR]\n"
    . "RewriteCond %{QUERY_STRING} (^|&)p=14(&|\$) [OR]\n"
    . "RewriteCond %{QUERY_STRING} (^|&)pagename=contact(&|\$)\n"
    . "RewriteRule ^(index\\.php)?\$ /kontakt? [R=301,L]");
expect(['?page_id=14', '?p=14', '?pagename=contact'], array_keys($r['entries']), 'three [OR] conds → three keys');
expect(['to' => 'kontakt', 'query' => 'drop'], $r['entries']['?p=14'] ?? null, 'three keys share the value');

expect(['?page_id=2', '?page_id=12'],
    array_keys(Htaccess::convert("RewriteCond %{QUERY_STRING} (^|&)page_id=(2|12)(&|\$)\n$rule")['entries']),
    'alternation → one key per alternative');

expect(['?feed' => ['to' => 'home', 'query' => 'drop']],
    Htaccess::convert("RewriteCond %{QUERY_STRING} (^|&)feed=\nRewriteRule ^(index\\.php)?\$ /? [R=301,L]")['entries'],
    'feed= → bare name key');

expect(['?p=1'], array_keys(Htaccess::convert("RewriteCond %{QUERY_STRING} ^p=1\$\n$rule")['entries']), 'anchored ^p=1$ → ?p=1');
expect(['?p=1'], array_keys(Htaccess::convert("RewriteCond %{QUERY_STRING} p=1\n$rule")['entries']), 'unanchored p=1 → ?p=1');

expect(['?a=1&b=2'], array_keys(Htaccess::convert("RewriteCond %{QUERY_STRING} (^|&)a=1(&|\$)\nRewriteCond %{QUERY_STRING} (^|&)b=2(&|\$)\n$rule")['entries']),
    'two conds without OR are ANDed');

expect(['?a=1&c=3', '?b=2&c=3'],
    array_keys(Htaccess::convert("RewriteCond %{QUERY_STRING} (^|&)a=1(&|\$) [OR]\nRewriteCond %{QUERY_STRING} (^|&)b=2(&|\$)\nRewriteCond %{QUERY_STRING} (^|&)c=3(&|\$)\n$rule")['entries']),
    '(A OR B) AND C gives two keys');

$r = Htaccess::convert("RewriteCond %{HTTP_HOST} ^www\\.x\\.de\$\nRewriteRule ^(.*)\$ https://x.de/\$1 [R=301,L]\nRewriteRule ^alt\$ /neu [R=301]");
expect(['alt' => 'neu'], $r['entries'], 'after a skipped group the next rule still converts');
expect([['line' => 2, 'text' => 'RewriteRule ^(.*)$ https://x.de/$1 [R=301,L]', 'reason' => 'condition']], $r['skipped'], 'HTTP_HOST cond → condition, reported with the rule line');

$r = Htaccess::convert("RewriteCond %{QUERY_STRING} page_id=.*\n$rule");
expect([['line' => 2, 'text' => $rule, 'reason' => 'condition']], $r['skipped'], 'regex cond page_id=.* → condition');

expect(['?page_id=13' => ['to' => 'x', 'query' => 'drop']],
    Htaccess::convert("RewriteCond %{QUERY_STRING} (^|&)page_id=13(&|\$) [NC]\n$rule")['entries'],
    '[NC] on a cond accepted');

$r = Htaccess::convert("RewriteCond %{QUERY_STRING} (^|&)page_id=13(&|\$)\nRewriteRule ^(index\\.php)?\$ /x [L]\nRewriteRule ^(index\\.php)?\$ /y? [R=301]");
expect([], $r['entries'], 'cond followed by a rule without R resets the group');
expect([['line' => 3, 'text' => 'RewriteRule ^(index\.php)?$ /y? [R=301]', 'reason' => 'pattern']], $r['skipped'], 'the next rule has no conditions any more');

/* ------------------------------------------------------------------------
 * Duplicates, line handling, ignored lines
 * --------------------------------------------------------------------- */

$r = Htaccess::convert("Redirect 301 /alt /a\nRedirect 301 /alt /b");
expect(['alt' => 'a'], $r['entries'], 'duplicate: first occurrence kept');
expect([['line' => 2, 'text' => 'Redirect 301 /alt /b', 'reason' => 'duplicate']], $r['skipped'], 'duplicate: second skipped');

$r = Htaccess::convert("# comment\n\n<IfModule mod_alias.c>\nredirect 301 /a /b\n</IfModule>\n");
expect(['a' => 'b'], $r['entries'], 'comments, blanks, IfModule ignored; lower-case directive');
expect([], $r['skipped'], 'nothing skipped in that block');

$r = Htaccess::convert("Redirect 303 \\\n/a /b\nRedirect 301 /c /d");
expect([['line' => 1, 'text' => 'Redirect 303 /a /b', 'reason' => 'status']], $r['skipped'], 'backslash continuation joins lines; skipped line is the first');
expect(['c' => 'd'], $r['entries'], 'line after a continuation is numbered normally');

$r = Htaccess::convert("Redirect 301 \\\n/a /b");
expect(['a' => 'b'], $r['entries'], 'continuation joins two lines');

$r = Htaccess::convert("# c\n\nRedirect 303 /a /b");
expect(3, $r['skipped'][0]['line'] ?? -1, 'line numbers are 1-based and point at the directive');

$r = Htaccess::convert("<IfModule mod_rewrite.c>\nRewriteEngine on\nHeader set X y\n</IfModule>");
expect([[], []], [$r['entries'], $r['skipped']], 'unrelated directives are ignored');

/* ------------------------------------------------------------------------
 * render()
 * --------------------------------------------------------------------- */

$entries = [
    '?page_id=13' => ['to' => 'ueber-uns', 'query' => 'drop'],
    'alt' => 410,
    'produkte' => 'produkte',
    'x' => ['to' => 'y', 'status' => 302],
];
expect("<?php\n/**\n * Header\n */\nreturn [\n"
    . "    '?page_id=13' => ['to' => 'ueber-uns', 'query' => 'drop'],\n"
    . "    'alt' => 410,\n"
    . "    'produkte' => 'produkte',\n"
    . "    'x' => ['to' => 'y', 'status' => 302],\n"
    . "];\n", Htaccess::render($entries, 'Header'), 'render with header, exact text');

expect("<?php\n\nreturn [\n];\n", Htaccess::render([]), 'render of an empty list');

expect("<?php\n/**\n * First line\n * Second line\n */\nreturn [\n];\n",
    Htaccess::render([], "First line\nSecond line"), 'two header lines → two " * " lines');

// eval() is deliberate here: it runs only the PHP that render() just produced
// from our own arrays, to prove the file returns the same list (SPEC §8.5).
$php = Htaccess::render($entries, "Header");
expect($entries, eval(substr($php, strlen('<?php'))), 'rendered code evaluates back to the same array');

// The header is a doc comment: a star-slash in it (a directory `a*` in the
// path of `hikkoshi convert`) must not end it, or the rest runs as code.
$php = Htaccess::render(['a' => 'b'], "From a*/ \$GLOBALS['hikkoshiInjected'] = 1; __halt_compiler();\x1b[2J.htaccess");
expect("<?php\n/**\n * From a*\\/ \$GLOBALS['hikkoshiInjected'] = 1; __halt_compiler();[2J.htaccess\n */\nreturn [\n    'a' => 'b',\n];\n",
    $php, 'render: star-slash broken up, control character dropped');
$significant = array_values(array_filter(token_get_all($php), static fn ($t) => !is_array($t) || $t[0] !== T_WHITESPACE));
expect([T_OPEN_TAG, T_DOC_COMMENT, T_RETURN], array_map(static fn ($t) => is_array($t) ? $t[0] : $t, array_slice($significant, 0, 3)),
    'render: the whole header is one doc comment, the code starts with return');
expect(['a' => 'b'], eval(substr($php, strlen('<?php'))), 'render: the hostile header file still returns the list');
holds(!isset($GLOBALS['hikkoshiInjected']), 'render: nothing from the header ran');
expect("<?php\n/**\n * a\n * b\n */\nreturn [\n];\n", Htaccess::render([], "a\r\nb"), 'render: CR of a CRLF header is dropped');

$converted = Htaccess::convert(WORDPRESS_FIXTURE)['entries'];
expect($converted, eval(substr(Htaccess::render($converted, 'x'), strlen('<?php'))), 'WordPress entries round-trip through render');

finish('HtaccessTest');

<?php
/**
 * The plugin with real Kirby (SPEC.md §8.3), in throwaway site directories,
 * rendered in-process (no PHP server, no sockets, no outbound HTTP). Port of
 * the pre-1.0 HTTP test. Sections:
 *  1. registered, off by default, translations, hikkoshi_option();
 *  2. the pre-1.0 list in weiterleitungen.php: targets, GET/HEAD, slash, query,
 *     drafts, POST, plain pages (changed: uppercase requests redirect now);
 *  3. without a list;
 *  4. broken lists answer 500 on every request; a chain is a logged warning now;
 *  5. new forms: file precedence, option forms, 410, per-entry status, query
 *     modes, external targets;
 *  6. query conditions;
 *  7. suffix and umlaut matching on real requests;
 *  8. site and page methods;
 *  9. option validation.
 *
 * Kirby loads the plugin through the symlink hikkoshi_site() creates, so no
 * src file is required here.
 *
 *   php tests/KirbyTest.php
 */

require_once __DIR__ . '/helpers.php';

use Hikkoshi\ConfigException;
use Kirby\Cms\App;

/** Runs $fn with a fresh throwaway site directory, removes it afterwards. */
function withSite(callable $fn): void
{
    $tmp = hikkoshi_site();
    try {
        $fn($tmp);
    } finally {
        hikkoshi_remove_tree($tmp);
    }
}

/** Runs $fn with the php error_log written to a file in $tmp, then restores the setting. */
function withErrorLog(string $tmp, callable $fn): string
{
    $previous = ini_get('error_log');
    $file = $tmp . '/php-error.log';
    ini_set('error_log', $file);
    try {
        $fn();
    } finally {
        ini_set('error_log', (string) $previous);
    }
    return is_file($file) ? (string) file_get_contents($file) : '';
}

/** Options with the master switch on. */
function ktOn(array $extra = []): array
{
    return array_merge(['hikkoshi.active' => true], $extra);
}

/** A request with a query, for hikkoshi_kirby(). */
function ktRequest(array $query): array
{
    return ['method' => 'GET', 'query' => $query];
}

/** The pre-1.0 list of the HTTP test. */
function ktPreList(): array
{
    return [
        'menue' => 'speisekarte',
        'produkt-c.html' => 'produkte',
        'tageskarte/karte-1.pdf' => 'speisekarte',
        'alt/tief/seite' => 'leistungen/beratung',
        'index.html' => 'home',
        'produkt-b.html' => 'produkt-b',   // target is a draft
        'team' => 'produkte',                        // a real page sits under the old path
    ];
}

/** Target paths of the pre-1.0 list render 200. */
const KT_TARGET_PATHS = ['/speisekarte', '/produkte', '/leistungen/beratung', '/'];

/* --- 1. registered, off by default, translations, option helper ----------- */

withSite(function (string $tmp): void {
    hikkoshi_write_config($tmp, ktPreList(), 'weiterleitungen.php');

    $off = hikkoshi_kirby($tmp);
    holds(array_key_exists('sayamaapps/hikkoshi', $off->plugins()), 'plugin sayamaapps/hikkoshi is registered');
    expect([], $off->site()->hikkoshiRedirects(), 'off by default: hikkoshiRedirects() is []');
    expect(null, $off->site()->hikkoshiResolve('/menue'), 'off by default: hikkoshiResolve() is null');
    expect([], $off->page('produkte')->hikkoshiSources(), 'off by default: page hikkoshiSources() is []');
    [$status, , $location] = hikkoshi_render($off, '/menue');
    expect(404, $status, 'off by default: /menue is 404 even with a list file');
    expect(null, $location, 'off by default: /menue has no Location');

    $yes = hikkoshi_kirby($tmp, ['hikkoshi.active' => 'yes']);
    expect([], $yes->site()->hikkoshiRedirects(), '"yes" does not switch Hikkoshi on');
    expect(404, hikkoshi_render($yes, '/menue')[0], '"yes": /menue is still 404');

    $on = hikkoshi_kirby($tmp, ktOn());
    holds(array_key_exists('sayamaapps/hikkoshi', $on->plugins()), 'plugin is registered with the switch on, too');
    expect('Page removed', $on->translation('en')->get('hikkoshi.gone.title'), 'translation hikkoshi.gone.title in English');
    expect('Seite entfernt', $on->translation('de')->get('hikkoshi.gone.title'), 'translation hikkoshi.gone.title in German');
    holds(function_exists('hikkoshi_option'), 'hikkoshi_option() exists');
    expect(true, hikkoshi_option('active'), 'hikkoshi_option() reads hikkoshi.active');
    expect('fallback', hikkoshi_option('gibtsnicht', 'fallback'), 'hikkoshi_option() returns the fallback for an unset key');
});

/* --- 2. the pre-1.0 list, legacy file name ------------------------------- */

withSite(function (string $tmp): void {
    hikkoshi_write_config($tmp, ktPreList(), 'weiterleitungen.php');
    $kirby = hikkoshi_kirby($tmp, ktOn());

    $expected = [
        '/menue' => 'http://example.invalid/speisekarte',
        '/produkt-c.html' => 'http://example.invalid/produkte',
        '/tageskarte/karte-1.pdf' => 'http://example.invalid/speisekarte',
        '/alt/tief/seite' => 'http://example.invalid/leistungen/beratung',
        '/index.html' => 'http://example.invalid',
    ];
    foreach ($expected as $old => $location) {
        foreach (['', '/'] as $end) {
            foreach (['GET', 'HEAD'] as $method) {
                [$status, , $actual] = hikkoshi_render($kirby, $old . $end, $method);
                expect(301, $status, "$method $old$end is 301");
                expect($location, $actual, "$method $old$end location");
            }
        }
    }

    foreach (KT_TARGET_PATHS as $path) {
        expect(200, hikkoshi_render($kirby, $path)[0], "target $path renders 200");
    }

    // Query kept: the QR code on the old flyer carries utm parameters.
    $withQuery = hikkoshi_kirby($tmp, ktOn(), ktRequest(['utm_source' => 'qr', 'probe' => ['1']]));
    [$status, , $location] = hikkoshi_render($withQuery, '/menue');
    expect(301, $status, 'with query: 301');
    $parts = parse_url((string) $location);
    parse_str($parts['query'] ?? '', $query);
    expect('/speisekarte', $parts['path'] ?? null, 'with query: path of the location');
    expect(['utm_source' => 'qr', 'probe' => ['1']], $query, 'with query: the whole query is passed on');

    $withSlash = hikkoshi_kirby($tmp, ktOn(), ktRequest(['utm_source' => 'qr']));
    expect('http://example.invalid/speisekarte?utm_source=qr', hikkoshi_render($withSlash, '/menue/')[2], 'with query and trailing slash');

    // CHANGED: Kirby compared routes case-sensitively and answered 404 for
    // uppercase; the normalised match redirects now (pre-1.0: 404).
    foreach (['/MENUE' => 'http://example.invalid/speisekarte', '/Menue' => 'http://example.invalid/speisekarte',
              '/Produkt-c.html' => 'http://example.invalid/produkte'] as $old => $location) {
        [$status, , $actual] = hikkoshi_render($kirby, $old);
        expect(301, $status, "$old is 301 now (pre-1.0: 404)");
        expect($location, $actual, "$old location");
    }

    // A draft as target: no redirect, the normal treatment (404).
    [$status, , $location] = hikkoshi_render($kirby, '/produkt-b.html');
    expect(404, $status, 'draft target: 404');
    expect(null, $location, 'draft target: no Location');

    // Plugin routes come before the page lookup: the page team is hidden.
    [$status, , $location] = hikkoshi_render($kirby, '/team');
    expect(301, $status, 'old path under a real page: 301');
    expect('http://example.invalid/produkte', $location, 'old path under a real page: location');

    // Other methods are untouched: a POST to an old form address stays a POST.
    holds(hikkoshi_render($kirby, '/menue', 'POST')[0] !== 301, 'POST /menue is not redirected');

    // Control: real pages and unknown paths behave as before.
    [$status, $body] = hikkoshi_render($kirby, '/speisekarte');
    expect(200, $status, 'real page speisekarte: 200');
    expect('Page speisekarte', $body, 'real page speisekarte renders its template');
    [$status, , $location] = hikkoshi_render($kirby, '/gibt-es-nicht');
    expect(404, $status, 'unknown path: 404');
    expect(null, $location, 'unknown path: no Location');
    expect(404, hikkoshi_render($kirby, '/menue/unterseite')[0], 'sub-path of an old path is not taken along');
});

/* --- 3. without a list --------------------------------------------------- */

withSite(function (string $tmp): void {
    hikkoshi_write_config($tmp, null);
    $kirby = hikkoshi_kirby($tmp, ktOn());
    expect(404, hikkoshi_render($kirby, '/menue')[0], 'without a list file: /menue is 404');
    expect(200, hikkoshi_render($kirby, '/')[0], 'without a list file: the home page is 200');
    expect([], $kirby->site()->hikkoshiRedirects(), 'without a list file: hikkoshiRedirects() is []');
});

/* --- 4. broken lists: 500 on every request; chains are a warning ---------- */

withSite(function (string $tmp): void {
    $broken = [
        'loop' => ['a' => 'b', 'b' => 'a'],
        'pattern' => ['(:all)' => 'produkte'],
        'foreign domain' => ['menue' => 'https://example.org'],
        'duplicate' => ['menue' => 'a', 'Menue' => 'b'],
    ];
    foreach ($broken as $case => $list) {
        hikkoshi_write_config($tmp, $list);
        // Routes are built with the instance, so a broken list throws while the
        // App is created: the web server answers 500 for every request.
        $thrown = null;
        try {
            hikkoshi_kirby($tmp, ktOn());
        } catch (ConfigException $e) {
            $thrown = $e;
        }
        holds($thrown instanceof ConfigException, "$case: the App cannot be built, every request is a 500");
    }

    // CHANGED: a chain no longer breaks the site. Two hops work, the warning
    // goes to the error log (pre-1.0: every request 500).
    hikkoshi_write_config($tmp, ['menue' => 'speisekarte', 'speisekarte' => 'produkte']);
    $chain = null;
    $logged = withErrorLog($tmp, function () use ($tmp, &$chain): void {
        $chain = hikkoshi_kirby($tmp, ktOn());
    });
    holds(str_contains($logged, 'Redirect chain'), 'chain: the warning is in the error log');
    holds(str_contains($logged, 'menue → speisekarte → produkte'), 'chain: the warning names the chain');
    expect(200, hikkoshi_render($chain, '/')[0], 'chain: the home page is 200 now');
    expect(301, hikkoshi_render($chain, '/menue')[0], 'chain: /menue is 301');
    expect('http://example.invalid/speisekarte', hikkoshi_render($chain, '/menue')[2], 'chain: /menue goes to speisekarte (two hops)');

    // After repairing the list a new instance answers 301 again.
    hikkoshi_write_config($tmp, ['menue' => 'speisekarte']);
    expect(301, hikkoshi_render(hikkoshi_kirby($tmp, ktOn()), '/menue')[0], 'after repair: /menue is 301 again');
});

/* --- 5. new forms -------------------------------------------------------- */

withSite(function (string $tmp): void {
    // hikkoshi.php beats weiterleitungen.php when both exist.
    hikkoshi_write_config($tmp, ['menue' => 'produkte'], 'weiterleitungen.php');
    hikkoshi_write_config($tmp, ['menue' => 'speisekarte']);
    expect('http://example.invalid/speisekarte', hikkoshi_render(hikkoshi_kirby($tmp, ktOn()), '/menue')[2],
        'hikkoshi.php beats weiterleitungen.php');
    hikkoshi_write_config($tmp, null);
    expect('http://example.invalid/produkte', hikkoshi_render(hikkoshi_kirby($tmp, ktOn()), '/menue')[2],
        'without hikkoshi.php the legacy file is used');

    // The option hikkoshi.redirects, as an array and as a Closure, beats the files.
    expect('http://example.invalid/speisekarte',
        hikkoshi_render(hikkoshi_kirby($tmp, ktOn(['hikkoshi.redirects' => ['menue' => 'speisekarte']])), '/menue')[2],
        'option array beats the files');
    expect('http://example.invalid/speisekarte',
        hikkoshi_render(hikkoshi_kirby($tmp, ktOn(['hikkoshi.redirects' => fn () => ['menue' => 'speisekarte']])), '/menue')[2],
        'option Closure beats the files');

    // hikkoshi.file: relative to site/config, and absolute.
    hikkoshi_write_config($tmp, ['menue' => 'speisekarte'], 'liste.php');
    expect('http://example.invalid/speisekarte',
        hikkoshi_render(hikkoshi_kirby($tmp, ktOn(['hikkoshi.file' => 'liste.php'])), '/menue')[2],
        'hikkoshi.file relative to site/config');
    @mkdir($tmp . '/elsewhere', 0700, true);
    file_put_contents($tmp . '/elsewhere/absolut.php', '<?php return ' . var_export(['menue' => 'produkte'], true) . ';');
    expect('http://example.invalid/produkte',
        hikkoshi_render(hikkoshi_kirby($tmp, ktOn(['hikkoshi.file' => $tmp . '/elsewhere/absolut.php'])), '/menue')[2],
        'hikkoshi.file absolute');

    // 410: the gone snippet, no Location.
    hikkoshi_write_config($tmp, ['karte-alt' => 410, 'menue' => 'speisekarte']);
    [$status, $body, $location] = hikkoshi_render(hikkoshi_kirby($tmp, ktOn()), '/karte-alt');
    expect(410, $status, '410 entry answers 410');
    holds(str_contains($body, 'hikkoshi-gone-title'), '410 body uses the gone snippet');
    holds(str_contains($body, 'Page removed'), '410 body is English by default');
    expect(null, $location, '410 has no Location');
    [$status, $body] = hikkoshi_render(hikkoshi_kirby($tmp, ktOn(['hikkoshi.language' => 'de'])), '/karte-alt');
    expect(410, $status, '410 with hikkoshi.language de');
    holds(str_contains($body, 'Seite entfernt'), '410 body is German with hikkoshi.language de');

    // A site's own snippet replaces the body.
    @mkdir($tmp . '/site/snippets/hikkoshi', 0700, true);
    file_put_contents($tmp . '/site/snippets/hikkoshi/gone.php', '<p class="eigen">Eigene Seite</p>');
    [$status, $body] = hikkoshi_render(hikkoshi_kirby($tmp, ktOn()), '/karte-alt');
    expect(410, $status, 'site snippet: still 410');
    holds(str_contains($body, 'Eigene Seite') && !str_contains($body, 'hikkoshi-gone-title'), 'site snippet replaces the body');
    unlink($tmp . '/site/snippets/hikkoshi/gone.php');

    // Per-entry status 302 and 308.
    hikkoshi_write_config($tmp, [
        'alt-302' => ['to' => 'produkte', 'status' => 302],
        'alt-308' => ['to' => 'produkte', 'status' => 308],
    ]);
    $statusKirby = hikkoshi_kirby($tmp, ktOn());
    expect(302, hikkoshi_render($statusKirby, '/alt-302')[0], 'per-entry status 302');
    expect(308, hikkoshi_render($statusKirby, '/alt-308')[0], 'per-entry status 308');

    // Per-entry query drop: no query on the target, even with one on the request.
    hikkoshi_write_config($tmp, ['alt-drop' => ['to' => 'speisekarte', 'query' => 'drop']]);
    expect('http://example.invalid/speisekarte',
        hikkoshi_render(hikkoshi_kirby($tmp, ktOn(), ktRequest(['utm_source' => 'qr'])), '/alt-drop')[2],
        'query drop per entry: no query on the target');

    // Global query drop.
    hikkoshi_write_config($tmp, ['menue' => 'speisekarte']);
    expect('http://example.invalid/speisekarte',
        hikkoshi_render(hikkoshi_kirby($tmp, ktOn(['hikkoshi.query' => 'drop']), ktRequest(['utm_source' => 'qr'])), '/menue')[2],
        'hikkoshi.query drop: no query on the target');

    // External targets: only with the host in allowedHosts.
    hikkoshi_write_config($tmp, ['extern' => 'https://other.example/x']);
    expect('https://other.example/x?utm_source=qr',
        hikkoshi_render(hikkoshi_kirby($tmp, ktOn(['hikkoshi.allowedHosts' => ['other.example']]), ktRequest(['utm_source' => 'qr'])), '/extern')[2],
        'external target with allowedHosts: location exact, query kept');
    // Without the allow-list the App cannot be built: every request is a 500.
    $thrown = null;
    try {
        hikkoshi_kirby($tmp, ktOn(), ktRequest([]));
    } catch (ConfigException $e) {
        $thrown = $e;
    }
    holds($thrown instanceof ConfigException, 'external target without allowedHosts: the App cannot be built (500)');

    // An uppercase scheme passes the Validator lowercased; Kirby's Uri would throw on `HTTPS:`.
    hikkoshi_write_config($tmp, ['extern' => 'HTTPS://other.example/x']);
    expect([301, 'https://other.example/x'],
        (static fn (array $r) => [$r[0], $r[2]])(hikkoshi_render(hikkoshi_kirby($tmp, ktOn(['hikkoshi.allowedHosts' => 'other.example']), ktRequest([])), '/extern')),
        'external target with uppercase scheme and allowedHosts as a string: 301, lowercase location');

    // A converted RewriteRule with a query on its external target and no QSA: Apache replaces the
    // request's query, so the route must not append it.
    hikkoshi_write_config($tmp, Hikkoshi\Htaccess::convert('RewriteRule ^replace$ https://target.example/new?mode=fixed [R=301,L]')['entries']);
    expect('https://target.example/new?mode=fixed',
        hikkoshi_render(hikkoshi_kirby($tmp, ktOn(['hikkoshi.allowedHosts' => ['target.example']]), ktRequest(['campaign' => 'sample'])), '/replace')[2],
        'converted RewriteRule with target query, no QSA: request query not appended');
    hikkoshi_write_config($tmp, Hikkoshi\Htaccess::convert('Redirect 301 /alt https://target.example/new?mode=fixed')['entries']
        + Hikkoshi\Htaccess::convert('RedirectMatch 301 ^/match$ https://target.example/new?mode=fixed')['entries']);
    expect('https://target.example/new?mode=fixed',
        hikkoshi_render(hikkoshi_kirby($tmp, ktOn(['hikkoshi.allowedHosts' => ['target.example']]), ktRequest(['campaign' => 'sample'])), '/alt')[2],
        'converted Redirect with target query: request query not appended');
    expect('https://target.example/new?mode=fixed',
        hikkoshi_render(hikkoshi_kirby($tmp, ktOn(['hikkoshi.allowedHosts' => ['target.example']]), ktRequest(['campaign' => 'sample'])), '/match')[2],
        'converted RedirectMatch with target query: request query not appended');
});

/* --- 6. query conditions ------------------------------------------------- */

withSite(function (string $tmp): void {
    hikkoshi_page($tmp, '5_impressum', 'default', ['title' => 'Impressum']);
    hikkoshi_write_config($tmp, [
        '?page_id=82' => 'impressum',
        '?feed' => 'home',
        'index.php?p=1' => 'home',
    ]);

    $pageId = hikkoshi_kirby($tmp, ktOn(), ktRequest(['page_id' => '82', 'utm' => 'x']));
    expect(301, hikkoshi_render($pageId, '/')[0], 'page_id=82 on the home path: 301');
    expect('http://example.invalid/impressum?utm=x', hikkoshi_render($pageId, '/')[2],
        'page_id=82 on the home path: condition removed, rest kept');

    $pageIdIndex = hikkoshi_kirby($tmp, ktOn(), ktRequest(['page_id' => '82', 'utm' => 'x']));
    expect('http://example.invalid/impressum?utm=x', hikkoshi_render($pageIdIndex, '/index.php')[2],
        'page_id=82 on /index.php: same location (suffix pass)');

    $other = hikkoshi_kirby($tmp, ktOn(), ktRequest(['page_id' => '99']));
    expect(200, hikkoshi_render($other, '/')[0], 'page_id=99 matches nothing: home renders 200');

    $feed = hikkoshi_kirby($tmp, ktOn(), ktRequest(['feed' => '1']));
    expect('http://example.invalid', hikkoshi_render($feed, '/')[2], 'parameter feed present with any value: home');

    $pre = hikkoshi_kirby($tmp, ktOn(), ktRequest(['p' => '1']));
    expect(301, hikkoshi_render($pre, '/index.php')[0], 'index.php?p=1: 301');
    expect('http://example.invalid', hikkoshi_render($pre, '/index.php')[2], 'index.php?p=1: location is home');
});

/* --- 7. suffix and umlaut on real requests ------------------------------- */

withSite(function (string $tmp): void {
    hikkoshi_write_config($tmp, ['impressum' => 'speisekarte', 'ueber-uns' => 'produkte']);
    $kirby = hikkoshi_kirby($tmp, ktOn());

    foreach (['/impressum.html' => 'http://example.invalid/speisekarte', '/Impressum.php' => 'http://example.invalid/speisekarte',
              '/über-uns' => 'http://example.invalid/produkte', '/%C3%BCber-uns' => 'http://example.invalid/produkte'] as $old => $location) {
        [$status, , $actual] = hikkoshi_render($kirby, $old);
        expect(301, $status, "$old: 301");
        expect($location, $actual, "$old: location");
    }

    $exact = hikkoshi_kirby($tmp, ktOn(['hikkoshi.lowercase' => false]));
    expect(404, hikkoshi_render($exact, '/Impressum.html')[0], 'hikkoshi.lowercase false: /Impressum.html is 404');
});

/* --- 8. site and page methods -------------------------------------------- */

withSite(function (string $tmp): void {
    hikkoshi_write_config($tmp, ktPreList(), 'weiterleitungen.php');
    $kirby = hikkoshi_kirby($tmp, ktOn());

    $entries = $kirby->site()->hikkoshiRedirects();
    expect(7, count($entries), 'hikkoshiRedirects() has all seven entries');
    expect('menue', $entries[0]['key'] ?? null, 'hikkoshiRedirects() starts with menue');

    $resolved = $kirby->site()->hikkoshiResolve('/menue', ['a' => '1']);
    expect('http://example.invalid/speisekarte?a=1', $resolved['location'] ?? null, 'hikkoshiResolve() location');
    expect(301, $resolved['status'] ?? null, 'hikkoshiResolve() status');
    expect('exact', $resolved['pass'] ?? null, 'hikkoshiResolve() pass');
    expect(null, $kirby->site()->hikkoshiResolve('/nope'), 'hikkoshiResolve() of an unknown path is null');

    expect(['produkt-c.html', 'team'], $kirby->page('produkte')->hikkoshiSources(),
        'page hikkoshiSources() in config order');
    expect(['index.html'], $kirby->site()->homePage()->hikkoshiSources(), 'home page hikkoshiSources()');

    $off = hikkoshi_kirby($tmp);
    expect([], $off->site()->hikkoshiRedirects(), 'off: hikkoshiRedirects() is []');
    expect(null, $off->site()->hikkoshiResolve('/menue'), 'off: hikkoshiResolve() is null');
    expect([], $off->page('produkte')->hikkoshiSources(), 'off: page hikkoshiSources() is []');
    expect([], $off->site()->homePage()->hikkoshiSources(), 'off: home page hikkoshiSources() is []');
});

/* --- 8b. renamed home page (Kirby option `home`) ------------------------- */

withSite(function (string $tmp): void {
    // No page `home`: the target `home` falls back to the home page, and the
    // home page counts the entries that name it `home` or by its own id.
    rename($tmp . '/content/home', $tmp . '/content/start');
    hikkoshi_write_config($tmp, ['index.html' => 'home', 'startseite' => 'start', 'menue' => 'speisekarte']);
    $kirby = hikkoshi_kirby($tmp, ktOn(['home' => 'start']), ktRequest([]));
    expect('start', $kirby->site()->homePage()->id(), 'fixture: the home page is start');
    expect([301, 'http://example.invalid'],
        (static fn (array $r) => [$r[0], $r[2]])(hikkoshi_render($kirby, '/index.html')),
        'renamed home: target home falls back to the home page');
    expect(['index.html', 'startseite'], $kirby->site()->homePage()->hikkoshiSources(),
        'renamed home: hikkoshiSources() has the home entry and the entry with its own id');
});

/* --- 9. option validation ------------------------------------------------ */

withSite(function (string $tmp): void {
    hikkoshi_write_config($tmp, ['menue' => 'speisekarte']);
    $kirby = null;
    $logged = withErrorLog($tmp, function () use ($tmp, &$kirby): void {
        $kirby = hikkoshi_kirby($tmp, ktOn(['hikkoshi.status' => 200]));
    });
    holds(str_contains($logged, 'hikkoshi.status'), 'status 200: the problem is in the error log');
    expect(1, substr_count($logged, 'hikkoshi.status'), 'status 200: logged once');
    expect(301, hikkoshi_render($kirby, '/menue')[0], 'status 200 is invalid: 301 is used');
    expect(301, hikkoshi_render($kirby, '/menue')[0], 'status 200 is invalid: still 301 on the next request');

    // With debug on, a broken option throws instead of being logged.
    $thrown = null;
    try {
        hikkoshi_kirby($tmp, ktOn(['hikkoshi.status' => 200, 'debug' => true]));
    } catch (InvalidArgumentException $e) {
        $thrown = $e;
    }
    holds($thrown instanceof InvalidArgumentException, 'debug on: an invalid option throws InvalidArgumentException');
});

finish('Hikkoshi Kirby');

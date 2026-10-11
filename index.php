<?php

use Kirby\Cms\App;

require_once __DIR__ . '/src/I18n.php';
require_once __DIR__ . '/src/Options.php';
require_once __DIR__ . '/src/Path.php';
require_once __DIR__ . '/src/ConfigException.php';
require_once __DIR__ . '/src/Validator.php';
require_once __DIR__ . '/src/Resolver.php';
require_once __DIR__ . '/src/Redirects.php';
require_once __DIR__ . '/src/SiteCheck.php';
// Kirby-free, also used by bin/hikkoshi; loaded here so a site's test suite
// can call Hikkoshi\Htaccess::convert() without extra requires.
require_once __DIR__ . '/src/Htaccess.php';
// CLI commands (bin/hikkoshi); declared here too so every src file is loaded from one place.
require_once __DIR__ . '/src/CheckCommand.php';
require_once __DIR__ . '/src/ConvertCommand.php';

use Hikkoshi\I18n;
use Hikkoshi\Options;
use Hikkoshi\Redirects;
use Hikkoshi\SiteCheck;

/**
 * Hikkoshi (引っ越し — moving house): redirects from the old addresses of a
 * site that moved to Kirby (Jimdo, Wix, WordPress). Off by default: without
 * `'hikkoshi.active' => true` no route is registered and the site and page
 * methods return [].
 *
 * Shared file — owned by the architect (SPEC.md §2). Builders never edit it;
 * all logic lives in src/Redirects.php (Kirby glue) and the Kirby-free
 * classes Validator, Resolver, Path.
 *
 * Defaults are passed as fallback values directly at each option call, NOT
 * in an 'options' block (Kirby would file them under sayamaapps.hikkoshi.*
 * otherwise).
 *
 * The pre-1.0 copy, site/plugins/weiterleitungen (plugin id
 * weiterleitungen/kern), must be REMOVED when the package is installed: both
 * register routes for the same old paths, and the copy would answer first.
 * Hikkoshi reads the copy's list file (site/config/weiterleitungen.php)
 * unchanged, so only the plugin folder goes (SPEC.md §7).
 */

/** Reads `hikkoshi.<key>`. Also meant for site snippets with their own markup. */
function hikkoshi_option(string $key, mixed $default = null): mixed
{
    return Options::get(App::instance(), $key, $default);
}

$hikkoshiTranslations = [];
foreach (I18n::LANGUAGES as $hikkoshiLanguage) {
    foreach (I18n::table($hikkoshiLanguage) as $hikkoshiKey => $hikkoshiText) {
        $hikkoshiTranslations[$hikkoshiLanguage]['hikkoshi.' . $hikkoshiKey] = $hikkoshiText;
    }
}
unset($hikkoshiLanguage, $hikkoshiKey, $hikkoshiText);

App::plugin('sayamaapps/hikkoshi', [
    // All strings, also usable as t('hikkoshi.<key>').
    'translations' => $hikkoshiTranslations,

    /**
     * The body of a 410 response. A site with its own markup creates
     * site/snippets/hikkoshi/gone.php — Kirby looks there first. It receives
     * $title and $text (SPEC.md §3.5).
     */
    'snippets' => [
        'hikkoshi/gone' => __DIR__ . '/snippets/hikkoshi/gone.php',
    ],

    /**
     * One catch-all GET|HEAD route (SPEC.md §3.4) that answers for old paths
     * and calls $this->next() for everything else. [] while Hikkoshi is off.
     * A list with errors throws Hikkoshi\ConfigException here, so EVERY
     * request fails (500) instead of one entry being skipped silently — the
     * pre-1.0 behaviour, kept on purpose (SPEC.md §10).
     */
    'routes' => function (App $kirby): array {
        return Redirects::routes($kirby);
    },

    'siteMethods' => [
        /** The validated, normalised entries (SPEC.md §3.2.1); [] when off. */
        'hikkoshiRedirects' => function (): array {
            return Redirects::entries($this->kirby());
        },

        /**
         * The decision for a path and query as the route would take it
         * (SPEC.md §3.3.2), or null. Meant for a site's own tests and
         * debugging; [] / null when off.
         */
        'hikkoshiResolve' => function (string $path, array $query = []): ?array {
            return Redirects::resolve($this->kirby(), $path, $query);
        },

        /**
         * Site test helper (SPEC.md §3.6): every entry resolves in at most one
         * hop to a 200 page. Returns the list of findings, [] when all is well.
         */
        'hikkoshiCheck' => function (bool $render = true): array {
            return SiteCheck::run($this->kirby(), $render);
        },
    ],

    'pageMethods' => [
        /** Old paths (config keys) that redirect to this page: list<string>. [] when off. */
        'hikkoshiSources' => function (): array {
            return Redirects::sourcesFor($this->kirby(), $this);
        },
    ],
]);

<?php

namespace Hikkoshi;

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Cms\Responder;
use Kirby\Exception\Exception as KirbyException;
use Kirby\Http\Response;
use Kirby\Http\Url;

/**
 * The test helper for sites (SPEC.md §3.6): every entry resolves in at most
 * one hop to a 200 page. Used by `$site->hikkoshiCheck()`
 * in a site's test suite; never on a live request.
 *
 * Renders through $kirby->router()->call(), not $kirby->render(): Kirby keeps
 * one Responder per App, and a 404 rendered earlier would stick to later
 * results.
 */
final class SiteCheck
{
    private const REDIRECTS = [301, 302, 307, 308];

    /**
     * Findings in the Validator shape, [] when all is well. Infos are never
     * returned (a resolved chain is not a problem).
     *
     * @return list<array{level:string, code:string, message:string, key:?string, values:array}>
     */
    public static function run(App $kirby, bool $render = true): array
    {
        if (!Options::active($kirby)) {
            return [Validator::finding(Validator::ERROR, 'inactive', [], null)];
        }

        Options::applyLanguage($kirby);
        $settings = Options::settings($kirby);
        $result = Validator::validate(Redirects::config($kirby), $settings);

        $findings = [];
        foreach ($result['findings'] as $finding) {
            if ($finding['level'] !== Validator::INFO) {
                $findings[] = $finding;
            }
        }
        if (Validator::hasErrors($findings)) {
            return $findings;
        }

        $resolver = new Resolver($result['entries'], $settings);
        $rendered = [];

        foreach ($result['entries'] as $entry) {
            $key = $entry['key'];
            $text = I18n::entry($key, $entry['status'] === 410 ? 410 : $entry['target']);

            $page = null;
            if ($entry['status'] !== 410 && !$entry['external']) {
                $page = Redirects::targetPage($kirby, $entry['target']);
                if ($page === null || $page->isDraft()) {
                    $findings[] = Validator::finding(Validator::ERROR, 'page.missing',
                        ['target' => $entry['target'], 'entry' => $text], $key);
                    continue;
                }
            }

            // Self-consistency: the entry's own old path must find this entry.
            $query = [];
            foreach ($entry['conditions'] as $name => $value) {
                $query[$name] = $value ?? '1';
            }
            $hit = $resolver->match($entry['source'], $query);
            if ($hit === null || $hit['entry']['key'] !== $key) {
                $findings[] = Validator::finding(Validator::ERROR, 'check.noMatch',
                    ['source' => Validator::identity($entry['source'], $entry['conditions']), 'entry' => $text], $key);
                continue;
            }

            if (!$render) {
                continue;
            }

            // The old path itself, only for entries without query conditions.
            if ($entry['conditions'] === []) {
                $source = '/' . $entry['source'];
                [$code, $location] = self::request($kirby, $source);
                if ($code !== $entry['status']) {
                    $findings[] = Validator::finding(Validator::ERROR, 'check.status',
                        ['source' => $source, 'actual' => $code, 'expected' => $entry['status']], $key);
                } elseif ($entry['status'] !== 410) {
                    // The route appends the request's query; so does the expected location.
                    $expected = Redirects::resolve($kirby, $entry['source'], $kirby->request()->query()->toArray())['location'] ?? null;
                    // Kirby sends Locations punycoded (Response::redirect → Url::unIdn).
                    // Url::unIdn() returns a Uri object: cast it, or the comparison fails for every redirect.
                    $expected = $expected === null ? null : (string) Url::unIdn($expected);
                    if ($location !== $expected) {
                        $findings[] = Validator::finding(Validator::ERROR, 'check.location',
                            ['source' => $source, 'actual' => (string) $location, 'expected' => (string) $expected], $key);
                    }
                }
            }

            // The target page: rendered once, must answer 200 and not redirect again.
            if ($page !== null && !isset($rendered[$page->id()])) {
                $rendered[$page->id()] = true;
                // The home page is requested as '' (SPEC §3.6): '/home' is Kirby's redirect to '/'.
                [$code] = self::request($kirby, $page->isHomePage() ? '' : $page->uri());
                if (in_array($code, self::REDIRECTS, true)) {
                    $findings[] = Validator::finding(Validator::ERROR, 'check.hop',
                        ['target' => $entry['target'], 'entry' => $text], $key);
                } elseif ($code !== 200) {
                    $findings[] = Validator::finding(Validator::ERROR, 'check.target',
                        ['target' => $entry['target'], 'actual' => $code], $key);
                }
            }
        }

        return $findings;
    }

    /**
     * What the router answers for a GET on this path: [status, Location or null].
     *
     * @return array{0: int, 1: ?string}
     */
    private static function request(App $kirby, string $path): array
    {
        try {
            $result = $kirby->router()->call(trim($path, '/'), 'GET');

            // Responder: what Kirby's core routes and $kirby->response()->redirect() return.
            if ($result instanceof Responder) {
                $location = $result->headers()['Location'] ?? null;
                return [$result->code() ?? 200, $location === null ? null : (string) $location];
            }
            if ($result instanceof Response) {
                $location = $result->headers()['Location'] ?? null;
                return [$result->code(), $location === null ? null : (string) $location];
            }
            if ($result instanceof Page) {
                $result->render();
                return [$result->isErrorPage() ? 404 : 200, null];
            }
            if ($result === null) {
                return [404, null];
            }
            // A File, or anything else the router returns, is delivered.
            return [200, null];
        } catch (\Throwable $e) {
            return [$e instanceof KirbyException ? $e->getHttpCode() : 500, null];
        }
    }
}

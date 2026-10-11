<?php

namespace Hikkoshi;

/**
 * Matches a request (path + query) against validated entries, Kirby-free
 * (SPEC.md §3.3). The Kirby glue (Redirects) turns a match into a Response;
 * the CLI and a site's tests use it directly.
 *
 * Two passes: 1. the normalised request path as it is; 2. the same path with
 * a trailing index file or a known suffix removed (`impressum.html` →
 * `impressum`, `blog/index.php` → `blog`, `index.php` → `` = home). Within a
 * pass, entries with more query conditions win over entries with fewer; an
 * entry without conditions matches any query.
 */
final class Resolver
{
    /** @var array<string, list<array>> normalised source path => entries, most conditions first */
    private array $index = [];

    private array $settings;

    /**
     * @param list<array> $entries  from Validator::validate()['entries'] / assert()
     * @param array       $settings Options::settings() shape; missing keys come from Options::DEFAULTS
     */
    public function __construct(array $entries, array $settings = [])
    {
        $this->settings = Options::lists($settings + Options::DEFAULTS);
        foreach ($entries as $entry) {
            $this->index[$entry['source']][] = $entry;
        }
        foreach ($this->index as &$list) {
            // Stable: equal condition counts keep config order (PHP 8 usort is stable).
            usort($list, static fn (array $a, array $b) => count($b['conditions']) <=> count($a['conditions']));
        }
        unset($list);
    }

    public function settings(): array
    {
        return $this->settings;
    }

    /** All entries, config order lost (grouped by source). */
    public function entries(): array
    {
        return array_merge([], ...array_values($this->index));
    }

    /**
     * The matching entry for a request, or null.
     *
     *   match('/Impressum.html', ['utm_source' => 'qr'])
     *   → ['entry' => […], 'pass' => 'suffix', 'path' => 'impressum',
     *      'remaining' => ['utm_source' => 'qr']]
     *
     * `remaining` is the query without the keys the entry's conditions used
     * (`?page_id=82&utm_source=qr` → `['utm_source' => 'qr']`). `path` is the
     * normalised path that hit.
     *
     * @param array<string, mixed> $query as Kirby's $request->query()->toArray() / $_GET
     * @return ?array{entry: array, pass: string, path: string, remaining: array}
     */
    public function match(string $path, array $query = []): ?array
    {
        $normalized = Path::normalize($path, $this->settings['lowercase'], $this->settings['transliterate']);
        $entry = $this->lookup($normalized, $query);
        if ($entry !== null) {
            return $this->result($entry, 'exact', $normalized, $query);
        }
        $stripped = Path::stripSuffix($normalized, $this->settings['suffixes'], $this->settings['indexFiles']);
        if ($stripped !== null && $stripped !== $normalized) {
            $entry = $this->lookup($stripped, $query);
            if ($entry !== null) {
                return $this->result($entry, 'suffix', $stripped, $query);
            }
        }
        return null;
    }

    /**
     * Do all conditions hold for this query? `k => null` means "k is
     * present with any value", `k => 'v'` means "k equals v" (string
     * comparison; an array value such as probe[]=1 never equals).
     */
    public static function conditionsMatch(array $conditions, array $query): bool
    {
        foreach ($conditions as $name => $expected) {
            if (!array_key_exists($name, $query)) {
                return false;
            }
            if ($expected === null) {
                continue;
            }
            $actual = $query[$name];
            if (is_array($actual) || (string) $actual !== $expected) {
                return false;
            }
        }
        return true;
    }

    /**
     * The query to append to the target for a match: the remaining
     * parameters when the entry keeps the query, [] when it drops it or is
     * a 410.
     */
    public static function targetQuery(array $match): array
    {
        $entry = $match['entry'];
        if ($entry['status'] === 410 || $entry['query'] !== 'keep') {
            return [];
        }
        return $match['remaining'];
    }

    /**
     * Appends a query array to a URL that may already carry one
     * (`https://other.example/x?a=1` + ['b' => '2'] → `…?a=1&b=2`). A fragment
     * is kept at the end. Nothing is appended for [].
     */
    public static function appendQuery(string $url, array $query): string
    {
        $encoded = Path::query($query);
        if ($encoded === '') {
            return $url;
        }
        $fragment = '';
        $hash = strpos($url, '#');
        if ($hash !== false) {
            $fragment = substr($url, $hash);
            $url = substr($url, 0, $hash);
        }
        $separator = str_contains($url, '?') ? (str_ends_with($url, '?') || str_ends_with($url, '&') ? '' : '&') : '?';
        return $url . $separator . $encoded . $fragment;
    }

    private function lookup(string $source, array $query): ?array
    {
        foreach ($this->index[$source] ?? [] as $entry) {
            if (self::conditionsMatch($entry['conditions'], $query)) {
                return $entry;
            }
        }
        return null;
    }

    private function result(array $entry, string $pass, string $path, array $query): array
    {
        $remaining = $query;
        foreach (array_keys($entry['conditions']) as $name) {
            unset($remaining[$name]);
        }
        return ['entry' => $entry, 'pass' => $pass, 'path' => $path, 'remaining' => $remaining];
    }
}

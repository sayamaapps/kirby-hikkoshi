<?php

namespace Hikkoshi;

use Kirby\Cms\App;
use Kirby\Cms\Page;
use Kirby\Http\Response;
use Kirby\Http\Route;

/**
 * The Kirby glue (SPEC.md §3.4): reads the list, validates it once per App
 * instance, registers the catch-all route and builds the Response.
 *
 * Everything that decides a request lives in Validator, Resolver and Path;
 * this class only wires them to Kirby.
 */
final class Redirects
{
	/**
	 * Validated state per App instance, so the two site methods and the route
	 * do not validate the list three times per request.
	 *
	 * @var \WeakMap<App, array{entries: list<array>, resolver: Resolver}>|null
	 */
	private static \WeakMap|null $cache = null;

	/** The raw list: option hikkoshi.redirects, else hikkoshi.file, else the first existing Options::FILES, else []. */
	public static function config(App $kirby): mixed
	{
		$option = Options::get($kirby, 'redirects');
		if ($option instanceof \Closure) {
			return $option();
		}
		if ($option !== null) {
			return $option;
		}

		$file = Options::get($kirby, 'file');
		if (is_string($file) && trim($file) !== '') {
			$path = self::isAbsolute($file) ? $file : $kirby->root('config') . '/' . $file;
			if (!is_file($path)) {
				Options::invalid($kirby, 'Hikkoshi: hikkoshi.file points to a missing file: ' . $path);
				return [];
			}
			return require $path;
		}

		foreach (Options::FILES as $name) {
			$path = $kirby->root('config') . '/' . $name;
			if (is_file($path)) {
				return require $path;
			}
		}
		return [];
	}

	/**
	 * The validated entries (SPEC.md §3.2.1), [] when Hikkoshi is off.
	 *
	 * @return list<array>
	 * @throws ConfigException when the list has an error (every request then fails)
	 */
	public static function entries(App $kirby): array
	{
		return self::loaded($kirby)['entries'];
	}

	private static function isAbsolute(string $path): bool
	{
		return str_starts_with($path, '/') || (strlen($path) > 1 && $path[1] === ':');
	}

	/**
	 * Validates the list once per App instance. Warnings and infos go to the
	 * error log (once per process); an error throws.
	 *
	 * @return array{entries: list<array>, resolver: Resolver}
	 * @throws ConfigException
	 */
	private static function loaded(App $kirby): array
	{
		if (!Options::active($kirby)) {
			return ['entries' => [], 'resolver' => new Resolver([])];
		}

		// Every call sets the language: the text of messages depends on it.
		Options::applyLanguage($kirby);

		self::$cache ??= new \WeakMap();
		if (isset(self::$cache[$kirby])) {
			return self::$cache[$kirby];
		}

		$settings = Options::settings($kirby);
		$result = Validator::validate(self::config($kirby), $settings);

		$hasErrors = false;
		foreach ($result['findings'] as $finding) {
			if ($finding['level'] === Validator::ERROR) {
				$hasErrors = true;
			} else {
				Options::log($finding['message']);
			}
		}
		if ($hasErrors) {
			throw new ConfigException($result['findings']);
		}

		$loaded = [
			'entries'  => $result['entries'],
			'resolver' => new Resolver($result['entries'], $settings),
		];
		self::$cache[$kirby] = $loaded;
		return $loaded;
	}

	/**
	 * The decision for a path and query as the route would take it, or null:
	 * inactive, no match, or the internal target page is missing or a draft
	 * (the request then falls through to Kirby). The Entry fields plus
	 * pass, remaining, page (internal target or null) and location.
	 *
	 * @throws ConfigException when the list has an error
	 */
	public static function resolve(App $kirby, string $path, array $query = []): array|null
	{
		if (!Options::active($kirby)) {
			return null;
		}

		$match = self::resolver($kirby)->match($path, $query);
		if ($match === null) {
			return null;
		}

		$entry = $match['entry'];
		$page = null;
		$location = null;

		if ($entry['status'] === 410) {
			// Gone: no location.
		} elseif ($entry['external']) {
			$location = Resolver::appendQuery($entry['target'], Resolver::targetQuery($match));
		} else {
			$page = self::targetPage($kirby, $entry['target']);
			if ($page === null || $page->isDraft()) {
				return null;
			}
			$location = Resolver::appendQuery($page->url(), Resolver::targetQuery($match));
		}

		return $entry + [
			'pass'      => $match['pass'],
			'remaining' => $match['remaining'],
			'page'      => $page,
			'location'  => $location,
		];
	}

	/**
	 * The matcher over entries(), same cache. An empty resolver while off.
	 *
	 * @throws ConfigException when the list has an error
	 */
	public static function resolver(App $kirby): Resolver
	{
		return self::loaded($kirby)['resolver'];
	}

	/**
	 * The response for a path and query, or null when the request falls
	 * through to Kirby. 410 renders the `hikkoshi/gone` snippet.
	 *
	 * @throws ConfigException when the list has an error
	 */
	public static function respond(App $kirby, string $path, array $query = []): Response|null
	{
		$decision = self::resolve($kirby, $path, $query);
		if ($decision === null) {
			return null;
		}

		if ($decision['status'] === 410) {
			$body = $kirby->snippet('hikkoshi/gone', [
				'title' => I18n::t('gone.title'),
				'text'  => I18n::t('gone.text'),
			], true);
			return new Response($body, 'text/html', 410);
		}

		return Response::redirect($decision['location'], $decision['status']);
	}

	/**
	 * The one catch-all GET|HEAD route (SPEC.md §3.4). [] while Hikkoshi is
	 * off. A broken list throws here, at route registration, so every
	 * request fails with 500.
	 *
	 * @return list<array>
	 */
	public static function routes(App $kirby): array
	{
		if (!Options::active($kirby)) {
			return [];
		}
		// Validates now, so a broken list throws at route registration.
		self::entries($kirby);

		return [[
			'pattern' => '(:all)',
			'method'  => 'GET|HEAD',
			'action'  => function (string $path = '') use ($kirby) {
				$response = Redirects::respond($kirby, $path, $kirby->request()->query()->toArray());
				if ($response === null) {
					Route::next();
				}
				return $response;
			},
		]];
	}

	/**
	 * The keys of the entries that point to this page, config order: `'home'`
	 * for the home page. [] while Hikkoshi is off.
	 *
	 * @return list<string>
	 */
	public static function sourcesFor(App $kirby, Page $page): array
	{
		if (!Options::active($kirby)) {
			return [];
		}

		$keys = [];
		foreach (self::entries($kirby) as $entry) {
			if ($entry['target'] === $page->id() || ($page->isHomePage() && $entry['target'] === 'home')) {
				$keys[] = $entry['key'];
			}
		}
		return $keys;
	}

	/**
	 * The internal target page: by id, `home` falling back to the home page.
	 * Null when it does not exist (drafts are returned; callers check them).
	 */
	public static function targetPage(App $kirby, string $target): Page|null
	{
		$site = $kirby->site();
		$page = $site->find($target);
		if ($page === null && $target === 'home') {
			$page = $site->homePage();
		}
		return $page;
	}
}

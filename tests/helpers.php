<?php
/**
 * Shared test helpers.
 *
 *   require_once __DIR__ . '/helpers.php';
 *
 * Loads NO src file: Kirby-free tests require the classes they need
 * themselves, Kirby tests load the plugin through Kirby (hikkoshi_site()).
 *
 * Every test class runs its checks in testSuite(): expect() and holds()
 * are PHPUnit assertions, so one run reports every failing check.
 */

use PHPUnit\Framework\Assert;

/** Strict comparison ($expected === $actual), counted as one PHPUnit assertion. */
function expect(mixed $expected, mixed $actual, string $what): void
{
	Assert::assertSame($expected, $actual, $what);
}

/** A boolean check, counted as one PHPUnit assertion. */
function holds(bool $ok, string $what): void
{
	Assert::assertTrue($ok, $what);
}

/** Only these keys of an array, in this order (for comparing parts of an entry or finding). */
function only(array $array, array $keys): array
{
	$result = [];
	foreach ($keys as $key) {
		$result[$key] = $array[$key] ?? null;
	}
	return $result;
}

/** The `code` values of a list of findings, in order. */
function codes(array $findings): array
{
	return array_values(array_map(static fn (array $f) => $f['code'], $findings));
}

/** Runs $fn with a fresh throwaway site directory, removes it afterwards. */
function withSite(callable $fn): void
{
	$tmp = hikkoshi_site();
	try {
		$fn($tmp);
	} finally {
		Kirby\Filesystem\Dir::remove($tmp);
	}
}

/* --------------------------------------------------------------------------
 * Kirby fixtures (need vendor/: composer install).
 * -------------------------------------------------------------------------- */

/**
 * A throwaway site directory with the package symlinked as
 * site/plugins/hikkoshi, templates (default prints "Page <id>", error prints
 * "Fixture 404"), a home page, an error page, and the pages of the pre-1.0
 * HTTP test: speisekarte, produkte, leistungen/beratung, team, and the DRAFT
 * produkt-b. Returns its path; removed by withSite() in a finally block.
 */
function hikkoshi_site(): string
{
	$base = dirname(__DIR__);
	if (!is_file($base . '/vendor/autoload.php')) {
		fwrite(STDERR, "Composer dependencies missing; run composer install first\n");
		exit(1);
	}
	require_once $base . '/vendor/autoload.php';

	$tmp = sys_get_temp_dir() . '/hikkoshi-kirby-' . bin2hex(random_bytes(6));
	foreach (['public', 'content/home', 'content/error', 'content/1_speisekarte', 'content/2_produkte',
		'content/3_leistungen/beratung', 'content/4_team', 'content/_drafts/produkt-b',
		'site/config', 'site/plugins', 'site/snippets', 'site/templates', 'storage'] as $dir) {
		mkdir($tmp . '/' . $dir, 0700, true);
	}
	symlink($base, $tmp . '/site/plugins/hikkoshi');
	file_put_contents($tmp . '/site/templates/default.php', '<?php echo "Page " . $page->id();');
	file_put_contents($tmp . '/site/templates/error.php', 'Fixture 404');
	file_put_contents($tmp . '/content/site.txt', "Title: Testseite\n");
	file_put_contents($tmp . '/content/home/default.txt', "Title: Start\n");
	file_put_contents($tmp . '/content/error/error.txt', "Title: Error\n");
	foreach (['1_speisekarte', '2_produkte', '3_leistungen', '3_leistungen/beratung', '4_team', '_drafts/produkt-b'] as $page) {
		file_put_contents($tmp . "/content/$page/default.txt", "Title: $page\n");
	}
	return $tmp;
}

/**
 * A fresh Kirby instance on a hikkoshi_site() directory with these options
 * and, optionally, a request (['method' => 'GET', 'query' => ['page_id' => '82']]).
 * The site URL is http://example.invalid, so a redirect to `produkte` has the
 * Location http://example.invalid/produkte and `home` http://example.invalid.
 * Snippet paths are cached per process by Kirby, so the cache is reset.
 */
function hikkoshi_kirby(string $tmp, array $options = [], array|null $request = null): Kirby\Cms\App
{
	Kirby\Template\Snippet::$cache = [];
	$props = [
		'roots' => [
			'base'    => $tmp,
			'index'   => $tmp . '/public',
			'content' => $tmp . '/content',
			'site'    => $tmp . '/site',
			'storage' => $tmp . '/storage',
		],
		'options' => $options + ['url' => 'http://example.invalid', 'debug' => false, 'cache' => false],
	];
	if ($request !== null) {
		$props['request'] = $request;
	}
	return new Kirby\Cms\App($props);
}

/**
 * Writes site/config/<name> returning this list, or removes the file when
 * $list is null. Default name: hikkoshi.php; pass 'weiterleitungen.php' for
 * the legacy file.
 */
function hikkoshi_write_config(string $tmp, array|null $list, string $name = 'hikkoshi.php'): void
{
	$file = $tmp . '/site/config/' . $name;
	if ($list === null) {
		@unlink($file);
		return;
	}
	file_put_contents($file, '<?php return ' . var_export($list, true) . ';');
}

/**
 * Renders a request in-process: [status, body, location]. $kirby must have
 * been created with the matching request (hikkoshi_kirby(..., ['method' => …,
 * 'query' => …])) when the query matters; the method is passed here.
 *
 * Kirby keeps ONE Responder per App instance and io() writes the status into
 * it (a 404 would stick to every later render on the same instance), so the
 * Responder is reset before each render. The Location header is cast to
 * string (Kirby stores a Uri object there).
 *
 * @return array{0: int, 1: string, 2: ?string}
 */
function hikkoshi_render(Kirby\Cms\App $kirby, string $path, string $method = 'GET'): array
{
	(function (): void {
		$this->response = null;
	})->call($kirby);
	$response = $kirby->render($path, $method);
	$location = $response->headers()['Location'] ?? null;
	return [$response->code(), (string)$response->body(), $location === null ? null : (string)$location];
}

/** Writes content/<dir>/<template>.txt with these fields (plain strings). */
function hikkoshi_page(string $tmp, string $dir, string $template, array $fields): void
{
	$lines = [];
	foreach ($fields as $name => $value) {
		$lines[] = ucfirst($name) . ': ' . (string)$value;
	}
	@mkdir($tmp . '/content/' . $dir, 0700, true);
	file_put_contents($tmp . '/content/' . $dir . '/' . $template . '.txt', implode("\n\n----\n\n", $lines) . "\n");
}

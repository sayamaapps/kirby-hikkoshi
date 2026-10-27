<?php

use Sayamaapps\Hikkoshi\Redirects;
use Sayamaapps\Hikkoshi\SiteCheck;

return [
	/** The validated, normalised entries (docs/SPEC.md §3.2.1); [] when off. */
	'hikkoshiRedirects' => function (): array {
		return Redirects::entries($this->kirby());
	},

	/**
	 * The decision for a path and query as the route would take it
	 * (docs/SPEC.md §3.3.2), or null. Meant for a site's own tests and debugging;
	 * [] / null when off.
	 */
	'hikkoshiResolve' => function (string $path, array $query = []): array|null {
		return Redirects::resolve($this->kirby(), $path, $query);
	},

	/**
	 * Site test helper (docs/SPEC.md §3.6): every entry resolves in at most one hop
	 * to a 200 page. Returns the list of findings, [] when all is well.
	 */
	'hikkoshiCheck' => function (bool $render = true): array {
		return SiteCheck::run($this->kirby(), $render);
	},
];

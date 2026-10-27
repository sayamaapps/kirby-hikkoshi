<?php

use Sayamaapps\Hikkoshi\Redirects;

return [
	/** Old paths (config keys) that redirect to this page: list<string>. [] when off. */
	'hikkoshiSources' => function (): array {
		return Redirects::sourcesFor($this->kirby(), $this);
	},
];

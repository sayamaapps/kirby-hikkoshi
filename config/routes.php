<?php

use Kirby\Cms\App;
use Sayamaapps\Hikkoshi\Redirects;

/**
 * One catch-all GET|HEAD route (docs/SPEC.md §3.4) that answers for old paths and
 * calls $this->next() for everything else. [] while Hikkoshi is off. A list
 * with errors throws Sayamaapps\Hikkoshi\ConfigException here, so EVERY request
 * fails (500) instead of one entry being skipped silently — the pre-1.0
 * behaviour, kept on purpose (docs/SPEC.md §10).
 */
return function (App $kirby): array {
	return Redirects::routes($kirby);
};

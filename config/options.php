<?php

// Options Hikkoshi reads, relative to the plugin: `sayamaapps.hikkoshi.<key>`.
// Each value is null on purpose: Options::get() gives the default at every
// read, and a default here would hide the 1.x spelling `hikkoshi.<key>`.
return [
	'active'        => null,
	'redirects'     => null,
	'file'          => null,
	'status'        => null,
	'query'         => null,
	'allowedHosts'  => null,
	'resolveChains' => null,
	'lowercase'     => null,
	'transliterate' => null,
	'suffixes'      => null,
	'indexFiles'    => null,
	'language'      => null,
];

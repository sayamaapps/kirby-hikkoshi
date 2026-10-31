<?php

// Options Hikkoshi reads, relative to the plugin: `sayamaapps.hikkoshi.<key>`.
// Each value is null on purpose. Options::get() gives the default at every read,
// and a default here would hide the 1.x spelling `hikkoshi.<key>` (fallback).
return [
	'active'        => null,
	'allowedHosts'  => null,
	'file'          => null,
	'indexFiles'    => null,
	'language'      => null,
	'lowercase'     => null,
	'query'         => null,
	'redirects'     => null,
	'resolveChains' => null,
	'status'        => null,
	'suffixes'      => null,
	'transliterate' => null,
];

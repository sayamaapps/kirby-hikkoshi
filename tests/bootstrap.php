<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

// The plugin file, as Kirby loads it: class map, legacy names, plugin registration.
// Kirby tests reach the same file through the site folder hikkoshi_site() builds.
require_once dirname(__DIR__) . '/index.php';
require_once __DIR__ . '/helpers.php';

// Kirby's Whoops error handler stays registered after every App is created, so
// the risky-test check would flag each Kirby test. PHPUnit reports errors itself.
Kirby\Cms\App::$enableWhoops = false;

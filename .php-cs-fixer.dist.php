<?php

// The Kirby rule set removes @package from docblocks. The class docblocks of
// this package keep it (every class names its package), so that one rule is off.
return Kirby\PhpCs\Config::create()
	->setRules([
		'@Kirby/style:risky' => true,
		'phpdoc_no_package'  => false,
	])
	->setFinder(
		PhpCsFixer\Finder::create()
			->exclude('vendor')
			->in(__DIR__)
			// The CLI has no .php extension, so the finder does not match it by name.
			->append([__DIR__ . '/bin/hikkoshi'])
	);

<?php

/**
 * Class map for Kirby's F::loadClasses() (AUT-2). Keys are lowercase class names,
 * values are paths relative to the plugin root. tests/AutoloadTest.php checks the
 * map against src/.
 */

return [
	'sayamaapps\\hikkoshi\\checkcommand' => 'src/CheckCommand.php',
	'sayamaapps\\hikkoshi\\configexception' => 'src/ConfigException.php',
	'sayamaapps\\hikkoshi\\convertcommand' => 'src/ConvertCommand.php',
	'sayamaapps\\hikkoshi\\htaccess' => 'src/Htaccess.php',
	'sayamaapps\\hikkoshi\\i18n' => 'src/I18n.php',
	'sayamaapps\\hikkoshi\\options' => 'src/Options.php',
	'sayamaapps\\hikkoshi\\path' => 'src/Path.php',
	'sayamaapps\\hikkoshi\\redirects' => 'src/Redirects.php',
	'sayamaapps\\hikkoshi\\resolver' => 'src/Resolver.php',
	'sayamaapps\\hikkoshi\\sitecheck' => 'src/SiteCheck.php',
	'sayamaapps\\hikkoshi\\validator' => 'src/Validator.php',
];

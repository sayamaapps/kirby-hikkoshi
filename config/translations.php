<?php

use Sayamaapps\Hikkoshi\I18n;

// All strings, also usable as t('sayamaapps.hikkoshi.<key>') in templates. The
// 1.x key t('hikkoshi.<key>') keeps working next to it until 2.0.
$translations = [];
foreach (I18n::LANGUAGES as $language) {
	foreach (I18n::table($language) as $key => $text) {
		$translations[$language]['sayamaapps.hikkoshi.' . $key] = $text;
		$translations[$language]['hikkoshi.' . $key] = $text;
	}
}

return $translations;

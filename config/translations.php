<?php

use Sayamaapps\Hikkoshi\I18n;

// All strings, also usable as t('hikkoshi.<key>') in templates.
$translations = [];
foreach (I18n::LANGUAGES as $language) {
	foreach (I18n::table($language) as $key => $text) {
		$translations[$language]['hikkoshi.' . $key] = $text;
	}
}

return $translations;

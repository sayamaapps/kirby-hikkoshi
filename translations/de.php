<?php

/**
 * Deutsche Texte. Gleiche Schlüssel und Platzhalter wie translations/en.php
 * (tests/I18nTest.php prüft das). `{entry}` ist immer der Konfigurationseintrag
 * als 'alt' => 'neu' (I18n::entry()).
 */
return [
    'level.error'   => 'Fehler',
    'level.warning' => 'Warnung',
    'level.info'    => 'Hinweis',

    'finding.config.notArray'  => 'Die Weiterleitungsliste muss ein Array alt => neu sein.',
    'finding.source.numeric'   => 'Alter Pfad fehlt oder ist rein numerisch: {entry}',
    'finding.source.invalid'   => 'Ungültiger alter Pfad: {entry}',
    'finding.source.empty'     => 'Die Startseite kann nur zusammen mit einer Query-Bedingung (etwa \'?page_id=82\') ein alter Pfad sein: {entry}',
    'finding.source.duplicate' => 'Doppelter alter Pfad {source}: {entry} (zeigt bereits auf {target})',
    'finding.entry.invalid'    => 'Ungültiger Eintrag: {entry}. Erlaubt sind eine Seiten-ID, 410 oder ein Array mit den Schlüsseln to, status und query.',
    'finding.target.invalid'   => 'Ungültige Weiterleitung: {entry}',
    'finding.target.missing'   => 'Eintrag ohne Ziel: {entry}',
    'finding.target.foreign'   => 'Fremde Domain {host} ist als Ziel nicht erlaubt: {entry}. In hikkoshi.allowedHosts eintragen.',
    'finding.target.self'      => 'Weiterleitung auf sich selbst: {entry}',
    'finding.status.invalid'   => 'Ungültiger Status {status}: {entry}. Erlaubt: 301, 302, 307, 308, 410.',
    'finding.query.invalid'    => 'Ungültige Query-Einstellung {query}: {entry}. Erlaubt: keep, drop.',
    'finding.loop'             => 'Weiterleitungsschleife: {chain}',
    'finding.chain'            => 'Weiterleitungskette: {chain}. Das Ziel direkt eintragen.',
    'finding.chain.resolved'   => 'Weiterleitungskette {chain} auf {target} aufgelöst.',

    'finding.inactive'       => 'Hikkoshi ist aus: \'hikkoshi.active\' => true setzen.',
    'finding.page.missing'   => 'Zielseite {target} existiert nicht oder ist ein Entwurf: {entry}',
    'finding.check.noMatch'  => 'Der alte Pfad {source} trifft seinen eigenen Eintrag nicht: {entry}',
    'finding.check.status'   => '{source} antwortet mit {actual} statt {expected}',
    'finding.check.location' => '{source} leitet auf {actual} statt {expected}',
    'finding.check.target'   => 'Ziel {target} antwortet mit {actual} statt 200',
    'finding.check.hop'      => 'Ziel {target} von {entry} wird selbst weitergeleitet',

    'gone.title' => 'Seite entfernt',
    'gone.text'  => 'Diese Seite wurde dauerhaft entfernt.',

    'cli.usage' => "Aufruf:
  hikkoshi check [DATEI] [--lang=en|de] [--resolve-chains] [--allow-host=HOST]... [--strict]
  hikkoshi convert DATEI [--lang=en|de]

check    prüft eine Weiterleitungsliste (Vorgabe: site/config/hikkoshi.php, dann site/config/weiterleitungen.php).
         Exit 0 = keine Fehler, 1 = Fehler (mit --strict auch Warnungen), 2 = Aufruf oder Datei nicht gefunden.
convert  liest Apache-Zeilen Redirect, RedirectMatch und RewriteRule aus einer .htaccess und gibt die
         Hikkoshi-Liste als PHP aus. Ändert nie Dateien. Exit 0 = alles übernommen, 1 = Zeilen übersprungen, 2 = Aufruf.",
    'cli.file'           => 'Prüfe {file}',
    'cli.unknownCommand' => 'Unbekannter Befehl: {command}',
    'cli.fileMissing'    => 'Datei nicht gefunden: {file}',
    'cli.noFile'         => 'Keine Weiterleitungsliste gefunden (versucht: {files}).',
    'cli.ok'             => 'OK: {entries} Einträge, keine Befunde.',
    'cli.summary'        => '{entries} Einträge, {errors} Fehler, {warnings} Warnungen.',
    'cli.skipped'        => 'Zeile {line} übersprungen ({reason}): {text}',
    'cli.convertSummary' => '{entries} Einträge übernommen, {skipped} Zeilen übersprungen.',
    'cli.partial'        => 'Zeile {line} nur für den genauen Pfad übernommen ({reason}): {text}',
    'cli.partialSummary' => '{partial} Zeilen nur für den genauen Pfad übernommen; Einträge für die Pfade darunter ergänzen oder die Regeln in der .htaccess lassen.',

    'reason.pattern'   => 'Muster ist kein fester Pfad',
    'reason.condition' => 'Bedingung ist keine Query-String-Bedingung',
    'reason.target'    => 'Ziel lässt sich nicht übernehmen',
    'reason.status'    => 'Status ist kein Weiterleitungsstatus',
    'reason.flag'      => 'Flag lässt sich nicht übernehmen',
    'reason.duplicate' => 'alter Pfad bereits übernommen',
    'reason.prefix'    => 'Apache leitete auch die Pfade darunter weiter',
];

# Hikkoshi: technische Grundlage für den Datenschutztext

(English version: [PRIVACY.md](PRIVACY.md). Die Optionen heißen `hikkoshi.*`;
die Namen stehen im [README](README.md#options).)

Dieser Baustein beschreibt die Implementierung. Rechtsgrundlage, Interessenabwägung
und die Formulierung müssen vor Veröffentlichung für den konkreten Betrieb fachlich
geprüft und ergänzt werden. Der Text ist keine Rechtsberatung.

## Weiterleitungen alter Adressen — technischer Textbaustein

Wenn Sie eine Seite unter einer früheren Adresse aufrufen, leitet unser Webserver
Sie auf die aktuelle Adresse weiter. Dafür liest der Server den Pfad und die
Abfrage (den Teil nach dem Fragezeichen) der angeforderten Adresse und vergleicht
sie mit unserer Liste alter Adressen. Gibt es einen Treffer, antwortet der Server
mit der neuen Adresse. Ist eine Seite dauerhaft entfernt, antwortet er mit einer
Hinweisseite, die im Text feststeht.

Ihr Browser baut dafür **keine Verbindung zu einem anderen Dienst** auf: Es wird
kein Skript, keine Schriftart, keine Einbettung und kein Bild von einem fremden
Server nachgeladen.

Das eingesetzte Modul setzt **keine Cookies**, verwendet **keinen Browser-Speicher**
(etwa Local Storage oder Session Storage) und erhebt **keine Daten über
Besucherinnen und Besucher** dieser Website. Es speichert nicht, welche Seiten
aufgerufen wurden, und es liest weder Ihre IP-Adresse noch Ihren Browser-Typ aus.

Die Liste enthält nur Adressen unserer Website und die Ziele, zu denen sie führen.
Ausnahme sind Adressen anderer Anbieter, die wir ausdrücklich als Ziel zugelassen
haben. Auch dann stellt der Server selbst keine Verbindung zu diesen Anbietern her;
Ihr Browser folgt der Weiterleitung wie jedem anderen Link.

## Hinweise für den Betreiber

- **Keine Übertragung durch das Modul.** Das Modul überträgt selbst keine Daten an
  Dritte und ruft keine fremden Adressen ab. Hosting und die Zugriffsprotokolle des
  Webservers liegen außerhalb des Moduls und sind in der allgemeinen
  Datenschutzerklärung der Website zu beschreiben, einschließlich der aufgerufenen
  alten Adressen samt Abfrage.
- **Keine personenbezogenen Angaben in der Liste.** Die Liste enthält Adressen der
  Website und Seiten-IDs. Persönliche Daten, etwa Namen in einem Dateinamen, gehören
  dort nicht hinein.
- **Zugelassene fremde Ziele.** Nur Hosts, die in `hikkoshi.allowedHosts` eingetragen
  sind, dürfen Weiterleitungsziel sein. Die Entscheidung trifft allein der Betreiber.
  Die Liste sollte kurz bleiben.
- **Fehlerprotokoll.** Konfigurationsfehler (etwa eine ungültige Weiterleitung oder
  ein Statuscode, den das Modul nicht kennt) werden in das Fehlerprotokoll des Servers
  geschrieben. Die Meldung enthält den betroffenen Listeneintrag und keine
  Besucherdaten.
- **Prüfung in den Tests.** Die Prüfung der Weiterleitungen über den Test
  `hikkoshiCheck()` ruft die Seiten nur bei der Entwicklung und den Tests des Betreibers
  auf, nicht im Betrieb der Website.
- **Keine Speicherung durch das Modul.** Es gibt keine Datenbank, keine Datei und keinen
  Zugangsschlüssel. Die Liste liegt als Konfiguration in der Website, wie alle anderen
  Einstellungen auch.

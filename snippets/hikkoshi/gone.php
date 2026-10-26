<?php
/**
 * The body of a 410 response (SPEC.md §3.5).
 *
 * Receives $title and $text (already translated). Unstyled, only hikkoshi-*
 * classes, every value escaped. A site overrides it with
 * site/snippets/hikkoshi/gone.php.
 */
$title ??= '';
$text ??= '';
$lang = \Hikkoshi\I18n::language();
?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body class="hikkoshi-gone">
<main class="hikkoshi-gone-main">
<h1 class="hikkoshi-gone-title"><?= htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8') ?></h1>
<p class="hikkoshi-gone-text"><?= htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8') ?></p>
</main>
</body>
</html>

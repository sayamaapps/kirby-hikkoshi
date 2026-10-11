<?php

namespace Hikkoshi;

/**
 * Strings of the plugin, Kirby-free. Shared file — owned by the architect
 * (SPEC.md §2). Builders never edit it; a missing key goes into NOTES-<key>.md.
 *
 * The tables live in translations/<language>.php and are also registered as
 * Kirby plugin translations under `hikkoshi.<key>` (index.php), so a site's
 * own 410 snippet can use t('hikkoshi.gone.title'). Hikkoshi itself reads
 * its own tables: a missing Kirby translation must never silently turn a
 * German message English.
 *
 * The language is set once per request (Options::applyLanguage()) or once
 * per CLI run (--lang) and defaults to English. Missing keys fall back to
 * English, then to the key itself.
 */
final class I18n
{
    /** Languages Hikkoshi ships. English is the default and the fallback. */
    public const LANGUAGES = ['en', 'de'];

    private static string $language = 'en';

    /** @var array<string, array<string,string>> */
    private static array $tables = [];

    public static function setLanguage(string $language): void
    {
        self::$language = in_array($language, self::LANGUAGES, true) ? $language : 'en';
    }

    public static function language(): string
    {
        return self::$language;
    }

    /** @return array<string,string> */
    public static function table(string $language): array
    {
        if (!in_array($language, self::LANGUAGES, true)) {
            return [];
        }
        return self::$tables[$language] ??= require dirname(__DIR__) . '/translations/' . $language . '.php';
    }

    /** `{name}` placeholders are replaced with the given values, unescaped. */
    public static function t(string $key, array $values = []): string
    {
        $text = self::table(self::$language)[$key] ?? self::table('en')[$key] ?? $key;
        if ($values === []) {
            return $text;
        }
        $pairs = [];
        foreach ($values as $name => $value) {
            $pairs['{' . $name . '}'] = is_array($value) ? implode(', ', $value) : (string) $value;
        }
        return strtr($text, $pairs);
    }

    /**
     * Text for a terminal or a generated file: control characters are
     * removed (C0 except newline and tab, DEL, and C1 in UTF-8), so an escape
     * sequence from a .htaccess line or a file name cannot rewrite the
     * terminal. Bytes that are not valid UTF-8 become "?" first, so a raw C1
     * byte (0x9B = CSI on 8-bit terminals) cannot slip through either.
     */
    public static function printable(string $text): string
    {
        return (string) preg_replace('/[\x{00}-\x{08}\x{0B}-\x{1F}\x{7F}-\x{9F}]/u', '', mb_scrub($text, 'UTF-8'));
    }

    /**
     * A config entry for messages: `'old' => 'new'`, as the pre-1.0 plugin
     * printed it (var_export of key and value), so existing expectations hold.
     * Arrays are printed on one line in short syntax: `['to' => 'x', 'status' => 302]`.
     */
    public static function entry(mixed $key, mixed $value): string
    {
        return self::export($key) . ' => ' . self::export($value);
    }

    /** var_export for scalars and null, one-line short array syntax for arrays. */
    public static function export(mixed $value): string
    {
        if (!is_array($value)) {
            return var_export($value, true);
        }
        $parts = [];
        $list = array_is_list($value);
        foreach ($value as $k => $v) {
            $parts[] = ($list ? '' : self::export($k) . ' => ') . self::export($v);
        }
        return '[' . implode(', ', $parts) . ']';
    }
}

<?php

namespace App\Parsing\Support;

use Normalizer;

class TextNormalizer
{
    public static function nfc(string $text): string
    {
        return Normalizer::normalize($text, Normalizer::FORM_C) ?: $text;
    }

    /**
     * Convert an HTML body into plain text: one line per block/table row,
     * cells separated by a single space, whitespace collapsed.
     */
    public static function htmlToText(string $html): string
    {
        $html = preg_replace('#<(head|style|script|title)\b[^>]*>.*?</\1>#is', ' ', $html);
        $html = preg_replace('#<!--.*?-->#s', ' ', $html);
        $html = preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = preg_replace('#</(p|div|tr|li|h[1-6]|table|tbody|thead)>#i', "\n", $html);
        $html = preg_replace('#<(td|th)\b[^>]*>#i', ' ', $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return self::clean($text);
    }

    public static function clean(string $text): string
    {
        $text = self::nfc($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\x{00A0}\x{2007}\x{202F}\x{200B}\t ]+/u', ' ', $text);

        $lines = array_map('trim', explode("\n", $text));

        return implode("\n", array_values(array_filter($lines, fn (string $line) => $line !== '')));
    }

    /** Lowercase, strip Vietnamese diacritics, collapse whitespace. Used for rule matching. */
    public static function fold(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        $text = Normalizer::normalize(mb_strtolower($text, 'UTF-8'), Normalizer::FORM_D) ?: $text;
        $text = preg_replace('/\p{Mn}+/u', '', $text);
        $text = str_replace('đ', 'd', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}

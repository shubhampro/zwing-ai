<?php

namespace App\Support;

use Illuminate\Support\Str;

class SafeHtml
{
    private const ALLOWED = '<p><br><b><strong><i><em><u><ul><ol><li><a><div><span><h1><h2><h3><h4><blockquote><pre><code><table><thead><tbody><tr><td><th>';

    public static function from(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        if (! str_contains($value, '<')) {
            return nl2br(e($value), false);
        }

        $html = preg_replace('/<(script|style|iframe|object|embed|link|meta|form)[^>]*>.*?<\/\1>/is', '', $value) ?? $value;
        $html = strip_tags($html, self::ALLOWED);
        $html = preg_replace('/\s(?:on\w+|style|xmlns|formaction)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/href\s*=\s*([\'"])\s*javascript:[^\'"]*\1/i', 'href="#"', $html) ?? $html;

        return $html === '' ? null : $html;
    }

    public static function plain(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $value = preg_replace('/<(script|style|iframe|object|embed|link|meta|form)[^>]*>.*?<\/\1>/is', '', $value) ?? $value;
        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) Str::of($text)->squish();

        return $text === '' ? null : $text;
    }
}

<?php
/**
 * TaskFlow — mbstring compatibility layer
 * -------------------------------------------------------------
 * mbstring is enabled by default on XAMPP, MAMP, WAMP and virtually every
 * cPanel host. This file keeps TaskFlow working on minimal PHP builds by
 * providing safe fallbacks when the extension is missing.
 */

if (!function_exists('mb_internal_encoding')) {
    function mb_internal_encoding($encoding = null) { return true; }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen($string, $encoding = null)
    {
        $string = (string)$string;
        if (function_exists('iconv_strlen')) {
            $len = @iconv_strlen($string, 'UTF-8');
            if ($len !== false) { return $len; }
        }
        return (int)preg_match_all('/./us', $string);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr($string, $start, $length = null, $encoding = null)
    {
        $string = (string)$string;
        if (function_exists('iconv_substr')) {
            $out = $length === null
                ? @iconv_substr($string, $start, null, 'UTF-8')
                : @iconv_substr($string, $start, $length, 'UTF-8');
            if ($out !== false) { return $out; }
        }
        preg_match_all('/./us', $string, $chars);
        $slice = $length === null
            ? array_slice($chars[0], $start)
            : array_slice($chars[0], $start, $length);
        return implode('', $slice);
    }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($string, $encoding = null) { return strtoupper((string)$string); }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($string, $encoding = null) { return strtolower((string)$string); }
}
if (!function_exists('mb_strpos')) {
    function mb_strpos($haystack, $needle, $offset = 0, $encoding = null) { return strpos((string)$haystack, (string)$needle, $offset); }
}
if (!function_exists('mb_convert_encoding')) {
    function mb_convert_encoding($string, $to = 'UTF-8', $from = null) { return (string)$string; }
}

/** True when the real mbstring extension is available. */
function taskflow_has_mbstring(): bool
{
    return extension_loaded('mbstring');
}

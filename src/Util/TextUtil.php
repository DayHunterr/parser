<?php

namespace App\Util;

class TextUtil
{
    /**
     * Returned string without special symbols.
     *
     * @param string|null $str
     *
     * @return string|null
     */
    public static function removeSpecialSymbols(?string $str): ?string
    {
        $symbols = ["'",'"','/', ':', '!', '-', ',', '.', '&', '*', '^', '?', '@', '#', '$', '%', '(', ')', ';', '{', '}', '[', ']', '<', '>', '~', '`', '+', '=', '_', '№', '|'];

        $clearStr = str_replace($symbols, '', $str);
        $clearStr = preg_replace('/\s+/', ' ', $clearStr);

        return self::trimOrNull($clearStr);
    }

    /**
     * Removes all symbols, that are not numbers.
     *
     * @param string|null $string
     * @return string|null
     */
    public static function removeChars(?string $string): ?string
    {
        return $string
            ? self::trimOrNull(preg_replace('/\D+/', '', $string))
            : null;
    }

    /**
     * Returns true if some of <code>needles<code> is in <code>haystack<code>,
     * and false in other case.
     *
     * @param string $haystack
     * @param array $needles
     * @param int $offset
     * @return bool
     */
    public static function isStringsPos(string $haystack, array $needles, int $offset = 0): bool
    {
        foreach($needles as $needle) {
            if(mb_stripos($haystack, trim($needle), $offset) !== false){
                return true;
            }
        }
        return false;
    }

    /**
     * Removes all string separators symbols (\n\t\r).
     *
     * @param string|null $string
     * @return string|null
     */
    public static function removeStringSeparators(?string $string): ?string
    {
        if($string){
            $string = self::replaceMultipleSpaces($string);
            return self::trimOrNull(preg_replace('/(\t)|(\n)|(\r)/', '', $string));
        }
        return null;
    }

    /**
     * @param string|null $string
     *
     * @return string|null
     */
    public static function decodeString(?string $string): ?string
    {
        return !$string
            ? $string
            : self::unicodeToUTF8(urldecode(html_entity_decode($string)));
    }

    /**
     * @param string|null $string
     *
     * @return string
     */
    public static function unicodeToUTF8(?string $string): ?string
    {
        if(!$string){
            return null;
        }

        return preg_replace_callback('/\\\\u([\da-fA-F]{4})/', static function ($match) use ($string) {
            $unicodeStr = $match[1] ?? null;

            if(!$unicodeStr){
                return $string;
            }

            return mb_convert_encoding(pack('H*', $unicodeStr), 'UTF-8', 'UCS-2BE');
        }, $string);
    }

    /**
     * @param string|null $string
     *
     * @return string|null
     */
    public static function trimOrNull(?string $string): ?string
    {
        return !empty($string)
            ? preg_replace('/(^\s+)|(\s+$)/', '', $string)
            : null;
    }

    /**
     * Clear email address
     *
     * @param string|null $email
     *
     * @return string|null
     */
    public static function clearEmail(?string $email): ?string
    {
        return $email ? str_replace('mailto:', '', $email) : null;
    }

    /**
     * @param string|null $string
     *
     * @return string|null
     */
    public static function replaceMultipleSpaces(?string $string): ?string
    {
        return preg_replace('/\s+/', ' ', $string);
    }

    /**
     * @param string|null $string
     *
     * @return string|null
     */
    public static function clearString(?string $string): ?string
    {
        if(!$string){
            return null;
        }

        $clearString = self::removeStringSeparators($string);
        $clearString = self::replaceMultipleSpaces($clearString);
        $clearString = self::decodeString($clearString);

        return self::trimOrNull($clearString);
    }
}
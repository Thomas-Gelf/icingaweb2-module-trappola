<?php

namespace Icinga\Module\Trappola;

class Util
{
    public static function hex2bin($hex)
    {
        return pack('H*' , $hex);
    }

    public static function bin2hex($bin)
    {
        return bin2hex($hex);
    }

    public static function unescapeValue($value)
    {
        return str_replace(
            array('\\n', '\\r', '\\t'),
            array("\n", "\r", "\t"),
            $value
        );
    }

    public static function escapeValue($value)
    {
        // Automatic: " -> \" ??
        return str_replace(
            array("\n", "\r", "\t"),
            array('\\n', '\\r', '\\t'),
            $value
        );
    }
}

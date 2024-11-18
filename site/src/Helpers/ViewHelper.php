<?php

namespace App\Helpers;

class ViewHelper {


    // get filemtime of files in /public
    public static function filemtime($filePath) {
        return filemtime(__SITE__ . '/public/' . $filePath);
    }


    public static function removeEmoji($str) {
        return preg_replace('/[\x{1F100}-\x{1F1FF}' .
            '\x{1F300}-\x{1F5FF}' .
            '\x{1F600}-\x{1F64F}' .
            '\x{1F680}-\x{1F6FF}' .
            '\x{1F700}-\x{1F77F}' .
            '\x{1F780}-\x{1F7FF}' .
            '\x{1F800}-\x{1F8FF}' .
            '\x{1F900}-\x{1F9FF}' .
            '\x{2600}-\x{26FF}' .    // Miscellaneous Symbols (includes ⚡️ and 💡)
            '\x{2700}-\x{27BF}' .    // Dingbats
            '\x{FE00}-\x{FE0F}' .    // Variation Selectors
            '\x{1F000}-\x{1FAFF}]/u', '', $str);
    }


}

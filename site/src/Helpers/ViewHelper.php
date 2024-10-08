<?php

namespace App\Helpers;

class ViewHelper {


    // get filemtime of files in /public
    public static function filemtime($filePath) {
        return filemtime(__SITE__ . '/public/' . $filePath);
    }



}

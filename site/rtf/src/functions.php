<?php

// Global Helper functions

/**
 * Debug output
 * @param $data some data
 * @param $varDump do var_dump instead of print_r? default print_r
 * @return void
 */

function dd($data, $varDump = false) {
    if (php_sapi_name() == 'cli') {
        echo "\n";
    } else {
        echo '<pre style="color: #fff; background: #1e1e1e; padding: 10px; border-radius: 8px; font-size: 12px; white-space: pre-wrap;">';
    }

    $trace = debug_backtrace();
    $caller = $trace[1];

    if (isset($caller['function'])) {
        echo "-- In {$caller['function']}():\n";
    }

    if ($varDump) {
        var_dump($data);
    } else {
        print_r($data);
    }
    if (php_sapi_name() == 'cli') {
        echo "\n";
    } else {
        echo '</pre>';
    }
}


/**
 * Cut of string, remove the last word so there are no cut-off words
 * @param $content
 * @param $maxLength
 * @param $trimMarker
 * @return string
 */
function shortenString($content, $maxLength = 100, $trimMarker = " ...") {

    if (strlen($content) <= $maxLength) {
        return $content;
    }

    $str = mb_strimwidth(strip_tags($content), 0, $maxLength, '');
    $lastSpace = mb_strrpos($str, ' ');
    if ($lastSpace !== false) {
        $str = mb_substr($str, 0, $lastSpace);
    }
    return $str . $trimMarker;
}


/**
 * Get reading time in minutes
 * @param $content
 * @return int
 */
function readingTime($content) {
    $wordCount = str_word_count(strip_tags($content));
    $readingTime = ceil($wordCount / 200);
    return $readingTime;
}

/**
 * Formats a date string using current locale
 * @param $dateTime like "2024-01-05 22:54:08"
 * @param string $format default output: "4. Dezember 2023 13:37"
 * @return void
 */
function formatDate($dateTime, $format = "d. MMMM YYYY") {
    global $app;
    $locale = 'en_US';
    $localization = $app->container->get('localization');
    if ($localization->locale) {
        $locale = $localization->locale;
    }

    $date = new \DateTime($dateTime);

    // Create an IntlDateFormatter
    $formatter = new \IntlDateFormatter(
        $locale,
        \IntlDateFormatter::NONE, // Date type
        \IntlDateFormatter::NONE, // Time type
        $date->getTimezone(), // Time zone
        \IntlDateFormatter::GREGORIAN, // Calendar
        $format
    );

    return $formatter->format($date);
}


/**
 * Formats a date time string using current locale
 * @param $dateTime like "2024-01-05 22:54:08"
 * @param string $format default output: "4. Dezember 2023 13:37"
 * @return void
 */
function formatDateTime($dateTime, $format = "d. MMMM YYYY HH:mm") {
    global $app;
    $locale = 'en_US';


    if ($app->container->has('localization')) {
        $localization = $app->container->get('localization');
        if ($localization->locale) {
            $locale = $localization->locale;
        }
    }


    $date = new \DateTime($dateTime);

    // Create an IntlDateFormatter
    $formatter = new \IntlDateFormatter(
        $locale,
        \IntlDateFormatter::NONE, // Date type
        \IntlDateFormatter::NONE, // Time type
        $date->getTimezone(), // Time zone
        \IntlDateFormatter::GREGORIAN, // Calendar
        $format
    );

    return $formatter->format($date);
}

// thx to https://stackoverflow.com/a/2510459/1191375
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);
    // $bytes /= (1 << (10 * $pow));

    return round($bytes, $precision) . $units[$pow];
}

/**
 * return string with only letters, numbers, - and _
 * @param $text
 * @return string
 */
function slugify($text) {
    // replace non letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);

    // transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

    // remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);

    // trim
    $text = trim($text, '-');

    // remove duplicate -
    $text = preg_replace('~-+~', '-', $text);

    // lowercase
    $text = strtolower($text);

    return $text;
}

/**
 * Sanitize file path to only valid characters
 * @param $path /like/this/
 * @return string returns path with only valid linux/macOS/windows path characters
 */
function sanitizePath($path) {
    $path = preg_replace('/[^a-zA-Z0-9\/\-\_]/', '', $path);
    $path = preg_replace('/\/+/', '/', $path);
    return $path;
}

/**
 * Sanitize filename to only valid characters
 * @param $filename
 * @return string returns filename with only valid linux/macOS/windows filename characters
 */
function sanitizeFilename($filename) {
    $filename = strtolower(preg_replace('/[^a-zA-Z0-9\-\_\.]/', '', str_replace(" ", "-", $filename)));
    return $filename;
}

// escape html
function h($str) {
    if (is_null($str)) {
        $str = '';
    }
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function post($key) {
    return isset($_POST['key']) ? $_POST['key'] : null;
}

/**
 * Loads icon from assets/icons/*.svg, returns it
 * @param $name
 * @return string
 */
function icon($name) {
    $path = __DIR__ . "/../../public/img/icons/$name.svg";
    if (file_exists($path)) {
        return file_get_contents($path);
    }
    return "";
}

function getGravatarURL($email) {
    $hash = md5(strtolower(trim($email)));
    return "https://www.gravatar.com/avatar/$hash";
}

/**
 * Format duration as days, hours, minutes, seconds like "5m 51s". without DateTime
 * @param $seconds
 * @return string
 */
function formatDuration($seconds) {
    return sprintf('%2dh %2dm', $seconds/3600,
        (int)($seconds/60)%60,
        $seconds % 60);
}



/**
 * Translate text
 * @param $str
 * @return string in current language
 */
function t($key, $dataOrSubkey = [], $data = []) {
    global $app;
    $localization = $app->container->get('localization');
    return $localization->translate($key, $dataOrSubkey, $data);
}

function csrfToken() { ?>
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
<?php
}
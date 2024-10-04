<?php

define('BASEPATH', dirname(__DIR__));

$config = require BASEPATH . '/classes/Base.php';
$config = require BASEPATH . '/config/config.php';

// escape html
function h($str) {
    if (is_null($str)) {
        $str = '';
    }
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

class Web extends Base {

    
    public function __construct($config) {
        parent::__construct($config);
    }

    public function run() {
        //$this->auth(); // bail if no auth

        /*
        frontend plan:

        info to display somehow/somewhere:
        - logo
        - name of current movie
        - link to infos for current movie (imdb, tubi, archive.org, trailer, etc)
        - list of movies with date, duration
        - toots for current movie
        - play button and timeline

         */

        // simple router
        // request to "/" -> show list of movies
        // request to "/movie/{alphanumeric-plus-dashes}" -> show movie with name == {alphanumeric-plus-dashes}

        $uri = $_SERVER['REQUEST_URI'];
        // use preg_match for simple router and extract movie name

        if ($uri == '/') {
            $title = "Movies";
            require BASEPATH . '/pages/movies.php';

            // is /movie/{alphanumeric-plus-dashes} ?
        } else if (preg_match('/^\/movie\/([a-zA-Z0-9-]+)$/', $uri, $matches)) {
            $movieName = $matches[1];
            $this->renderHeader($movieName);
            $this->renderMovie($movieName);
            $this->renderFooter();
        } else {
            // 404
            header("HTTP/1.0 404 Not Found");
            $this->renderHeader('404');
            echo "404 Not Found";
            $this->renderFooter();
        }


        


    }

    public function renderHeader($title = '') {
        $filemtimeCSS = filemtime(BASEPATH . '/public/css/main.css');
        $filemtimeJS = filemtime(BASEPATH . '/public/js/main.js');
        ?>
        <html>
        <head>
            <title>#monsterdon replay <?= $title ? '&middot; ' . $title : '';?></title>
            <link rel="stylesheet" type="text/css" href="css/main.css?v=<?php echo $filemtimeCSS;?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <script src="/js/main.js?v=<?php echo $filemtimeJS;?>"></script>

            <link rel="icon" type="image/x-icon" href="/img/favicon.ico">
            <link rel="apple-touch-icon" sizes="256x256" href="/img/icon-256.png">
        </head>
        <body>
        <?php
    }

    public function renderFooter() {
        ?>
        </body>
        </html>
        <?php
    }


}

$web = new Web($config);
$web->run();


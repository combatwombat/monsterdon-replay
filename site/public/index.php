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


        

        // otherwise, render whole page
        $this->render();

    }

    public function render() {
        $filemtimeCSS = filemtime(BASEPATH . '/public/css/main.css');
        $filemtimeJS = filemtime(BASEPATH . '/public/js/main.js');

        ?>
<html>
<head>
    <title>#monsterdon replay</title>
    <link rel="stylesheet" type="text/css" href="css/main.css?v=<?php echo $filemtimeCSS;?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="/js/main.js?v=<?php echo $filemtimeJS;?>"></script>

    <link rel="icon" type="image/x-icon" href="/img/favicon.ico">

    <!-- apple icon 256x256 -->
    <link rel="apple-touch-icon" sizes="256x256" href="/img/icon-256.png">

</head>
<body>
rawr 🦖
</body>
</html>
<?php
    }

}

$web = new Web($config);
$web->run();


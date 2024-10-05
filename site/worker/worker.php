<?php

define('BASEPATH', dirname(__DIR__));

$config = require BASEPATH . '/config/config.php';
require BASEPATH . '/classes/Base.php';
require BASEPATH . '/classes/SaveToots.php';

$saveToots = new SaveToots($config);

while (true) {
    $saveToots->run($config['mastodon']['hashtag'], $config['mastodon']['startDateTime']);
}

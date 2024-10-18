<?php
use App\Helpers\ViewHelper;

$filemtimeCSS = ViewHelper::filemtime('css/main.css');
$filemtimeJS = ViewHelper::filemtime('js/main.js');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>monsterdon replay<?= $title ? ' &middot; ' . h($title) : '';?></title>
    <link rel="stylesheet" type="text/css" href="/css/main.css?v=<?php echo $filemtimeCSS;?>">
    <script src="/js/main.min.js?v=<?php echo $filemtimeJS;?>"></script>

    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#131415"/>
    <meta name="description" content="Missed the #monsterdon watch party on Mastodon? Replay the toots here.">

    <meta property="og:image" content="https://monsterdon-replay.gerlach.dev/img/og-image.png" />

    <link rel="icon" type="image/x-icon" href="/img/favicon.ico">
    <link rel="apple-touch-icon" sizes="256x256" href="/img/icon.png">
</head>
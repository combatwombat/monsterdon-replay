<?php

namespace App;

require __DIR__ . "/../../vendor/autoload.php";

use App\Helpers\TMDB;
use RTF\RTF;

$app = new RTF();
$app->container->set('config', new \RTF\Config());
$app->container->set('helper', new \RTF\Helper($app->container));
$app->container->set('db', new \RTF\DB($app->container->config('db')));
$app->container->set('auth', new \RTF\Auth($app->container, "http"));
$app->container->set('view', new \RTF\View($app->container));
$app->container->set('tmdb', new TMDB($app->container));

date_default_timezone_set($app->container->get('config')('timezone'));

// Routing

$app->get("/", "Movies@list");

$app->get("/backstage/movies", "BackstageMovies@list");
$app->post("/backstage/movies", "BackstageMovies@new");
$app->delete("/backstage/movies/{id}", "BackstageMovies@delete");
$app->post("/backstage/movies/{id}", "BackstageMovies@edit");

$app->get("/privacy", function() {
    $this->view("privacy", ['bodyClass' => 'page-privacy']);
});

$app->get("/api/toots/{slug}", "Movies@tootsJSON");

$app->get("/{slug}", "Movies@show");

$app->onError(404, function() {
    $this->view("404", ['bodyClass' => 'error-404']);
});


// CLI commands

// save toot worker.
// usage:
// php site/public/index.php save_toots
$app->cli("save_toots", function() {
    $saveToots = new Workers\SaveToots($this->container);
    while (true) {
        $saveToots->run();
    }
});

$app->run();
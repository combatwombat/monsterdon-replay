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

$app->get("/privacy-info", function() {
    $this->view("privacy-info", ['header' => ['bodyClass' => 'page-privacy', 'title' => 'Privacy Policy & Info']]);
});

$app->get("/api/toots/{slug}", "Movies@tootsJSON");

$app->get("/{slug}", "Movies@show");

$app->onError(404, function() {
    $this->view("404", ['header' => ['bodyClass' => 'error-404', 'title' => 'Movie not found']]);
});


// CLI commands

// save toot worker.
// usage:
// php site/public/index.php save_toots
$app->cli("save_toots", function() {
    $saveToots = new Workers\SaveToots($this->container);
    $c = 1;
    while (true) {

        // every 5 to 60 minutes (depending on wait time below), fetch all toots from now 'till yesterday and don't
        // stop at existing ones. that way we catch some stragglers that where federated late.
        if ($c % 60 === 0) {

            $now = new \DateTime();
            $now->sub(new \DateInterval('P1D'));
            $now = $now->format('Y-m-d H:i:s');

            $ret = $saveToots->run(false, $now);

            $c = 1;
        } else {

            // download all toots until we reach an existing one or the config.mastodon.oldTootDateTime
            $ret = $saveToots->run();
            $c++;
        }


        if ($ret['newTootCount'] > 0) {
            sleep(5);
        } else {
            if ($ret['error']) {
                sleep(10);
            } else {
                sleep(60);
            }
        }
    }
});

// go through all toots and save media
$app->cli("save_toot_media", function() {
    $saveToots = new Workers\SaveToots($this->container);
    $saveToots->saveMediaForExistingToots();
});

$app->run();
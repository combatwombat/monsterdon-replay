<?php

namespace App;

require __DIR__ . "/../../vendor/autoload.php";

$app = new \RTF\RTF();
$app->container->set('config', new \RTF\Config());
$app->container->set('helper', new \RTF\Helper($app->container));
$app->container->set('db', new \RTF\DB($app->container->config('db')));
$app->container->set('auth', new \RTF\Auth($app->container, "http"));
$app->container->set('view', new \RTF\View($app->container));
$app->container->set('tmdb', new App\Helpers\TMDB($app->container));

date_default_timezone_set($app->container->get('config')('timezone'));


// Routes

$app->get("/", "Movies@list");

$app->get("/about", function() {
    $this->view("about", ['header' => ['bodyClass' => 'page-text page-about', 'title' => 'About']]);
});

$app->get("/privacy", function() {
    $this->view("privacy", ['header' => ['bodyClass' => 'page-text page-privacy', 'title' => 'Privacy Policy & Info']]);
});

$app->get("/api/toots/{slug}", "Movies@tootsJSON");

$app->get("/backstage/movies", "BackstageMovies@list");
$app->post("/backstage/movies", "BackstageMovies@new");
$app->delete("/backstage/movies/{id}", "BackstageMovies@delete");
$app->post("/backstage/movies/{id}", "BackstageMovies@edit");

# assuming no monster movies are called "about" or "privacy"...
$app->get("/{slug}", "Movies@show");

$app->onError(404, function() {
    $this->view("404", ['header' => ['bodyClass' => 'error-404', 'title' => 'Movie not found']]);
});


// CLI commands

// save toot worker.
// usage:
// php site/public/index.php save_toots // fetch all toots up until config.mastodon.oldTootDateTime or an existing toot. occasionally catch up on older toots
// php site/public/index.php save_toots -catchup 6 // start with catching up. fetch toots until {num} days in the past. don't stop on existing toots
$app->cli("save_toots {catchup}", function($catchup = false) {
    $saveToots = new Workers\SaveToots($this->container);

    // re-fetch older toots every x seconds
    $catchUpInterval = 3600 * 6;

    // timestamp. when did we last re-fetch older toots?
    $lastCatchUpDateTime = time();

    // when catching up, how many days to fetch back?
    $catchUpDays = $catchup ? (int)$catchup : 6;

    while (true) {

        // every $catchUpInterval seconds, fetch all toots from now until $catchUpDays ago and don't
        // stop at existing ones. that way we catch some stragglers that where federated late.
        if ($catchup || time() - $lastCatchUpDateTime > $catchUpInterval) {

            $this->log("catching up until ".$catchUpDays." days ago");

            $now = new \DateTime();
            $now->sub(new \DateInterval('P'.((string)$catchUpDays).'D'));
            $now = $now->format('Y-m-d H:i:s');

            $ret = $saveToots->run(false, $now);
            $catchup = false;
        } else {

            $this->log("not catching up");

            // download all toots until we reach an existing one or the config.mastodon.oldTootDateTime
            $ret = $saveToots->run();
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

// go through all toots and save their media
$app->cli("save_toot_media", function() {
    $saveToots = new Workers\SaveToots($this->container);
    $saveToots->saveMediaForExistingToots();
});

// call api for each movie to rebuild toot cache
$app->cli("rebuild_movie_cache", function() {
    $movies = $this->db->getAll("movies");
    $baseURL = "https://" . $this->config("domain") . "/api/toots/";
    $movieCount = count($movies);
    $c = 1;
    foreach ($movies as $movie) {
        $this->log("rebuilding cache for movie " . $c . "/" . $movieCount . ": " . $movie['slug']);
        $this->db->deleteCacheByPrefix("toots-" . $movie['slug']);
        file_get_contents($baseURL . $movie['slug']);
        $c++;
    }
});

$app->run();
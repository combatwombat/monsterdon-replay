<?php

namespace App;

require __DIR__ . "/../../vendor/autoload.php";

$app = new \RTF\RTF();
$app->container->set('config', new \RTF\Config());
$app->container->set('helper', new \RTF\Helper($app->container));
$app->container->set('db', new \RTF\DB($app->container->config('db')));
$app->container->set('auth', new \RTF\Auth($app->container, "http"));
$app->container->set('view', new \RTF\View($app->container));
$app->container->set('tmdb', new Helpers\TMDB($app->container));
$app->container->set('subtitles', new Helpers\Subtitles($app->container));

date_default_timezone_set($app->container->get('config')('timezone'));


// Routes

$app->get("/", "Movies@list");
$app->get("/api/toots/{slug}", "Movies@tootsJSON");


$app->get("/about", function() {
    $this->view("about", ['header' => ['bodyClass' => 'page-text page-about', 'title' => 'About']]);
});
$app->get("/privacy", function() {
    $this->view("privacy", ['header' => ['bodyClass' => 'page-text page-privacy', 'title' => 'Privacy Policy & Info']]);
});


$app->get("/backstage/movies", "BackstageMovies@list");
$app->post("/backstage/movies", "BackstageMovies@new");
$app->delete("/backstage/movies/{id}", "BackstageMovies@delete");
$app->post("/backstage/movies/{id}", "BackstageMovies@edit");




// get list of authors, ordered by toot count
$app->get("/stats/authors", function() {
    $this->auth(); // heavy operation. members-only
    $toots = $this->db->getAll("toots");

    $authors = [];
    foreach ($toots as $toot) {
        $data = json_decode($toot['data'], true);

        $acct = $data['account']['acct'];
        $displayName = $data['account']['display_name'];

        // is acct part of the keys of the $authors array?
        if (!array_key_exists($acct, $authors)) {
            $authors[$acct] = [
                'acct' => $acct,
                'displayName' => $displayName,
                'tootCount' => 1
            ];
        } else {
            $authors[$acct]['tootCount']++;
        }
    }

    // sort by toot count
    usort($authors, function($a, $b) {
        return $b['tootCount'] - $a['tootCount'];
    });

    echo '<h1>Toot Ranking</h1>';

    echo "Authors: " . count($authors) . "<br>";

    echo '<ol>';
    foreach ($authors as $author) {
        echo '<li>' . $author['displayName'] . " (" . $author['acct'] . "): " . $author['tootCount'] . "</li>";
    }
    echo '</ol>';

});

// display movies and their date and toot count. also as csv with ?csv option
$app->get("/stats/movies", function() {

    $movies = $this->db->fetchAll("SELECT * FROM movies ORDER BY start_datetime ASC");

    $csv = "Title;Year;Duration;Watched On;Toots\n";

    foreach ($movies as $movie) {
        $csvLine = $movie['title'] . ";" . substr($movie['release_date'], 0, 4) . ";" . trim(formatDuration($movie['duration'])) . ";" . substr($movie['start_datetime'], 0, 10) . ";" . $movie['toot_count'] . "\n";

        $csv .= $csvLine;
    }

    if (isset($_GET['csv'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="movies.csv"');
        echo $csv;
        exit;
    }

    echo '<pre>';
    echo $csv;
    echo '</pre>';
});

// show some select toots to find older monsterdon movies
/*
$app->get("/temp", function() {
    $query = "SELECT    id,    created_at,    JSON_UNQUOTE(JSON_EXTRACT(data, '$.uri')) AS uri, JSON_UNQUOTE(JSON_EXTRACT(data, '$.account.acct')) AS account, JSON_UNQUOTE(JSON_EXTRACT(data, '$.account.display_name')) AS name,    JSON_UNQUOTE(JSON_EXTRACT(data, '$.content')) AS content FROM    toots WHERE    created_at < \"2022-06-11 01:00:19\" ORDER BY    created_at DESC;";

    $toots = $this->db->fetchAll($query);

    foreach ($toots as $toot) {
        ?>
<div style="border-bottom: 1px solid #ccc; margin: 0 auto; max-width: 600px;" data-id="<?= $toot['id'];?>">
    <div><b><?= $toot['created_at'];?></b> &middot; <?= $toot['name'];?> &middot; <?= $toot['account'];?> &middot; <a href="<?= $toot['uri'];?>"><?= $toot['id'];?></a></div>
    <div>
        <?= $toot['content'];?>
    </div>
</div>

<?php
    }
});
*/


$app->get("/{slug}/subtitles", "Movies@subtitles");


# assuming no monster movies are called "about" or "privacy"...
$app->get("/{slug}", "Movies@show");

$app->onError(404, function() {
    $this->view("404", ['header' => ['bodyClass' => 'error-404', 'title' => 'Movie not found']]);
});




// CLI commands

/**
 * - Saves newest toots. The default
 * - Occasionally re-saves older toots
 * - Seldom re-fetches all toots, possibly deleting ones that have not been found on Mastodon for a while
 *
 * Usage:
 * php site/public/index.php save_toots # saves newest toots first, then goes on as usual
 * php site/public/index.php save_toots -first catchup # re-fetches older toots first
 * php site/public/index.php save_toots -first resave # re-fetches all toots first
 */
$app->cli("save_toots {first?}", function($first = 'catchup') {

    $tootsWorker = new Workers\TootsWorker($this->container);

    // re-fetch older toots every 6 hours
    $catchUpInterval = 3600 * 6;

    // timestamp. when did we last re-fetch older toots?
    $lastCatchUpTime = time();

    // when catching up, how many seconds to fetch back?
    $catchUpSeconds = 3600 * 24 * 5; // 5 days

    // re-fetch all toots every 7 days
    $resaveInterval = 3600 * 24 * 7;

    // timestamp. when did we last re-fetch all toots?
    $lastResaveTime = time();

    $rebuildCacheSecondsPadding = 3600 * 24; // 1 day


    while (true) {

        $now = time();

        // catching up with stragglers?
        if ($first == "catchup" || $now > $lastCatchUpTime + $catchUpInterval) {

            $oldestTootDateTime = new \DateTime();
            $oldestTootDateTime->sub(new \DateInterval("PT" . $catchUpSeconds . "S"));

            $res = $tootsWorker->saveTootsUntil($oldestTootDateTime->format("Y-m-d H:i:s"));
            $lastCatchUpTime = $now;

            $oldestTootDateTime->sub(new \DateInterval("PT" . $rebuildCacheSecondsPadding . "S"));

            $tootsWorker->rebuildMovieCache($oldestTootDateTime->format("Y-m-d H:i:s"));

            $first = null;

        // or re-save all toots, updating them?
        } else if ($first == "resave" || $now > $lastResaveTime + $resaveInterval) {

            $res = $tootsWorker->resaveAllToots(false);
            $lastResaveTime = $now;

            $tootsWorker->rebuildMovieCache();

            $first = null;

        // or just save new toots?
        } else {

            $res = $tootsWorker->saveNewToots();

            if ($res['newTootCount'] > 0 || $res['updatedTootCount'] > 0) {

                // create datetime from lastModifiedTootDatetime string
                $oldestTootDateTime = new \DateTime($res['lastModifiedTootDatetime']);
                $oldestTootDateTime->sub(new \DateInterval("PT" . $rebuildCacheSecondsPadding . "S"));

                $tootsWorker->rebuildMovieCache($oldestTootDateTime->format("Y-m-d H:i:s"));
            }


        }

        // wait less if there are new toots
        if ($res['newTootCount'] > 0) {
            sleep(5);
        } else {
            if ($res['error']) {
                sleep(60);
            } else {
                sleep(60 * 5); // 5 minutes
            }
        }

    }
});

// go through all toots and save their media
$app->cli("save_toot_media", function() {
    $tootsWorker = new Workers\TootsWorker($this->container);
    $tootsWorker->saveMediaForExistingToots();
});

// rebuild the movie cache, update toots_count
$app->cli("rebuild_movie_cache", function() {
    $tootsWorker = new Workers\TootsWorker($this->container);
    $tootsWorker->rebuildMovieCache();
});


$app->run();
<?php

namespace App\Workers;

use RTF\Base;

class SaveToots extends Base {

    public $imageDownloadWaitTime = 2;
    public $movies;

    public function __construct($container) {
        parent::setContainer($container);
    }

    public function saveMediaForExistingToots() {
        $toots = $this->db->fetchAll("SELECT * FROM toots WHERE JSON_UNQUOTE(JSON_EXTRACT(data, '$.media_attachments')) <> \"[]\";");


        $tootCount = count($toots);
        $this->log("Saving media for " . $tootCount . " toots");

        $c = 0;
        foreach ($toots as $toot) {
            $this->log("Saving media for toot " . $toot['id'] . " (" . $c . "/" . $tootCount . ")");
            $tootData = json_decode($toot['data'], true);
            foreach ($tootData['media_attachments'] as $media) {
                $this->saveMedia($media);
            }
            $c++;
        }

    }

    public function saveAvatarsForExistingToots() {
        $toots = $this->db->getAll("toots");
        $tootCount = count($toots);

        $c = 0;
        foreach ($toots as $toot) {
            $this->log("Saving avatar image for toot " . $toot['id'] . " (" . $c . "/" . $tootCount . ")");
            $tootData = json_decode($toot['data'], true);
            $this->saveAvatarImage($tootData['account']['id'], $tootData['account']['avatar']);
            $c++;
        }
    }

    /**
     * Fetch toots from Mastodon and save them to the database.
     * The Mastodon API only provides a list opf toots ordered by date, newest first, or all toots that come after
     * a specific toot id (max_id). So, we can only work ourselves backwards until we reach a stop criterion.
     * @param $stopAtExistingToot boolean stop fetching toots if we reach one we already have in the database
     * @param $oldestTootDateTime string toot-datetime at which to stop fetching toots
     * @return array with 'newTootCount' (int), 'error' (bool)
     * @throws \DateMalformedIntervalStringException
     * @throws \DateMalformedStringException
     */
    public function run($stopAtExistingToot = true, $oldestTootDateTime = null) {

        /*
        New idea. Two modes:
        1. Like before. Get all the newest toots and save them. Stop at existing toot or oldestTootDateTime.
        2. Get all the toots. Two new database columns: "visible" and "is_on_mastodon"
           Before fetching all toots: visible = 1, is_on_mastodon = 1, last_found_on_mastodon = datetime, for all toots
           Then we fetch all toots. While we do that:
              If a fetched toot exists in the database: visible = 1, is_on_mastodon = 1, last_found_on_mastodon = now
              If a fetched toot does not exist in the database: add toot to database, set visible = 1, is_on_mastodon = 1, last_found_on_mastodon = now
           After fetching all toots, go through all toots again:
              If is_on_mastodon = 0, set visible to 0 if last_found_on_mastodon is older than 1 week
           Then, every week or so, go through all toots and delete the ones that are not visible and are older than 1 week
         */


        $hashtag = $this->config('mastodon.hashtag');

        if (!$oldestTootDateTime) {
            $oldestTootDateTime = $this->config('mastodon.oldestTootDateTime');
        }

        $this->log("Fetching toots. Stop at existing toot: " . ($stopAtExistingToot ? "yes" : "no") . ", oldest toot datetime: " . $oldestTootDateTime);

        if (empty($hashtag)) {
            $this->log("No hashtag provided");
            return;
        }

        // get movies
        $this->movies = $this->db->getAll("movies");

        if ($oldestTootDateTime) {
            $oldestTootDateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $oldestTootDateTime);
        }

        $maxId = null;
        $newTootCount = 0;
        $error = false;

        while (true) {

            $url = $this->config('mastodon.instance') . "/api/v1/timelines/tag/" . $hashtag . "?limit=40";
            if ($maxId) {
                $url .= "&max_id=" . $maxId;
            }

            $this->log("Fetching " . $url);
            sleep(3);

            try {
                $json = $this->helper->httpRequest($url);
            } catch (\Throwable $e) {
                $this->log("Error fetching toots: " . $e->getMessage());
                $error = true;
                break;
            }


            if (empty($json)) {
                $this->log("No JSON returned");
                $error = true;
                break;
            }

            $toots = json_decode($json, true);

            if (empty($toots)) {
                $this->log("No toots returned");
                break;
            }

            foreach ($toots as $toot) {

                $dbID = hash("sha256", $toot['uri']);

                // toot not public? skip
                if ($toot['visibility'] !== 'public') {
                    $this->log("Toot " . $toot['uri'] . " is not public");
                    continue;
                }

                $createdAt = \DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $toot['created_at']);

                // toot too old? exit
                if ($oldestTootDateTime && $createdAt < $oldestTootDateTime) {
                    $this->log("Toot " . $toot['uri'] . " is older than " . $oldestTootDateTime->format('Y-m-d H:i:s'));
                    break 2;
                }

                // toot already exists? exit
                if ($this->db->getById("toots", $dbID)) {
                    $this->log("Toot " . $dbID . " from " . $toot['created_at'] . " already exists");

                    if ($stopAtExistingToot) {
                        break 2;
                    } else {
                        continue;
                    }
                }

                // toot not too old and not existing yet: save it
                $this->db->insert('toots', [
                    'id' => $dbID,
                    'data' => json_encode($toot),
                    'created_at' => $createdAt->format('Y-m-d H:i:s')
                ]);
                $this->log("Saved toot " . $dbID . " from " . $toot['account']['username'] . ", date: " . $createdAt->format('Y-m-d H:i:s'));

                // save previews of media attachments.
                if (!empty($toot['media_attachments'])) {
                    foreach ($toot['media_attachments'] as $media) {
                        $this->saveMedia($media);
                    }
                }

                // save avatar image of account
                $this->saveAvatarImage($toot['account']['uri'], $toot['account']['avatar']);

                // delete cache entries with name "toots-{slug}" for movies that take place during the toots time
                // also update toot_count
                $movieSlugs = $this->getMovieSlugsForToot($toot);
                foreach ($movieSlugs as $slug) {

                    $movie = $this->db->getBySlug("movies", $slug);

                    $this->db->deleteCacheByPrefix("toots-" . $slug);
                    $this->log("Deleted cache entries for movie " . $slug);


                    // update toot_count
                    $startDateTime = new \DateTime($movie['start_datetime']);

                    // add some seconds for aftershow toots
                    $endDateTime = clone $startDateTime;
                    $endDateTime->add(new \DateInterval('PT' . $movie['duration'] . 'S'));
                    $endDateTime->add(new \DateInterval('PT' . $this->config("aftershowDuration") . 'S'));

                    $res = $this->db->fetch("SELECT COUNT(*) AS count FROM toots WHERE created_at >= :start AND created_at <= :end ORDER BY created_at ASC", ["start" => $startDateTime->format("Y-m-d H:i:s"), "end" => $endDateTime->format("Y-m-d H:i:s")], 'toots-' . $movie['slug']);

                    $tootCount = $res['count'];

                    $this->db->update("movies", ['toot_count' => $tootCount], ['id' => $movie['id']]);

                    $this->log("Updated toot_count for movie " . $movie['slug'] . " to " . $tootCount);
                }

                $newTootCount++;

            }

            // set max_id to id of last toot
            $maxId = $toot['id'];

            $this->db->execute("INSERT INTO options (name, value) VALUES ('max_id', :max_id) ON DUPLICATE KEY UPDATE value = :value", ["max_id" => $maxId, "value" => $maxId]);

            $row = $this->db->fetch("SELECT COUNT(*) as count FROM toots");
            $tootCount = $row['count'];
            $this->log("Toot total: " . $tootCount);

            sleep(3);

        }

        // end of toots. unset max_id in options table so that next time we start from the beginning and fetch new toots
        if (!$error) {
            $this->db->execute("INSERT INTO options (name, value) VALUES ('max_id', '') ON DUPLICATE KEY UPDATE value = ''");
        }

        $this->log("Saved " . $newTootCount . " new toots");


        // reload movies (maybe a movie was added or changed while toots where fetched)
        $this->movies = $this->db->getAll("movies");

        return [
            'newTootCount' => $newTootCount,
            'error' => $error
        ];

    }

    /**
     * Return list of movie slugs for movies that take place during the toots time
     * @param $toot
     * @return array movies that match the toots time
     */
    public function getMovieSlugsForToot($toot) {

        $movies = [];

        foreach ($this->movies as $movie) {
            $startDateTime = new \DateTime($movie['start_datetime']);

            // add some seconds for aftershow toots
            $endDateTime = clone $startDateTime;
            $endDateTime->add(new \DateInterval('PT' . $movie['duration'] . 'S'));
            $endDateTime->add(new \DateInterval('PT' . $this->config("aftershowDuration") . 'S'));

            $tootTime = new \DateTime($toot['created_at']);

            if ($tootTime >= $startDateTime && $tootTime <= $endDateTime) {
                $movies[] = $movie['slug'];
            }
        }

        return $movies;
    }

    /**
     * Save avatar image for account
     * @param $uri string unique id of account
     * @param $url string url of avatar image
     * @return void
     */
    public function saveAvatarImage($uri, $url) {
        if (empty($uri) || empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        $id = hash("sha256", $uri);

        $fileName = __SITE__ . '/public/media/avatars/' . $id . '.jpg';

        if (file_exists($fileName)) {
            return;
        }

        $data = $this->helper->httpRequest($url, "GET", null, $this->config('http.headers'));

        if ($data === false) {
            return;
        }

        // get mime type
        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $fileInfo->buffer($data);

        // is it an image?
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif'])) {
            return;
        }

        // convert to jpg and resize to 120x120 (40@3x)
        if ($mime !== 'image/jpeg') {
            $image = imagecreatefromstring($data);
            $image = imagescale($image, 120);
            ob_start();
            imagejpeg($image);
            $data = ob_get_clean();
        }

        file_put_contents($fileName, $data);

        $this->log("Saved avatar image for account " . $id);

        sleep($this->imageDownloadWaitTime);
    }


    // save original file and preview
    public function saveMedia($media) {

        if (empty($media) || !isset($media['id']) || !isset($media['url']) || !isset($media['preview_url'])) {
            return;
        }

        $originalURL = $media['remote_url'];
        if (!$originalURL) {
            $originalURL = $media['url'];
        }

        $id = hash("sha256", $originalURL);

        $fileExtension = pathinfo($originalURL, PATHINFO_EXTENSION);

        $originalFileName = __SITE__ . '/public/media/originals/' . $id . '.' . $fileExtension;
        $previewFileName = __SITE__ . '/public/media/previews/' . $id . '.jpg';

        if (file_exists($originalFileName) && file_exists($previewFileName)) {
            $this->log("Files for media " . $id . " already exists");
            return;
        }

        if (file_exists($originalFileName)) {
            $this->log("Loading original file for media " . $id . " from disk");
            $originalFile = file_get_contents($originalFileName);

        } else {
            try {
                $this->log("Fetching original file for media " . $id . " from " . $originalURL);
                $originalFile = $this->helper->httpRequest($originalURL, "GET", null, $this->config('http.headers'));
            } catch (\Throwable $e) {
                $httpCode = $e->getMessage();
                $this->log("Error fetching original file for media " . $id . ": " . $httpCode);

                if ($httpCode == 429) {
                    sleep($this->imageDownloadWaitTime * 4);
                } else {
                    sleep($this->imageDownloadWaitTime);
                }

                return;
            }

            if ($originalFile === false) {
                return;
            }

            // save original file
            $res = file_put_contents($originalFileName, $originalFile);

            if (!$res) {
                $this->log("Error saving original file for media " . $id);
            } else {
                $this->log("Saved original file for media " . $id);
            }
        }

        if (file_exists($previewFileName)) {
            $this->log("Preview file for media " . $id . " already exists");
            return;
        }

        // get mime type
        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $fileInfo->buffer($originalFile);

        // is it an image? resize and convert to jpeg
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif'])) {

            // resize to 650px width and convert to jpg
            $image = imagecreatefromstring($originalFile);
            $image = imagescale($image, 650);
            ob_start();
            imagejpeg($image);
            $previewImage = ob_get_clean();

            $res = file_put_contents($previewFileName, $previewImage);

            // not an image: fetch the preview image from the server
        } else {

            try {
                $this->log("Fetching preview file for media " . $id . " from " . $media['preview_url']);
                $previewImage = $this->helper->httpRequest($media['preview_url'], "GET", null, $this->config('http.headers'));
            } catch (\Throwable $e) {
                $httpCode = $e->getMessage();
                $this->log("Error fetching preview image for media " . $id . ": " . $httpCode);

                if ($httpCode == 429) {
                    sleep($this->imageDownloadWaitTime * 4);
                } else {
                    sleep($this->imageDownloadWaitTime);
                }

                return;
            }

            if ($previewImage === false) {
                return;
            }

            // save preview image
            $res = file_put_contents($previewFileName, $previewImage);

        }


        if (!$res) {
            $this->log("Error saving preview image for media " . $id);
        } else {
            $this->log("Saved preview image for media " . $id);
        }

        sleep($this->imageDownloadWaitTime);
    }

}

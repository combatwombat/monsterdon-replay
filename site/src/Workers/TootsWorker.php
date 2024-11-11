<?php

namespace App\Workers;

use RTF\Base;

class TootsWorker extends Base {

    public $imageDownloadWaitTime = 2; // wait at least 2 seconds between downloading images, more on 429 error
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
     * Save newest toots, stop at an existing one or if we reach config.mastodon.oldestTootDateTime
     * Used for regular fetching of new toots or initial fetching of all toots.
     * @return array with 'newTootCount' (int), 'error' (bool)
     */
    public function saveNewToots() {
        return $this->saveToots(true, null);
    }

    /**
     * Save newest toots until a specific toot datetime, like 6 days in the past. Don't stop at existing toots.
     * Used for catching up and fetching stragglers.
     * @param $oldestTootDateTime string toot-datetime at which to stop fetching toots
     * @return array with 'newTootCount' (int), 'error' (bool)
     */
    public function saveTootsUntil($oldestTootDateTime) {
        return $this->saveToots(false, $oldestTootDateTime);
    }

    /**
     * Fetch all toots for the hashtag until config.mastodon.oldestTootDateTime.
     * Before fetching toots, sets found_on_mastodon to 0 for all toots.
     * During fetching, adds new toots and replaces data for existing toots.
     * After fetching all toots, sets visible to 0 for all toots that have not been found on mastodon in this run.
     * After fetching all toots, also deletes those, that are not found_on_mastodon and where last_found_on_mastodon datetime is older than a week.
     * Used for re-saving all toots (to catch edited ones) and hiding and eventually deleting toots that are deleted from Mastodon.
     *
     * @param $deleteOldInvisibleToots boolean delete toots that are not found_on_mastodon and where last_found_on_mastodon datetime is older than config.mastodon.keepInvisibleTootsForSeconds
     * @return array with 'newTootCount' (int), 'updatedTootCount' (int), 'error' (bool)
     */
    public function resaveAllToots($deleteOldInvisibleToots = false) {

        // before first time: UPDATE toots SET visible = 1, found_on_mastodon = 1, last_found_on_mastodon = NOW();

        $this->log("resaving all toots");

        // set found_on_mastodon to 0/false for all toots
        $this->log("Setting found_on_mastodon to 0 for all toots");
        $this->db->update("toots", ['found_on_mastodon' => false]);

        // fetch all toots until oldestTootDateTime, update existing toots
        $res = $this->saveToots(false, null, true);

        // get number of toots with found_on_mastodon = false
        $row = $this->db->fetch("SELECT COUNT(*) as count FROM toots WHERE found_on_mastodon = 0");
        $notFoundTootCount = $row['count'];

        // error fetching toots? reset found_on_mastodon back to 1
        if ($res['error']) {
            $this->log("Error fetching toots. Resetting found_on_mastodon to 1 for all toots");
            $this->db->update("toots", ['found_on_mastodon' => true]);
            return $res;
        }

        if ($notFoundTootCount > 1000) {
            $this->log("More than 1000 toots not found on Mastodon. Something is wrong ಠ_ಠ. Resetting found_on_mastodon to 1 for all toots");
            $this->db->update("toots", ['found_on_mastodon' => true]);
            return $res;
        }

        $toots = $this->db->getAll("toots");

        $this->log("Hiding all toots that have not been found on mastodon in this run");
        foreach ($toots as $toot) {
            if (!$toot['found_on_mastodon']) {
                $this->db->update("toots", ['visible' => false], ['id' => $toot['id']]);
            }
        }

        if ($deleteOldInvisibleToots) {
            $keepInvisibleTootsForSeconds = $this->config('mastodon.keepInvisibleTootsForSeconds');

            if ($keepInvisibleTootsForSeconds) {
                $cutoffDatetime = new \DateTime();
                $cutoffDatetime->sub(new \DateInterval('PT' . $keepInvisibleTootsForSeconds . 'S'));
                $this->log("Deleting invisible toots that have last been found on mastodon before " . $cutoffDatetime->format('Y-m-d H:i:s'));

                $this->db->execute("DELETE FROM toots WHERE found_on_mastodon = 0 AND last_found_on_mastodon < :cutoff", ['cutoff' => $cutoffDatetime->format('Y-m-d H:i:s')]);
            }

        }

        return $res;
    }

    /**
     * Fetch toots from Mastodon and save them to the database.
     * The Mastodon API only provides a list of toots ordered by date, newest first, or all toots that come after
     * a specific toot id (max_id). So, we can only work ourselves backwards until we reach a stop criterion.
     * @param $stopAtExistingToot boolean stop fetching toots if we reach one we already have in the database
     * @param $oldestTootDateTime string toot-datetime at which to stop fetching toots
     * @param $updateExistingToots boolean update existing toots
     * @return array with 'newTootCount' (int), 'updatedTootCount' (int), 'error' (bool)
     * @throws \DateMalformedIntervalStringException
     * @throws \DateMalformedStringException
     */
    public function saveToots($stopAtExistingToot = true, $oldestTootDateTime = null, $updateExistingToots = false) {

        $hashtag = $this->config('mastodon.hashtag');

        if (!$oldestTootDateTime) {
            $oldestTootDateTime = $this->config('mastodon.oldestTootDateTime');
        }

        if (empty($hashtag)) {
            $this->log("No hashtag provided");
            return [
                'newTootCount' => 0,
                'updatedTootCount' => 0,
                'lastModifiedTootDatetime' => '',
                'error' => true
            ];
        }

        $this->log("Fetching toots for hashtag " . $hashtag . ". Stop at existing toot: " . ($stopAtExistingToot ? "yes" : "no") . ", oldest toot datetime: " . $oldestTootDateTime);


        // get movies
        $this->movies = $this->db->getAll("movies");

        if ($oldestTootDateTime) {
            $oldestTootDateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $oldestTootDateTime);
        }

        $maxId = null;
        $newTootCount = 0;
        $updatedTootCount = 0;
        $lastModifiedTootDatetime = '';
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
                if ($createdAt < $oldestTootDateTime) {
                    $this->log("Toot " . $toot['uri'] . " is older than " . $oldestTootDateTime->format('Y-m-d H:i:s'));
                    break 2;
                }

                // toot already exists? exit, continue or update
                $dbToot = $this->db->getById("toots", $dbID);
                if ($dbToot) {


                    if ($updateExistingToots) {

                        $this->log("Toot " . $dbID . " from " . $toot['created_at'] . " already exists. Updating.");

                        $this->db->update('toots', [
                            'data' => json_encode($toot),
                            'visible' => true,
                            'found_on_mastodon' => true,
                            'last_found_on_mastodon' => (new \DateTime())->format('Y-m-d H:i:s')
                        ],
                        ['id' => $dbID]);

                        $lastModifiedTootDatetime = $toot['created_at'];

                        $oldToot = json_decode($dbToot['data'], true);

                        // delete media attachments that are not in the new toot
                        // add media attachments that are in the new toot but not in the old toot

                        $oldTootMedia = [];
                        if (!empty($oldToot['media_attachments'])) {
                            foreach ($oldToot['media_attachments'] as $media) {
                                $oldTootMedia[$this->getMediaId($media)] = $media;
                            }
                        }

                        $newTootMedia = [];
                        if (!empty($toot['media_attachments'])) {
                            foreach ($toot['media_attachments'] as $media) {
                                $newTootMedia[$this->getMediaId($media)] = $media;
                            }
                        }

                        $mediaToDelete = array_diff_key($oldTootMedia, $newTootMedia);
                        $mediaToAdd = array_diff_key($newTootMedia, $oldTootMedia);

                        foreach ($mediaToDelete as $media) {
                            $this->deleteMedia($media);
                        }

                        foreach ($mediaToAdd as $media) {
                            $this->saveMedia($media);
                        }

                        $updatedTootCount++;

                        continue;
                    }

                    if ($stopAtExistingToot) {
                        $this->log("Toot " . $dbID . " from " . $toot['created_at'] . " already exists. Stopping.");
                        break 2;
                    } else {
                        $this->log("Toot " . $dbID . " from " . $toot['created_at'] . " already exists. Skipping.");
                    }

                } else {

                    // toot not too old and not existing yet: save it
                    $this->db->insert('toots', [
                        'id' => $dbID,
                        'data' => json_encode($toot),
                        'created_at' => $createdAt->format('Y-m-d H:i:s'),
                        'visible' => true,
                        'found_on_mastodon' => true,
                        'last_found_on_mastodon' => (new \DateTime())->format('Y-m-d H:i:s')
                    ]);
                    $this->log("Saved toot " . $dbID . " from " . $toot['account']['username'] . ", date: " . $createdAt->format('Y-m-d H:i:s'));

                    // save media attachments.
                    if (!empty($toot['media_attachments'])) {
                        foreach ($toot['media_attachments'] as $media) {
                            $this->saveMedia($media);
                        }
                    }

                    // save avatar image of account
                    $this->saveAvatarImage($toot['account']['uri'], $toot['account']['avatar']);

                    $lastModifiedTootDatetime = $createdAt->format('Y-m-d H:i:s');

                    $newTootCount++;
                }

            }

            // set max_id to id of last toot
            $maxId = $toot['id'];

            $row = $this->db->fetch("SELECT COUNT(*) as count FROM toots");
            $tootCount = $row['count'];
            $this->log("Toot total: " . $tootCount);

            sleep(3);

        }

        $this->log("Saved " . $newTootCount . " new toots");
        $this->log("Updated " . $updatedTootCount . " existing toots");


        return [
            'newTootCount' => $newTootCount,
            'updatedTootCount' => $updatedTootCount,
            'lastModifiedTootDatetime' => $lastModifiedTootDatetime,
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


    /**
     * Get unique hash for media
     * @param $media
     * @return string|boolean hash or false
     */
    public function getMediaId($media) {

        if (empty($media) || !isset($media['id']) || !isset($media['url']) || !isset($media['preview_url'])) {
            return false;
        }

        $originalURL = $media['remote_url'];
        if (!$originalURL) {
            $originalURL = $media['url'];
        }

        return hash("sha256", $originalURL);
    }


    /**
     * Delete media files (original, preview) for toot
     * @param $media array media section of toot
     * @return void
     */
    public function deleteMedia($media) {

        if (empty($media) || !isset($media['id']) || !isset($media['url']) || !isset($media['preview_url'])) {
            return;
        }

        $originalURL = $media['remote_url'];
        if (!$originalURL) {
            $originalURL = $media['url'];
        }

        $id = $this->getMediaId($media);

        $fileExtension = pathinfo($originalURL, PATHINFO_EXTENSION);

        $originalFileName = __SITE__ . '/public/media/originals/' . $id . '.' . $fileExtension;
        $previewFileName = __SITE__ . '/public/media/previews/' . $id . '.jpg';

        if (file_exists($originalFileName)) {
            unlink($originalFileName);
            $this->log("Deleted original file for media " . $id);
        }

        if (file_exists($previewFileName)) {
            unlink($previewFileName);
            $this->log("Deleted preview file for media " . $id);
        }

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

        $id = $this->getMediaId($media);

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

    /**
     * Delete and rebuild cache for all movies, update toot_count
     * @param $afterStartDatetime string only rebuild cache for movies that start after this datetime
     * @return void
     */
    public function rebuildMovieCache($afterStartDateTime = null) {

        if (!$afterStartDateTime) {
            $afterStartDateTime = "1970-01-01 00:00:00";
        }

        $movies = $this->db->fetchAll("SELECT * FROM movies WHERE start_datetime > :start", ['start' => $afterStartDateTime]);

        $baseURL = "https://" . $this->config("domain") . "/api/toots/";
        $movieCount = count($movies);
        $c = 1;
        foreach ($movies as $movie) {
            $this->log("rebuilding cache for movie " . $c . "/" . $movieCount . ": " . $movie['slug']);
            $this->db->deleteCacheByPrefix("toots-" . $movie['slug']);
            $toots = json_decode(file_get_contents($baseURL . $movie['slug']), true);

            $tootCount = count($toots);
            $this->db->update("movies", ['toot_count' => $tootCount], ['id' => $movie['id']]);

            $c++;
        }
    }

}

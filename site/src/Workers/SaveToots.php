<?php

namespace App\Workers;

use RTF\Base;

class SaveToots extends Base {

    public $imageDownloadWaitTime = 2;
    public $movies;

    public function __construct($container) {
        parent::setContainer($container);
    }

    public function saveMediaPreviewsForExistingToots() {
        // get all toots with media attachments
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

    public function run() {

        $hashtag = $this->config('mastodon.hashtag');
        $startDateTime = $this->config('mastodon.startDateTime');

        #$this->saveMediaPreviewsForExistingToots();
        #return;

        if (empty($hashtag)) {
            $this->log("No hashtag provided");
            return;
        }

        // get movies
        $this->movies = $this->db->getAll("movies");

        if ($startDateTime) {
            $startDateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $startDateTime);
        }

        // get max_id from options table, if it exists
        $maxId = null;
        $row = $this->db->getByName('options', 'max_id');
        if ($row) {
            $maxId = $row['value'];
        }

        $newTootCount = 0;
        $error = false;
        while (true) {

            $url = $this->config('mastodon.instance') . "/api/v1/timelines/tag/" . $hashtag . "?limit=40";
            if ($maxId) {
                $url .= "&max_id=" . $maxId;
            }

            $this->log("Fetching " . $url);

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

                // toot not public? skip
                if ($toot['visibility'] !== 'public') {
                    $this->log("Toot " . $toot['id'] . " is not public");
                    continue;
                }

                $createdAt = \DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $toot['created_at']);

                // toot too old? exit
                if ($startDateTime && $createdAt < $startDateTime) {
                    $this->log("Toot " . $toot['id'] . " is older than " . $startDateTime->format('Y-m-d H:i:s'));
                    break 2;
                }

                // toot already exists? exit
                if ($this->db->getById("toots", $toot['id'])) {
                    $this->log("Toot " . $toot['id'] . " already exists");
                    break 2;
                }

                // toot not too old and not existing yet: save it
                $this->db->insert('toots', [
                    'id' => $toot['id'],
                    'data' => json_encode($toot),
                    'created_at' => $createdAt->format('Y-m-d H:i:s')
                ]);
                $this->log("Saved toot " . $toot['id'] . " from " . $toot['account']['username'] . ", date: " . $createdAt->format('Y-m-d H:i:s'));

                // save previews of media attachments.
                if (!empty($toot['media_attachments'])) {
                    foreach ($toot['media_attachments'] as $media) {
                        $this->saveMedia($media);
                    }
                }

                // save avatar image of account
                $this->saveAvatarImage($toot['account']['id'], $toot['account']['avatar']);

                // delete cache entries with name "toots-{slug}" for movies that take place during the toots time
                $movieSlugs = $this->getMovieSlugsForToot($toot);
                foreach ($movieSlugs as $slug) {
                    $this->db->execute("DELETE FROM cache WHERE name LIKE :prefix", ["prefix" => "toots-" . $slug . "%"]);
                    $this->log("Deleted cache entries for movie " . $slug);
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

        if ($newTootCount > 0) {
            sleep(5);
        } else {
            if ($error) {
                sleep(10);
            } else {
                sleep(60);
            }
        }


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

    public function saveAvatarImage($id, $url) {
        if (empty($id) || empty($url) || !filter_var($url, FILTER_VALIDATE_URL) || preg_match('/[.\/]/', $id)) {
            return;
        }

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

        // convert to jpg
        if ($mime !== 'image/jpeg') {
            $image = imagecreatefromstring($data);
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

        if (empty($media) || !isset($media['id']) || !isset($media['url']) || !isset($media['preview_url']) || preg_match('/[.\/]/', $media['id'])) {
            return;
        }

        $originalURL = $media['remote_url'];
        if (!$originalURL) {
            $originalURL = $media['url'];
        }

        $id = $media['id'];

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
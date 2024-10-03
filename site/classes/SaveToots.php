<?php

require 'Base.php';

// save new toots to db
class SaveToots extends Base {


    // how long to wait between image download requests, to not run afoul of rate limiting too much
    public $imageDownloadWaitTime = 2;

    public function __construct($config) {
        parent::__construct($config);

    }

    public function saveMediaPreviewsForExistingToots() {
        // get all toots with media attachments
        $stmt = $this->db->prepare("SELECT * FROM toots WHERE JSON_UNQUOTE(JSON_EXTRACT(data, '$.media_attachments')) <> \"[]\";");
        $stmt->execute();
        $toots = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        // get all toots
        $stmt = $this->db->prepare("SELECT * FROM toots");
        $stmt->execute();
        $toots = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tootCount = count($toots);

        $c = 0;
        foreach ($toots as $toot) {
            $this->log("Saving avatar image for toot " . $toot['id'] . " (" . $c . "/" . $tootCount . ")");
            $tootData = json_decode($toot['data'], true);
            $this->saveAvatarImage($tootData['account']['id'], $tootData['account']['avatar']);

            $c++;
        }

    }

    public function run($hashtag, $startDateTime = null) {

        #$this->saveMediaPreviewsForExistingToots();
        #return;

        if (empty($hashtag)) {
            $this->log("No hashtag provided");
            return;
        }

        if ($startDateTime) {
            $startDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $startDateTime);
        }


        // get max_id from options table, if it exists
        $maxId = null;
        $stmt = $this->db->prepare("SELECT value FROM options WHERE name = 'max_id'");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $maxId = $row['value'];
        }

        $newTootCount = 0;
        $error = false;
        while (true) {

            $url = $this->config['mastodon']['instance'] . "/api/v1/timelines/tag/" . $hashtag . "?limit=40";
            if ($maxId) {
                $url .= "&max_id=" . $maxId;
            }

            $this->log("Fetching " . $url);

            try {
                $json = $this->httpRequest($url);
            } catch (Exception $e) {
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

                $createdAt = DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $toot['created_at']);

                // toot to old? exit
                if ($startDateTime && $createdAt < $startDateTime) {
                    $this->log("Toot " . $toot['id'] . " is older than " . $startDateTime->format('Y-m-d H:i:s'));
                    break 2;
                }

                // toot already exists? exit
                $stmt = $this->db->prepare("SELECT * FROM toots WHERE id = ?");
                $stmt->execute([$toot['id']]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $this->log("Toot " . $toot['id'] . " already exists");
                    break 2;
                }

                // not too old and not existing yet: save it
                $stmt = $this->db->prepare("INSERT INTO toots (id, data, created_at) VALUES (?, ?, ?)");
                $stmt->execute([$toot['id'], json_encode($toot), $createdAt->format('Y-m-d H:i:s')]);
                $this->log("Saved toot " . $toot['id'] . " from " . $toot['account']['username'] . ", date: " . $createdAt->format('Y-m-d H:i:s'));

                // save previews of media attachments.
                if (!empty($toot['media_attachments'])) {
                    foreach ($toot['media_attachments'] as $media) {
                        $this->saveMedia($media);
                    }
                }


                // save avatar image of account
                $this->saveAvatarImage($toot['account']['id'], $toot['account']['avatar']);

                $newTootCount++;

            }

            // set max_id to id of last toot
            $maxId = $toot['id'];
            $stmt = $this->db->prepare("INSERT INTO options (name, value) VALUES ('max_id', ?) ON DUPLICATE KEY UPDATE value = ?");
            $stmt->execute([$maxId, $maxId]);


            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM toots");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $tootCount = $row['count'];
            $this->log("Toot total: " . $tootCount);

            sleep(3);

        }

        // end of toots. unset max_id in options table so that next time we start from the beginning and fetch new toots
        if (!$error) {
            $stmt = $this->db->prepare("INSERT INTO options (name, value) VALUES ('max_id', '') ON DUPLICATE KEY UPDATE value = ''");
            $stmt->execute();
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

    public function saveAvatarImage($id, $url) {
        if (empty($id) || empty($url) || !filter_var($url, FILTER_VALIDATE_URL) || preg_match('/[.\/]/', $id)) {
            return;
        }

        $fileName = BASEPATH . '/public/media/avatars/' . $id . '.jpg';

        if (file_exists($fileName)) {
            return;
        }

        $data = $this->httpRequest($url, "GET", null, $this->config['http']['headers']);

        if ($data === false) {
            return;
        }

        // get mime type
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
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

        if (empty($media) || !isset($media['id']) || !isset($media['remote_url']) || !isset($media['preview_url']) || preg_match('/[.\/]/', $media['id'])) {
            return;
        }

        $id = $media['id'];

        $fileExtension = pathinfo($media['remote_url'], PATHINFO_EXTENSION);

        $originalFileName = BASEPATH . '/public/media/originals/' . $id . '.' . $fileExtension;
        $previewFileName = BASEPATH . '/public/media/previews/' . $id . '.jpg';

        if (file_exists($originalFileName) && file_exists($previewFileName)) {
            $this->log("Files for media " . $id . " already exists");
            return;
        }

        if (file_exists($originalFileName)) {
            $this->log("Loading original file for media " . $id . " from disk");
            $originalFile = file_get_contents($originalFileName);

        } else {
            try {
                $this->log("Fetching original file for media " . $id . " from " . $media['remote_url']);
                $originalFile = $this->httpRequest($media['remote_url'], "GET", null, $this->config['http']['headers']);
            } catch (Exception $e) {
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
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
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
                $previewImage = $this->httpRequest($media['remote_url'], "GET", null, $this->config['http']['headers']);
            } catch (Exception $e) {
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



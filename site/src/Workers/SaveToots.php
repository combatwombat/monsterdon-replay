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

            $this->log("Fetching from url " . $url);

            $this->log("sleeping for 3 seconds");
            sleep(3);
            $this->log("waking up");

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
                if ($oldestTootDateTime && $createdAt < $oldestTootDateTime) {
                    $this->log("Toot " . $toot['id'] . " is older than " . $oldestTootDateTime->format('Y-m-d H:i:s'));
                    break 2;
                }

                // toot already exists? exit
                if ($this->db->getById("toots", $toot['id'])) {
                    $this->log("Toot " . $toot['id'] . " already exists");

                    if ($stopAtExistingToot) {
                        break 2;
                    } else {
                        continue;
                    }
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
                // also update toot_count
                $movieSlugs = $this->getMovieSlugsForToot($toot);
                foreach ($movieSlugs as $slug) {

                    $movie = $this->db->getBySlug("movies", $slug);

                    $this->db->execute("DELETE FROM cache WHERE name LIKE :prefix", ["prefix" => "toots-" . $slug . "%"]);
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

/*
     Toot JSON example:
    {
    "id": "113268434043201303",
    "uri": "https://meow.social/users/YsengrinWolf/statuses/113268434067547262",
    "url": "https://meow.social/@YsengrinWolf/113268434067547262",
    "card": {
        "url": "https://www.youtube.com/watch?v=fnJqLNGBAPc",
        "html": "<iframe width=\"200\" height=\"113\" src=\"https://www.youtube.com/embed/fnJqLNGBAPc?feature=oembed\" frameborder=\"0\" allowfullscreen=\"\" sandbox=\"allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-forms\"></iframe>",
        "type": "video",
        "image": "https://media.hachyderm.io/cache/preview_cards/images/025/975/643/original/423f6705d31eb4de.jpg",
        "title": "Nosferatu (1979) - Official Trailer",
        "width": 200,
        "height": 113,
        "blurhash": "UFA^5qs900-:%NR*IURj4nxt?aE1ITs:%Mxa",
        "language": null,
        "embed_url": "",
        "author_url": "https://www.youtube.com/@ScreamFactoryTV",
        "author_name": "ScreamFactoryTV",
        "description": "",
        "provider_url": "https://www.youtube.com/",
        "published_at": null,
        "provider_name": "YouTube",
        "image_description": ""
    },
    "poll": null,
    "tags": [
        {
            "url": "https://hachyderm.io/tags/Monsterdon",
            "name": "Monsterdon"
        },
        {
            "url": "https://hachyderm.io/tags/wpafw2024",
            "name": "wpafw2024"
        },
        {
            "url": "https://hachyderm.io/tags/werewolf",
            "name": "werewolf"
        },
        {
            "url": "https://hachyderm.io/tags/hippogriff",
            "name": "hippogriff"
        },
        {
            "url": "https://hachyderm.io/tags/fursuit",
            "name": "fursuit"
        }
    ],
    "emojis": [

    ],
    "reblog": null,
    "account": {
        "id": "109389188692021971",
        "bot": false,
        "uri": "https://meow.social/users/YsengrinWolf",
        "url": "https://meow.social/@YsengrinWolf",
        "acct": "YsengrinWolf@meow.social",
        "note": "<p>Werewolf, artist, fursuiter, kaiju enthusiast, haunter. Blender and UE4/5 wrangler. Exploring limits. He/him</p>",
        "group": false,
        "avatar": "https://media.hachyderm.io/cache/accounts/avatars/109/389/188/692/021/971/original/3752b171c92765c4.jpg",
        "emojis": [

        ],
        "fields": [
            {
                "name": "Age",
                "value": "Greymuzzle (60+)",
                "verified_at": null
            },
            {
                "name": "Website",
                "value": "<a href=\"https://www.runningwolfpack.com/\" target=\"_blank\" rel=\"nofollow noopener noreferrer\" translate=\"no\"><span class=\"invisible\">https://www.</span><span class=\"\">runningwolfpack.com/</span><span class=\"invisible\"></span></a>",
                "verified_at": "2024-10-07T22:12:39.998+00:00"
            },
            {
                "name": "FA (neglected)",
                "value": "www.furaffinity.net/user/ysengrin/",
                "verified_at": null
            }
        ],
        "header": "https://media.hachyderm.io/cache/accounts/headers/109/389/188/692/021/971/original/8e01097da7b1a7cb.png",
        "locked": false,
        "username": "YsengrinWolf",
        "created_at": "2022-10-29T00:00:00.000Z",
        "discoverable": true,
        "display_name": "Ysengrin Blackpaw 🔜 WPAFW",
        "avatar_static": "https://media.hachyderm.io/cache/accounts/avatars/109/389/188/692/021/971/original/3752b171c92765c4.jpg",
        "header_static": "https://media.hachyderm.io/cache/accounts/headers/109/389/188/692/021/971/original/8e01097da7b1a7cb.png",
        "last_status_at": "2024-10-07",
        "statuses_count": 3231,
        "followers_count": 407,
        "following_count": 1034
    },
    "content": "<p>Sorry <a href=\"https://meow.social/tags/Monsterdon\" class=\"mention hashtag\" rel=\"nofollow noopener noreferrer\" target=\"_blank\">#<span>Monsterdon</span></a> - I was AFK all weekend ... </p><p><a href=\"https://meow.social/tags/WPAFW2024\" class=\"mention hashtag\" rel=\"nofollow noopener noreferrer\" target=\"_blank\">#<span>WPAFW2024</span></a> <a href=\"https://meow.social/tags/werewolf\" class=\"mention hashtag\" rel=\"nofollow noopener noreferrer\" target=\"_blank\">#<span>werewolf</span></a> <a href=\"https://meow.social/tags/hippogriff\" class=\"mention hashtag\" rel=\"nofollow noopener noreferrer\" target=\"_blank\">#<span>hippogriff</span></a> <a href=\"https://meow.social/tags/fursuit\" class=\"mention hashtag\" rel=\"nofollow noopener noreferrer\" target=\"_blank\">#<span>fursuit</span></a></p>",
    "language": "en",
    "mentions": [

    ],
    "edited_at": null,
    "sensitive": false,
    "created_at": "2024-10-07T22:12:22.000Z",
    "visibility": "public",
    "spoiler_text": "",
    "reblogs_count": 2,
    "replies_count": 0,
    "in_reply_to_id": null,
    "favourites_count": 0,
    "media_attachments": [
        {
            "id": "113268435165386697",
            "url": "https://media.hachyderm.io/cache/media_attachments/files/113/268/435/165/386/697/original/8768042a090be8ac.jpg",
            "meta": {
                "focus": {
                    "x": -0.01,
                    "y": 1
                },
                "small": {
                    "size": "465x496",
                    "width": 465,
                    "aspect": 0.9375,
                    "height": 496
                },
                "original": {
                    "size": "750x800",
                    "width": 750,
                    "aspect": 0.9375,
                    "height": 800
                }
            },
            "type": "image",
            "blurhash": "UEDvr]01H@8_xa%dRnRitbRO%Oxu.QoeIUWA",
            "text_url": null,
            "remote_url": "https://medias.meow.social/media_attachments/files/113/268/413/023/031/977/original/b54398757be2e077.jpg",
            "description": "Werewolf and hippogriff chilling on the lawn",
            "preview_url": "https://media.hachyderm.io/cache/media_attachments/files/113/268/435/165/386/697/small/8768042a090be8ac.jpg",
            "preview_remote_url": null
        }
    ],
    "in_reply_to_account_id": null
}
     */
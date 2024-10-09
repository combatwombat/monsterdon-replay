<?php

namespace App\Controllers;
use RTF\Controller;

class Movies extends Controller {


    public function __construct($container) {
        parent::__construct($container);
    }

    public function list() {

        $movies = $this->db->fetchAll("SELECT * FROM movies ORDER BY start_datetime DESC");

        $data = [
            'bodyClass' => 'page-movies',
            'movies' => $movies
        ];

        $this->view("movies/list", $data);
    }

    public function show($slug) {

        $movie = $this->db->getBySlug("movies", $slug);

        if (!$movie) {
            $this->error(404);
        }

        $startDateTime = new \DateTime($movie['start_datetime']);

        // add some seconds for aftershow toots
        $endDateTime = clone $startDateTime;
        $endDateTime->add(new \DateInterval('PT' . $movie['duration'] . 'S'));
        $endDateTime->add(new \DateInterval('PT' . $this->config("aftershowDuration") . 'S'));

        // get count of all toots between start and end time
        $res = $this->db->fetch("SELECT COUNT(*) AS count FROM toots WHERE created_at >= :start AND created_at <= :end ORDER BY created_at ASC", ["start" => $startDateTime->format("Y-m-d H:i:s"), "end" => $endDateTime->format("Y-m-d H:i:s")], 'toots-' . $movie['slug']);

        $tootCount = $res['count'];


        $data = [
            'bodyClass' => 'page-movie',
            'movie' => $movie,
            'tootCount' => $tootCount
        ];

        $this->view("movies/show", $data);
    }

    // get toots for a movie by its slug
    public function tootsJSON($slug) {

        header('Content-Type: application/json');

        $movie = $this->db->getBySlug("movies", $slug);

        if (!$movie) {
            echo json_encode([]);
            exit;
        }

        // check cache first
        $cacheKey = "toots-" . $slug;
        $res = $this->db->getByName("cache", $cacheKey);
        if ($res) {
            echo json_encode(unserialize($res['value']));
            exit;
        }

        $startDateTime = new \DateTime($movie['start_datetime']);

        // add some seconds for aftershow toots
        $endDateTime = clone $startDateTime;
        $endDateTime->add(new \DateInterval('PT' . $movie['duration'] . 'S'));
        $endDateTime->add(new \DateInterval('PT' . $this->config("aftershowDuration") . 'S'));

        $dbToots = $this->db->fetchAll("SELECT * FROM toots WHERE created_at >= :start AND created_at <= :end ORDER BY created_at ASC", ["start" => $startDateTime->format("Y-m-d H:i:s"), "end" => $endDateTime->format("Y-m-d H:i:s")]);


        $toots = [];

        foreach ($dbToots as $dbToot) {
            $data = json_decode($dbToot['data'], true);

            // only return necessary data
            $toot = [
                'id' => $data['id'],
                'url' => $data['url'],
                'account' => [
                    'id' => $data['account']['id'],
                    'display_name' => $data['account']['display_name'],
                ],
                'content' => $data['content'],
                'sensitive' => $data['sensitive'],
                'created_at' => $data['created_at'],
                'media_attachments' => []
            ];

            foreach ($data['media_attachments'] as $media) {
                $toot['media_attachments'][] = [
                    'id' => $media['id'],
                    'type' => $media['type'],
                    'extension' => pathinfo($media['url'], PATHINFO_EXTENSION)
                ];
            }

            $toots[] = $toot;
        }

        // save in cache
        $this->db->insert("cache", [
            'name' => $cacheKey,
            'value' => serialize($toots)
        ]);

        echo json_encode($toots);
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

}
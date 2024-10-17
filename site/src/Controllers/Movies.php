<?php

namespace App\Controllers;
use RTF\Controller;

class Movies extends Controller {


    public function __construct($container) {
        parent::__construct($container);
    }

    public function list() {

        $allMovies = $this->db->fetchAll("SELECT * FROM movies ORDER BY start_datetime DESC");

        $isLoggedIn = $this->auth->isLoggedIn();

        $movies = [];

        $now = new \DateTime();

        foreach ($allMovies as $movie) {

            $movieEndTime = new \DateTime($movie['start_datetime']);
            $movieEndTime->add(new \DateInterval('PT' . $movie['duration'] . 'S'));
            $movieEndTime->add(new \DateInterval('PT' . $this->config("aftershowDuration") . 'S'));

            $movie['is_in_future'] = $movieEndTime >= $now;

            // show all movies for logged in users, show only past movies for guests
            if ($isLoggedIn) {
                $movies[] = $movie;

            } else {
                if (!$movie['is_in_future']) {
                    $movies[] = $movie;
                }
            }

        }

        $data = [
            'header' => [
                'bodyClass' => 'page-movies',
            ],
            'movies' => $movies
        ];

        $this->view("movies/list", $data);
    }

    public function show($slug) {

        $movie = $this->db->getBySlug("movies", $slug);

        if (!$movie) {
            $this->error(404);
        }

        $overallDuration = $movie['duration'] + $this->config("aftershowDuration");

        $data = [
            'header' => [
                'title' => $movie['title'],
                'bodyClass' => 'page-movie',
                'headerClass' => 'small',
                'backLink' => '/',
                'backgroundImage' => 'url(/media/covers/' . $movie['imdb_id'] . '.jpg)',
            ],
            'movie' => $movie,
            'overallDuration' => $overallDuration
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

        $dbToots = $this->db->fetchAll("SELECT * FROM toots WHERE created_at >= :start AND created_at <= :end ORDER BY created_at DESC", ["start" => $startDateTime->format("Y-m-d H:i:s"), "end" => $endDateTime->format("Y-m-d H:i:s")]);


        $toots = [];

        foreach ($dbToots as $dbToot) {
            $data = json_decode($dbToot['data'], true);

            $timeDelta = strtotime($dbToot['created_at']) - strtotime($startDateTime->format("Y-m-d H:i:s"));

            // only return necessary data
            $toot = [
                'id' => h($data['id']),
                'url' => h($data['url']),
                'account' => [
                    'id' => h($data['account']['id']),
                    'display_name' => h($data['account']['display_name']),
                    'acct' => h($data['account']['acct']),
                    'url' => h($data['account']['url'])
                ],
                'content' => strip_tags($data['content'], '<p><a><span><br>'),
                'sensitive' => $data['sensitive'] ? true : false,
                'created_at' => h($data['created_at']),
                'time_delta' => $timeDelta,
                'media_attachments' => []
            ];

            foreach ($data['media_attachments'] as $media) {
                $extension = trim(pathinfo($media['url'], PATHINFO_EXTENSION));
                if (empty($extension)) {
                    $extension = trim(pathinfo($media['remote_url'], PATHINFO_EXTENSION));
                }
                if (empty($extension)) {
                    if ($media['type'] == 'video') {
                        $extension = "mp4";
                    } else {
                        $extension = "jpg";
                    }
                }
                $toot['media_attachments'][] = [
                    'id' => h($media['id']),
                    'type' => h($media['type']),
                    'extension' => $extension
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


}
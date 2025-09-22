<?php

namespace App\Controllers;


class BackstageMovies extends \RTF\Controller {

    public function __construct($container) {
        parent::__construct($container);
    }

    public function list() {
        $this->auth();

        $movies = $this->db->fetchAll("SELECT * FROM movies ORDER BY start_datetime DESC");
        $data = [
            'header' => [
                'title' => 'Backstage > Movies',
                'bodyClass' => 'page-backstage'
            ],
            "movies" => $movies
        ];
        $this->view("backstage/movies/list", $data);
    }

    public function new() {
        $this->auth();

        $errors = $this->db->validate([
            'slug' => ['data' => $_POST['slug'], 'rules' => 'regex:[a-z0-9\-]+'],
            'release_date' => ['data' => $_POST['release_date'], 'rules' => 'date'],
            'start_datetime' => ['data' => $_POST['start_datetime'], 'rules' => 'required|datetime'],
            'duration' => ['data' => $_POST['duration'], 'rules' => 'numeric'],
            'imdb_id' => ['data' => $_POST['imdb_id'], 'rules' => 'required|regex:tt[a-z0-9]+'],
            'tmdb_id' => ['data' => $_POST['tmdb_id'], 'rules' => 'numeric'],
            'og_image_cover_offset' => ['data' => $_POST['og_image_cover_offset'], 'rules' => 'numeric|min:0|max:100']
        ]);


        if (empty($errors)) {


            // if tmdb_id, title, slug, release_date or duration are empty, get them from tmdb
            if (empty($_POST['title']) || empty($_POST['slug']) || empty($_POST['release_date']) || empty($_POST['duration'])) {
                $movieInfo = $this->tmdb->getInfo($_POST['imdb_id']);

                if (empty($_POST['tmdb_id'])) {
                    $_POST['tmdb_id'] = $this->tmdb->getTMDBIdFromIMDBId($_POST['imdb_id']);
                }

                if (empty($_POST['title'])) {
                    $_POST['title'] = $movieInfo['title'];
                }
                if (empty($_POST['slug'])) {
                    $_POST['slug'] = slugify($movieInfo['title']);
                }
                if (empty($_POST['release_date'])) {
                    $_POST['release_date'] = $movieInfo['release_date'];
                }
                if (empty($_POST['duration'])) {
                    $_POST['duration'] = (int) $movieInfo['runtime'] * 60; // tmdb runtime is in minutes
                }
            }

            // check if slug already exists. if it does, add a number to the end
            $movie = $this->db->getBySlug("movies", $_POST['slug']);
            if ($movie) {
                $_POST['slug'] = $_POST['slug'] . '-' . rand(1000, 9999);
            }


            // calculate toot_count

            $startDateTime = new \DateTime($_POST['start_datetime']);

            // add some seconds for aftershow toots
            $endDateTime = clone $startDateTime;
            $endDateTime->add(new \DateInterval('PT' . $_POST['duration'] . 'S'));
            $endDateTime->add(new \DateInterval('PT' . $this->config("aftershowDuration") . 'S'));

            $res = $this->db->fetch("SELECT COUNT(*) AS count FROM toots WHERE created_at >= :start AND created_at <= :end ORDER BY created_at ASC", ["start" => $startDateTime->format("Y-m-d H:i:s"), "end" => $endDateTime->format("Y-m-d H:i:s")]);

            $tootCount = $res['count'];

            $res = $this->db->insert("movies", [
                'title' => $_POST['title'],
                'slug' => $_POST['slug'],
                'release_date' => $_POST['release_date'],
                'start_datetime' => $_POST['start_datetime'],
                'duration' => $_POST['duration'],
                'imdb_id' => $_POST['imdb_id'],
                'tmdb_id' => $_POST['tmdb_id'],
                'toot_count' => $tootCount,
                'og_image_cover_offset' => $_POST['og_image_cover_offset'],
                'extra_code' => $_POST['extra_code'] ?? ''
            ]);

            $this->tmdb->saveImage($_POST['imdb_id'], 270, $_POST['og_image_cover_offset']);




            if (!$res) {
                $errors['general'][] = 'error adding movie';
            }
        }

        $ret = [
            'status' => empty($errors) ? 'ok' : 'error',
            'errors' => $errors
        ];
        echo json_encode($ret);
    }

    public function delete($id) {
        $this->auth();

        $movie = $this->db->get("movies", $id);

        if ($movie) {
            $this->db->delete("movies", ["id" => $id]);

            // delete toot-cache for movie
            $this->db->deleteCacheByPrefix("toots-" . $movie['slug']);

            // if there are no other movies with this imdb_id...
            $otherMovies = $this->db->fetchAll("SELECT * FROM movies WHERE imdb_id = :imdb_id", ["imdb_id" => $movie['imdb_id']]);
            if (empty($otherMovies)) {

                // ... delete movie images
                $cover = __SITE__ . "/public/media/covers/" . $movie['imdb_id'] . ".jpg";
                $thumb = __SITE__ . "/public/media/covers/" . $movie['imdb_id'] . "_thumb.jpg";
                $ogImage = __SITE__ . "/public/media/covers/" . $movie['imdb_id'] . "_ogimage.png";
                if (file_exists($cover)) {
                    unlink($cover);
                }
                if (file_exists($thumb)) {
                    unlink($thumb);
                }
                if (file_exists($ogImage)) {
                    unlink($ogImage);
                }
            }


        }
    }

    public function edit($id) {
        $this->auth();

        $errors = $this->db->validate([
            'title' => ['data' => $_POST['title'], 'rules' => 'required'],
            'slug' => ['data' => $_POST['slug'], 'rules' => 'required|regex:[a-z0-9\-]+'],
            'release_date' => ['data' => $_POST['release_date'], 'rules' => 'required|date'],
            'start_datetime' => ['data' => $_POST['start_datetime'], 'rules' => 'required|datetime'],
            'duration' => ['data' => $_POST['duration'], 'rules' => 'required|numeric'],
            'imdb_id' => ['data' => $_POST['imdb_id'], 'rules' => 'required|regex:tt[a-z0-9]+'],
            'tmdb_id' => ['data' => $_POST['tmdb_id'], 'rules' => 'required|numeric'],
            'og_image_cover_offset' => ['data' => $_POST['og_image_cover_offset'], 'rules' => 'required|numeric|min:0|max:100']
        ]);

        if (!$errors) {
            // check if slug already exists on another movie
            $movie = $this->db->fetch("SELECT * FROM movies WHERE slug = :slug AND id != :id", ["slug" => $_POST['slug'], "id" => $id]);
            if ($movie) {
                $errors['slug'][] = 'slug already exists';
            }
        }

        if (empty($errors) && !empty($id)) {

            // save new movie images if imdb id changes
            $movie = $this->db->getById("movies", $id);
            if ($movie['imdb_id'] !== $_POST['imdb_id']) {
                $this->tmdb->saveImage($_POST['imdb_id']);
            } else {

                // different og_image_cover_offset? create new og:image
                if ($movie['og_image_cover_offset'] !== $_POST['og_image_cover_offset']) {
                    $this->tmdb->createOGImage($_POST['imdb_id'], $_POST['og_image_cover_offset']);
                }

            }

            // tmdb id empty (since it was added later)? get it from imdb id
            if (empty($_POST['tmdb_id'])) {
                $_POST['tmdb_id'] = $this->tmdb->getTMDBIdFromIMDBId($_POST['imdb_id']);
            }


            $res = $this->db->update("movies", [
                'title' => $_POST['title'],
                'slug' => $_POST['slug'],
                'release_date' => $_POST['release_date'],
                'start_datetime' => $_POST['start_datetime'],
                'duration' => $_POST['duration'],
                'imdb_id' => $_POST['imdb_id'],
                'tmdb_id' => $_POST['tmdb_id'],
                'og_image_cover_offset' => $_POST['og_image_cover_offset'],
                'extra_code' => $_POST['extra_code'] ?? ''
            ], ['id' => $id]);

            if ($res) {

                // delete toot-cache for old movie slug
                $this->db->deleteCacheByPrefix("toots-" . $movie['slug']);

                $movie = $this->db->getById("movies", $id);

                // delete toot-cache for new movie slug. perhaps necessary if we changed the slug. if not, it doesn't take too long
                $this->db->deleteCacheByPrefix("toots-" . $movie['slug']);

                // update toot count

                $startDateTime = new \DateTime($movie['start_datetime']);

                // add some seconds for aftershow toots
                $endDateTime = clone $startDateTime;
                $endDateTime->add(new \DateInterval('PT' . $movie['duration'] . 'S'));
                $endDateTime->add(new \DateInterval('PT' . $this->config("aftershowDuration") . 'S'));

                $res = $this->db->fetch("SELECT COUNT(*) AS count FROM toots WHERE created_at >= :start AND created_at <= :end ORDER BY created_at ASC", ["start" => $startDateTime->format("Y-m-d H:i:s"), "end" => $endDateTime->format("Y-m-d H:i:s")]);

                $tootCount = $res['count'];

                $this->db->update("movies", ['toot_count' => $tootCount], ['id' => $id]);
            } else {
                $errors['general'][] = 'error updating movie';
            }

        }

        $ret = [
            'status' => empty($errors) ? 'ok' : 'error',
            'errors' => $errors
        ];
        echo json_encode($ret);
    }




}
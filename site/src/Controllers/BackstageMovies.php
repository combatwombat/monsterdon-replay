<?php

namespace App\Controllers;


class BackstageMovies extends \RTF\Controller {

    public function __construct($container) {
        parent::__construct($container);
    }

    public function list() {
        $this->auth();

        $movies = $this->db->fetchAll("SELECT * FROM movies ORDER BY start_datetime ASC");
        $data = ['bodyClass' => "page-backstage", "movies" => $movies];
        $this->view("backstage/movies/list", $data);
    }

    public function new() {
        $this->auth();

        $errors = $this->db->validate([
            'slug' => ['data' => $_POST['slug'], 'rules' => 'regex:[a-z0-9\-]+'],
            'release_date' => ['data' => $_POST['release_date'], 'rules' => 'date'],
            'start_datetime' => ['data' => $_POST['start_datetime'], 'rules' => 'required:datetime'],
            'duration' => ['data' => $_POST['duration'], 'rules' => 'numeric'],
            'imdb_id' => ['data' => $_POST['imdb_id'], 'rules' => 'required:regex:tt[a-z0-9]+']
        ]);


        if (empty($errors)) {

            // if title, slug, release_date or duration are empty, get them from tmdb
            if (empty($_POST['title']) || empty($_POST['slug']) || empty($_POST['release_date']) || empty($_POST['duration'])) {
                $movieInfo = $this->tmdb->getInfo($_POST['imdb_id']);

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

            $res = $this->db->insert("movies", [
                'title' => $_POST['title'],
                'slug' => $_POST['slug'],
                'release_date' => $_POST['release_date'],
                'start_datetime' => $_POST['start_datetime'],
                'duration' => $_POST['duration'],
                'imdb_id' => $_POST['imdb_id']
            ]);

            $this->tmdb->saveImage($_POST['imdb_id']);

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
        $this->db->delete("movies", ["id" => $id]);
    }

    public function edit($id) {
        $this->auth();

        $errors = $this->db->validate([
            'title' => ['data' => $_POST['title'], 'rules' => 'required'],
            'slug' => ['data' => $_POST['slug'], 'rules' => 'required|regex:[a-z0-9\-]+'],
            'release_date' => ['data' => $_POST['release_date'], 'rules' => 'required|date'],
            'start_datetime' => ['data' => $_POST['start_datetime'], 'rules' => 'required|datetime'],
            'duration' => ['data' => $_POST['duration'], 'rules' => 'required|numeric'],
            'imdb_id' => ['data' => $_POST['imdb_id'], 'rules' => 'required|regex:tt[a-z0-9]+']
        ]);

        if (!$errors) {
            // check if slug already exists on another movie
            $movie = $this->db->fetch("SELECT * FROM movies WHERE slug = :slug AND id != :id", ["slug" => $_POST['slug'], "id" => $id]);
            if ($movie) {
                $errors['slug'][] = 'slug already exists';
            }
        }

        if (empty($errors) && !empty($id)) {

            // save new cover image if imdb id changes
            $movie = $this->db->getById("movies", $id);
            if ($movie['imdb_id'] !== $_POST['imdb_id']) {
                $this->tmdb->saveImage($_POST['imdb_id']);
            }

            $res = $this->db->update("movies", [
                'title' => $_POST['title'],
                'slug' => $_POST['slug'],
                'release_date' => $_POST['release_date'],
                'start_datetime' => $_POST['start_datetime'],
                'duration' => $_POST['duration'],
                'imdb_id' => $_POST['imdb_id']
            ], ['id' => $id]);

            if (!$res) {
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
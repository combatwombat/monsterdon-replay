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

        $data = [
            'bodyClass' => 'page-movie',
            'movie' => $movie
        ];

        $this->view("movies/show", $data);
    }




}
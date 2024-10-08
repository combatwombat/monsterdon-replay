<?php

namespace App\Controllers;


class Movies extends \RTF\Controller {


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




}
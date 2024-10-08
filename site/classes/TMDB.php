<?php

class TMDB extends Base {

    public function __construct($config) {
        parent::__construct($config);
    }

    /**
     * Get TMDB id for a given IMDB id. We use IMDB ids in the database, because individual TV episodes don't have TMDB ids.
     * @param $imdbId
     * @return false|mixed
     * @throws Exception
     */
    public function getTMDBIdFromIMDBId($imdbId) {
        $url = "https://api.themoviedb.org/3/find/" . $imdbId . "?api_key=" . $this->config['tmdb']['apiKey'] . "&external_source=imdb_id";
        $response = $this->httpRequest($url);
        $json = json_decode($response, true);
        if (isset($json['movie_results'][0]['id'])) {
            return $json['movie_results'][0]['id'];
        } else {
            return false;
        }
    }

    /**
     * Get movie cover image from TMDB and save it to public/media/covers/{imdbId}.jpg
     * @param $imdbId
     * @return void
     * @throws Exception
     */
    public function saveMovieImage($imdbId) {

        if (file_exists(BASEPATH . "/public/media/covers/" . $imdbId . ".jpg")) {
            echo "hu";
            return;
        }

        $tmdbId = $this->getTMDBIdFromIMDBId($imdbId);

        echo $tmdbId;

        if ($tmdbId) {
            $url = "https://api.themoviedb.org/3/movie/" . $tmdbId . "?api_key=" . $this->config['tmdb']['apiKey'];
            $response = $this->httpRequest($url);
            $json = json_decode($response, true);
            $posterPath = $json['poster_path'];
            $posterUrl = "https://image.tmdb.org/t/p/w500" . $posterPath;
            $poster = $this->httpRequest($posterUrl);
            file_put_contents(BASEPATH . "/public/media/covers/" . $imdbId . ".jpg", $poster);
        }


    }


}



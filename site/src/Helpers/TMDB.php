<?php


namespace App\Helpers;

class TMDB extends \RTF\Base {

    function __construct($container) {
        $this->container = $container;
    }

    /**
     * Get TMDB id for a given IMDB id. We use IMDB ids in the database, because individual TV episodes don't have TMDB ids.
     * @param $imdbId
     * @return false|mixed
     * @throws Exception
     */
    public function getTMDBIdFromIMDBId($imdbId) {
        $url = "https://api.themoviedb.org/3/find/" . $imdbId . "?api_key=" . $this->config('apiKeys.tmdb') . "&external_source=imdb_id";
        $response = $this->helper->httpRequest($url);
        $json = json_decode($response, true);
        if (isset($json['movie_results'][0]['id'])) {
            return $json['movie_results'][0]['id'];
        } else {
            return false;
        }
    }

    /**
     * Get movie/episode cover image from TMDB and save it to public/media/covers/{imdbId}.jpg
     * Also resize to thumbnail.
     * @param $imdbId
     * @return void
     * @throws Exception
     */
    public function saveImage($imdbId, $thumbWidth = 270) {

        if (file_exists(__SITE__ . "/public/media/covers/" . $imdbId . ".jpg")) {
            return;
        }

        $tmdbId = $this->getTMDBIdFromIMDBId($imdbId);

        if ($tmdbId) {
            $url = "https://api.themoviedb.org/3/movie/" . $tmdbId . "?api_key=" . $this->config('apiKeys.tmdb');
            $response = $this->helper->httpRequest($url);
            $json = json_decode($response, true);

            if (!$json) {
                return;
            }
            $posterPath = $json['poster_path'];
            $posterUrl = "https://image.tmdb.org/t/p/w500" . $posterPath;
            $poster = $this->helper->httpRequest($posterUrl);
            $res = file_put_contents(__SITE__ . "/public/media/covers/" . $imdbId . ".jpg", $poster);

            if ($res) {

                // create thumbnail
                $image = imagecreatefromjpeg(__SITE__ . "/public/media/covers/" . $imdbId . ".jpg");
                $width = imagesx($image);
                $height = imagesy($image);
                $newWidth = $thumbWidth;
                $newHeight = $height * ($newWidth / $width);
                $newImage = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagejpeg($newImage, __SITE__ . "/public/media/covers/" . $imdbId . "_thumb.jpg");
            }
        }

    }


    public function getInfo($imdbId) {
        $tmdbId = $this->getTMDBIdFromIMDBId($imdbId);
        $url = "https://api.themoviedb.org/3/movie/" . $tmdbId . "?api_key=" . $this->config('apiKeys.tmdb');
        $response = $this->helper->httpRequest($url);
        return json_decode($response, true);
    }
}
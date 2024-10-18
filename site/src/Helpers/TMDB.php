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
     * Get IMDB id for a given TMDB id.
     * @param $tmdbId
     * @return false|mixed
     * @throws Exception
     */
    public function getIMDBIdFromTMDBId($tmdbId) {
        $url = "https://api.themoviedb.org/3/movie/" . $tmdbId . "?api_key=" . $this->config('apiKeys.tmdb');
        $response = $this->helper->httpRequest($url);
        $json = json_decode($response, true);
        if (isset($json['imdb_id'])) {
            return $json['imdb_id'];
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
    public function saveImage($imdbId, $thumbWidth = 270, $ogImageCoverOffset = 50) {

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


                // create og:image
                $this->createOGImage($imdbId, $ogImageCoverOffset);
            }
        }

    }

    /**
     * Combine templates and movie cover image to create og:image for a movie
     * @param $movie
     * @return void
     */
    public function createOGImage($imdbId, $ogImageCoverOffset) {

        $fgImagePath = __SITE__ . "/public/img/og-image_fg.png";
        $coverPath = __SITE__ . "/public/media/covers/" . $imdbId . ".jpg";
        $bgImagePath = __SITE__ . "/public/img/og-image_bg.png";
        $ogImagePath = __SITE__ . "/public/media/covers/" . $imdbId . "_ogimage.png";

        $fgImage = imagecreatefrompng($fgImagePath);
        $coverImage = imagecreatefromjpeg($coverPath);
        $bgImage = imagecreatefrompng($bgImagePath);

        $width = imagesx($fgImage);
        $height = imagesy($fgImage);

        $newImage = imagecreatetruecolor($width, $height);

        // Copy background
        imagecopy($newImage, $bgImage, 0, 0, 0, 0, $width, $height);

        // Calculate new dimensions for cover image
        $coverWidth = imagesx($coverImage);
        $coverHeight = imagesy($coverImage);
        $newCoverWidth = $width;
        $newCoverHeight = ($coverHeight / $coverWidth) * $newCoverWidth;

        // Calculate vertical position based on $ogImageCoverOffset
        $yOffset = round(($newCoverHeight - $height) * ($ogImageCoverOffset / 100)) * -1;

        // Create a new image for the resized cover
        $resizedCover = imagecreatetruecolor($newCoverWidth, $newCoverHeight);
        imagecopyresampled($resizedCover, $coverImage, 0, 0, 0, 0, $newCoverWidth, $newCoverHeight, $coverWidth, $coverHeight);

        // Apply the resized cover to the new image with 6% opacity
        imagecopymerge($newImage, $resizedCover, 0, $yOffset, 0, 0, $newCoverWidth, $newCoverHeight, 6);

        // Copy foreground
        imagecopy($newImage, $fgImage, 0, 0, 0, 0, $width, $height);

        // Save the final image
        imagepng($newImage, $ogImagePath);

        // Free up memory
        imagedestroy($fgImage);
        imagedestroy($coverImage);
        imagedestroy($bgImage);
        imagedestroy($newImage);
        imagedestroy($resizedCover);

    }


    public function getInfo($imdbId) {
        $tmdbId = $this->getTMDBIdFromIMDBId($imdbId);
        $url = "https://api.themoviedb.org/3/movie/" . $tmdbId . "?api_key=" . $this->config('apiKeys.tmdb');
        $response = $this->helper->httpRequest($url);
        return json_decode($response, true);
    }
}
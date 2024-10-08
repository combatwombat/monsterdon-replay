<?php

namespace RTF;

class Helper extends Base {

    function __construct($container) {
        $this->container = $container;
    }

    public function getFileLines($fileName) {
        $file = new \SplFileObject($fileName, 'r');
        $file->seek(PHP_INT_MAX);
        return $file->key();
    }

    public function formatTime($seconds) {
        if ($seconds < 60) {
            return sprintf("%02ds", $seconds);
        }
        if ($seconds < 3600) {
            $minutes = ($seconds / 60) % 60;
            $s = $seconds % 60;
            return sprintf("%02dm%02ds", $minutes, $s);
        }

        $H = floor($seconds / 3600);
        $i = ($seconds / 60) % 60;
        $s = $seconds % 60;

        return sprintf("%02dh%02dm%02ds", $H, $i, $s);
    }

    /**
     * curl wrapper
     * @param $url
     * @param $method
     * @param $data
     * @param $headers
     * @return bool|string
     */
    public function httpRequest($url, $method = "GET", $payload = null, $headers = null) {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($payload) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (curl_errno($ch)) {
            $this->log(curl_errno($ch) . " " . curl_error($ch));
            throw new Exception("Curl error");
        }

        // is it not a http code starting with 2? throw exception
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception($httpCode);
        }

        return $response;
    }


}
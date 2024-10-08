<?php

namespace RTF;

class Controller extends Base {

    public $isFragmentRequest;

    public function __construct($container) {
        $this->container = $container;
        $this->isFragmentRequest = isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'];
    }

    public function redirect($location, $code = 302) {
        $this->container->router->redirect($location, $code);
    }

    /**
     * Return json with appropriate content type header
     * @param array $data
     * @return void
     */
    public function json($data) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
    }

    /**
     * Stream back a file, supports rRange requests for media files
     * @param $path
     * @param $encoding
     * @return void
     */
    public function sendFile($path, $encoding = 'application/octet-stream', $bufferSize = 8192) {
        if (!file_exists($path)) {
            header("HTTP/1.1 404 Not Found");
            die();
        }

        $size = filesize($path);
        $start = 0;
        $end = $size - 1;

        $fp = fopen($path, 'rb');

        // is range request?
        if (isset($_SERVER['HTTP_RANGE'])) {
            $range = $_SERVER['HTTP_RANGE'];
            $range = preg_replace('/^bytes=/', '', $range);
            $range = explode('-', $range);
            $start = $range[0];
            $end = ($range[1] === '') ? $end : $range[1];
        }

        if ($start > 0 || $end < $size - 1) {
            header("HTTP/1.1 206 Partial Content");
            header("Content-Range: bytes $start-$end/$size");
            header("Content-Length: " . ($end - $start + 1));
        } else {
            header("Content-Length: $size");
        }

        header("Content-Type: " . $encoding);
        header("Accept-Ranges: bytes");

        fseek($fp, $start);

        while (!feof($fp) && ($p = ftell($fp)) <= $end) {
            if ($p + $bufferSize > $end) {
                // at the end? send rest
                $bufferSize = $end - $p + 1;
            }
            set_time_limit(0); // reset max_execution_time
            echo fread($fp, $bufferSize);
            flush();
        }

        fclose($fp);
    }

    public function sendDataAsFile($data, $filename, $encoding = 'application/octet-stream') {
        header("Content-Type: " . $encoding);
        header("Content-Disposition: attachment; filename=\"$filename\"");
        echo $data;
    }



}
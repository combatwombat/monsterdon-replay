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
     * Show $code.php if it exists, send http code
     * @param $code
     * @return void
     */
    public function error($code) {
        $viewFile = __SITE__ . "/src/views/$code.php";
        if (file_exists($viewFile)) {

            switch ($code) {
                case 404:
                    header("HTTP/1.0 404 Not Found");
                    break;
                case 403:
                    header("HTTP/1.0 403 Forbidden");
                    break;
                case 401:
                    header("HTTP/1.0 401 Unauthorized");
                    break;
                case 418:
                    header("HTTP/1.0 418 I'm a teapot");
                    break;
                case 500:
                    header("HTTP/1.0 500 Internal Server Error");
                    break;
            }


            $this->view($code, ['bodyClass' => "error-$code"]);
        } else {
            echo "Error $code";
        }
        die();

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
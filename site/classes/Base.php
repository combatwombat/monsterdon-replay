<?php

class Base {

    protected $config;
    protected $db;

    protected $logLength = 10000;

    public function __construct($config) {
        $this->config = $config;

        // new PDO connection. quit on error
        try {
            $this->db = new PDO("mysql:host=" . $this->config['db']['host'] . ";dbname=" . $this->config['db']['name'], $this->config['db']['user'], $this->config['db']['pass']);
        } catch (PDOException $e) {
            $this->log("DB connection failed: " . $e->getMessage());
            die();
        }

    }

    public function log($message) {
        $message = is_string($message) ? $message : print_r($message, true);

        // cli mode?
        if (php_sapi_name() == 'cli') {
            $userIP = "cli";
        } else {
            $userIP = $_SERVER['REMOTE_ADDR'];
        }

        $fileName = "default.log";

        // filename contains double dots? bail
        if (strpos($fileName, '..') !== false) {
            return;
        }

        $filePath = BASEPATH . "/logs/" . $fileName;
        $text = "=== " . date('Y-m-d H:i:s') . " (" . $userIP . ")\n" . $message . "\n\n";
        file_put_contents($filePath, $text, FILE_APPEND);
        print_r($text);

        // keep only the last x lines of logs
        $lines = file($filePath);
        if (count($lines) > $this->logLength) {
            $lines = array_slice($lines, -$this->logLength);
            file_put_contents($filePath, implode('', $lines));
        }

    }

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

    // check cookie or http basic auth for $config['auth']['user'] and $config['auth']['pass']
    public function auth() {
        ini_set('session.cookie_lifetime', 60 * 60 * 24 * 30);
        session_start();

        if (isset($_SESSION['auth']) && $_SESSION['auth'] == true) {

            // renew session cookie
            setcookie(session_name(), session_id(), time() + 60 * 60 * 24 * 30, '/');

            return true;
        }

        if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            if ($_SERVER['PHP_AUTH_USER'] == $this->config['auth']['user'] && $_SERVER['PHP_AUTH_PW'] == $this->config['auth']['pass']) {

                // set auth session to true
                $_SESSION['auth'] = true;
                return true;
            }
        }
        header('WWW-Authenticate: Basic');
        header('HTTP/1.0 401 Unauthorized');
        exit;
    }

}
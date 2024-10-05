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
                $_SESSION['auth'] = true;
                return true;
            }
        }
        header('WWW-Authenticate: Basic');
        header('HTTP/1.0 401 Unauthorized');
        echo "nope";
        sleep(1);
        exit;
    }

    /**
     * Validate a single field or an array of fields
     * @param $nameOrFields
     * @param $data
     * @param $rules
     * @return array of errors. Example: ['fieldname1' => ['error 1', 'error 2'], 'fieldname2' => ['error 1']]
     *
     * Usage:
     * // validate a single field
     * $errors = $this->validate('foo', $_POST['foo'], 'required|min:3|max:10');
     *
     * // validate multiple fields
     * $errors = $this->validate(['foo' => ['data' => $_POST['foo'], 'rules' => 'required|min:3|max:10'], 'bar' => ['data' => $_POST['bar'], 'rules' => 'required|datetime|regex:[a-z0-9\-]']]);
     */
    public function validate($nameOrFields, $data = null, $rules = null) {
        $errors = [];

        // if $nameOrFields is an array, loop through it
        if (is_array($nameOrFields)) {
            foreach ($nameOrFields as $name => $field) {
                $res = $this->validate($name, $field['data'], $field['rules']);
                if (!empty($res)) {
                    $errors = array_merge($errors, $res);
                }
            }
        } else {
            $rules = explode('|', $rules);
            $data = trim($data);
            $name = $nameOrFields;
            $errors[$name] = [];

            foreach ($rules as $rule) {
                $rule = explode(':', $rule);
                $ruleName = $rule[0];
                $ruleValue = isset($rule[1]) ? $rule[1] : null;

                if ($ruleName == 'required' && empty($data)) {
                    $errors[$name][] = $name . ' is required';
                }

                if ($ruleName == 'numeric' && !is_numeric($data)) {
                    $errors[$name][] = $name . ' must be a number';
                }

                if ($ruleName == 'min' && (int) $data < (int) $ruleValue) {
                    $errors[$name][] = $name . ' must be at least ' . $ruleValue . ' big';
                }

                if ($ruleName == 'max' && (int) $data > (int) $ruleValue) {
                    $errors[$name][] = $name . ' can\'t be bigger than ' . $ruleValue;
                }

                if ($ruleName == 'datetime' && !strtotime($data)) {
                    $errors[$name][] = $name . ' must be a valid date and time';
                }

                if ($ruleName == 'date' && !strtotime($data)) {
                    $errors[$name][] = $name . ' must be a valid date';
                }

                if ($ruleName == 'regex' && !preg_match('/' . $ruleValue . '/', $data)) {
                    $errors[$name][] = $name . ' must match the pattern ' . $ruleValue;
                }

            }

            if (empty($errors[$name])) {
                unset($errors[$name]);
            }

        }

        return $errors;
    }



}
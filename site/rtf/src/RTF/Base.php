<?php

namespace RTF;

class Base {

    public $container;

    protected $logLength = 100000; // prune log if it gets too long in lines

    public function setContainer($container) {
        $this->container = $container;
    }

    // search for missing properties in DI container
    public function __get($name) {
        if ($this->container->has($name)) {
            return $this->container->get($name);
        }
    }

    public function __call($name, $args) {
        if ($this->container->has($name)) {
            $obj = $this->container->get($name);
            return $obj(...$args);
        }
    }

    /**
     * Output and log text to a file.
     * @param $text
     * @param $fileName - default: system.log in site/logs directory
     * @return void
     */
    public function log($message, $fileName = "default.log") {
        $message = is_string($message) ? $message : print_r($message, true);

        // cli mode?
        if (php_sapi_name() == 'cli') {
            $userIP = "cli";
        } else {
            $userIP = $_SERVER['REMOTE_ADDR'];
        }

        $fileName = "default.log";

        if (strpos($fileName, '..') !== false) {
            return;
        }

        $filePath = __SITE__ . "/logs/" . $fileName;
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
}
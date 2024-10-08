<?php

namespace RTF;

class Config {

    private $config;
    private $defaultConfig = [
        "componentRoutePrefix" => "/_component"
    ];

    public function __construct($file = "config.php") {
        $fileConfig = require __DIR__ . '/../../../config/' . $file;
        $this->config = array_replace_recursive($this->defaultConfig, $fileConfig);
    }

    public function __invoke($args) {
        if (is_array($args) && count($args) === 1) {
            $args = $args[0];
        }
        return $this->get($args);
    }

    /**
     * Get value from config array by a path in the form "a.b.c".
     * @param string $path path.to.value
     * @return mixed
     */
    public function get($path) {

        $pathArr = explode(".", $path);
        $value = &$this->config;
        foreach ($pathArr as $key) {
            $value = &$value[$key];
        }
        return $value;
    }

}

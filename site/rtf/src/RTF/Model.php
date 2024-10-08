<?php

namespace RTF;

class Model extends Base {

    public function __construct($container) {
        $this->container = $container;
    }

    /**
     * "a.b.c" to get $array['a']['b']['c']
     * @param array $array
     * @param string $key in dot notation like a.b.c to get $array['a']['b']['c']
     * @return array|mixed|null
     */
    public function getNestedValue(array $array, string $key) {
        $keys = explode('.', $key);
        foreach ($keys as $k) {
            if (!array_key_exists($k, $array)) {
                return null;
            }
            $array = $array[$k];
        }
        return $array;
    }

}
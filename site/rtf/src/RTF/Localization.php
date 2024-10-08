<?php

namespace RTF;

class Localization extends Base {

    public $translations = '';
    public $locale;

    public function __construct($container) {
        $this->container = $container;
        $this->locale = !empty($this->container->config("defaultLocale")) ? $this->container->config("defaultLocale") : "en_US";

        if ($this->container->auth->isLoggedIn()) {
            $userData = $this->container->db->getJSONData("users", "data", $this->container->auth->getCurrentUser()['id']);
            if (isset($userData['language'])) {
                $this->locale = $userData['language'];
            }
        }

        // load src/languages/{defaultLocale}.php
        $file = __DIR__ . "/../../../src/languages/{$this->locale}.php";
        if (file_exists($file)) {
            $this->translations = include $file;
        }
    }

    public function __invoke($key, $data = []) {
        return $this->translate($key, $data);
    }


    /**
     * Look for translation in languages/{defaultLocale}.php and replace placeholders with data.
     * If not translation is found, return the key, with replaced placeholders.
     * Example: $this->translate("Hello {name}", ["name" => "John"]) will return "Hello John"
     * use global function t($key, $data) in functions.php as shortcut.
     * For ambigous keys with double meanings, the value is an array with subkey - value pairings
     * example:
     * return [
     *  'Cat' => 'Katze',
     *  'Hello {name}' => 'Hallo {name}',
     *  'Safe' => [
     *      'adjective' => 'Sicher',
     *      'tresor' => 'Tresor',
     *  ],
     *  'Something {num}' => [
     *      'foo' => 'Etwas {num}',
     *  'bar' => 'Schmettwas {num}'
     *  ],
     *
     * ];
     *
     * usage:
     *
     * #simple
     * t("Cat") => "Katze"
     *
     * # with named parameters 8similar to router)
     * t("Hello {name}", ["name" => "John"]) => "Hallo John"
     *
     * # with ambiguous keys
     * t("Safe") => "Sicher"
     * t("Safe", "tresor") => "Tresor"
     * t("Safe", "adjective") => "Sicher"
     *
     * # with ambiguous keys and parameters
     * t("Something {num}", "foo", ["num" => 1]) => "Etwas 1"
     * t("Something {num}", "bar", ["num" => 1]) => "Schmettwas 1"
     *
     * @param $key
     * @param $dataOrSubkey
     * @param $data
     * @return string
     */

    public function translate($key, $dataOrSubkey = [], $data = []) {
        $subKey = null;
        if (is_array($dataOrSubkey)) {
            $data = $dataOrSubkey;
        } else {
            $subKey = $dataOrSubkey;
        }

        if (!empty($this->translations) && !empty($this->translations[$key])) {
            $translation = $this->translations[$key];


            if (is_array($translation)) {
                if ($subKey) {
                    if (!empty($translation[$subKey])) {
                        $translation = $translation[$subKey];
                    }
                } else {
                    $translation = array_values($translation)[0];
                }

            }

            if (!empty($data)) {
                foreach ($data as $k => $v) {
                    $translation = str_replace("{{$k}}", $v, $translation);
                }
            }

            return $translation;
        } else {
            if (!empty($data)) {
                foreach ($data as $k => $v) {
                    $key = str_replace("{{$k}}", $v, $key);
                }
            }
            return $key;
        }
    }

}
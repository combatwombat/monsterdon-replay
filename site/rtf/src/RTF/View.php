<?php

namespace RTF;

class View extends Base {

    public $viewsPath;
    public $appNamespace;
    public $container;
    public $data;
    public $htmlCache;


    function __construct($container, $viewsPath = "../src/views/", $appNamespace = "\\App") {
        $this->container = $container;
        $this->viewsPath = $viewsPath;
        $this->appNamespace = $appNamespace;
    }

    public function __invoke($template, $data = [], $layout = "default") {
        $this->data = array_merge($data, (array)$this->data);

        $this->render($template, $data, $layout);
    }

    public function set($name, $value) {
        $this->data[$name] = $value;
    }

    /**
     * Render a template, maybe surround it with a layout
     * @param $template
     * @param $data
     * @param $layout
     * @param $cache
     * @return void
     */
    public function render($template, $data = [], $layout = "default", $cache = true) {
        $this->data = array_merge((array)$this->data, $data);

        $cacheHash = md5($template . print_r($data, true));

        if ($cache && isset($this->htmlCache[$cacheHash])) {
            echo $this->htmlCache[$cacheHash];
            return;
        }

        $file = $this->templateFilePath($template);

        if (!file_exists($file)) return;

        if ($layout) {
            $this->set("_content", $this->loadTemplate($file, $this->data));
            $content = $this->loadTemplate($this->templateFilePath("layouts/" . $layout), $this->data);
        } else {
            $content = $this->loadTemplate($file, $this->data);
        }

        if ($cache) {
            $this->htmlCache[$cacheHash] = $content;
        }

        echo $content;
    }

    private function loadTemplate($_file, $_data) {
        extract($_data);
        ob_start();
        include $_file;
        return ob_get_clean();
    }

    public function templateFilePath($template) {
        // normalize template path
        if (is_array($template)) {
            $template = $template[0];
        }
        if (strtolower(substr($template, -4)) !== ".php") {
            $template .= ".php";
        }

        return $this->viewsPath . $template;
    }

    public function templateExists($template) {
        return file_exists($this->templateFilePath($template));
    }

        /**
     * Execute and render a component
     * @param $componentName
     * @param $params
     * @param $client bool Request for component comes from the client via JS?
     * @return void
     */
    public function component($componentName, $params = [], $client = false) {
        $componentName = $this->appNamespace . "\\Components\\" . $componentName;
        $params["_isClient"] = $client;
        $component = new $componentName($this->container, $params);
        $component->execute();
    }

    /**
     * @param $componentName
     * @param $params
     * @return string relative url for component, with GET params
     */
    public function componentRoute($componentName, $params = []) {

        $url = $this->container->config("componentRoutePrefix") . '/' . $componentName;

        if (!empty($params)) {
            $url .= '?';
            $paramStrings = [];
            foreach ($params as $key => $value) {
                $paramString[] = urlencode($key) . "=" . urlencode($value);
            }
            $url .= implode("&", $paramString);
        }

        return $url;
    }

    /**
     * Include a view, add some parameters
     * @param $view
     * @return void
     */
    public function include($view, $params = null) {
        if ($params) {
            extract($params);
        }
        include $this->viewsPath . $view . ".php";
    }



}
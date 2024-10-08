<?php

/**
 * Rob's Tiny Framework v0.3
 * gerlach.dev 2024
 */

namespace RTF;

class RTF {

    public $container;
    public $router;
    public $cli;

    public function __construct() {
        $this->container = new \RTF\Container();
        $this->router = new \RTF\Router($this->container);
        $this->cli = new \RTF\CLI($this->container);

        $this->container->set("router", $this->router);

        ini_set("session.cookie_httponly", true);
        ini_set("session.cookie_secure", true);

    }

    // Wrap Router

    public function route($methods, $regex, $callbacks, $name = null) {
        $this->router->add($methods, $regex, $callbacks, $name);
    }

    public function get($path, $callbacks, $name = null) {
        $this->router->get($path, $callbacks, $name);
    }

    public function post($path, $callbacks, $name = null) {
        $this->router->post($path, $callbacks, $name);
    }

    public function put($path, $callbacks, $name = null) {
        $this->router->put($path, $callbacks, $name);
    }

    public function delete($path, $callbacks, $name = null) {
        $this->router->delete($path, $callbacks, $name);
    }

    public function patch($path, $callbacks, $name = null) {
        $this->router->patch($path, $callbacks, $name);
    }

    public function connect($path, $callbacks, $name = null) {
        $this->router->connect($path, $callbacks, $name);
    }

    public function options($path, $callbacks, $name = null) {
        $this->router->options($path, $callbacks, $name);
    }

    public function trace($path, $callbacks, $name = null) {
        $this->router->trace($path, $callbacks, $name);
    }

    public function cli($commands, $callback) {
        $this->cli->add($commands, $callback);
    }

    public function onError($code, $callback) {
        $this->router->onError($code, $callback);
    }

    public function redirect($path, $statusCode = 302) {
        $this->router->redirect($path, $statusCode);
    }


    public function run($basePath = '/') {
        if (php_sapi_name() == 'cli') {
            $this->cli->execute();
        } else {
            $this->router->route($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'], $basePath);
        }
    }

    /**
     * Automatically add an endpoint for components
     * @return void
     */
    public function addComponentRoute() {
        $componentRoutePrefix = $this->container->config("componentRoutePrefix");
        if (empty($componentRoutePrefix)) {
            $componentRoutePrefix = "/_component";
        }
        $this->post($componentRoutePrefix . "/{componentName}", function($componentName) {

            // dashed-name to DashedName
            $componentNameArr = explode("-", $componentName);
            $componentClass = "";
            foreach ($componentNameArr as $name) {
                $componentClass .= ucfirst($name);
            }

            $params = array_merge($_GET, $_POST);

            $this->container->view->component($componentClass, $params, true);
        });
    }

}

return new RTF();
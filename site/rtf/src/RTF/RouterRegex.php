<?php

namespace RTF;

use RTF;
use RTF\Auth;
use RTF\Controller;

class RouterRegex {

    private $container;
    private $routes = [];

    public function __construct($container) {
        $this->container = $container;
    }

    /**
     * Add a route.
     * @param string $regex
     * @param string|array $methods 1-n methods
     * @param $callback
     */
    public function add($methods, $regex, $callbacks) {
        if (!is_array($methods)) {
            $methods = [$methods];
        }
        foreach ($methods as $method) {
            $this->routes[] = [
                "regex" => $regex,
                'method'    => $method,
                'callbacks'  => $callbacks
            ];
        }
    }

    // Shortcuts to add routes for all HTTP methods

    public function get($regex, $callbacks) {
        $this->add('get', $regex, $callbacks);
    }

    public function post($regex, $callbacks) {
        $this->add('post', $regex, $callbacks);
    }

    public function put($regex, $callbacks) {
        $this->add('put', $regex, $callbacks);
    }

    public function delete($regex, $callbacks) {
        $this->add( 'delete', $regex, $callbacks);
    }

    public function patch($regex, $callbacks) {
        $this->add('patch', $regex, $callbacks);
    }

    public function connect($regex, $callbacks) {
        $this->add('connect', $regex, $callbacks);
    }

    public function options($regex, $callbacks) {
        $this->add('options', $regex, $callbacks);
    }

    public function trace($regex, $callbacks) {
        $this->add('trace', $regex, $callbacks);
    }


    /**
     * Route requests
     * @param string $basePath App root. Useful if it's in a subdirectory
     */
    public function route($basePath = '/') {

        $nonRootBasePath = !empty($basePath) && $basePath !== '/';

        $parsedURL = parse_url($_SERVER['REQUEST_URI']);
        $path = isset($parsedURL['path']) ? $parsedURL['path'] : '/';
        $method = $_SERVER['REQUEST_METHOD'];

        $pathMatched = $routeMatched = false;

        // Check routes
        foreach ($this->routes as $route) {

            // Add basepath to regex
            if ($nonRootBasePath) {
                $route['regex'] = '(' . $basePath . ')' . $route['regex'];
            }

            // add / to end of path and regex if necessary
            /*if (mb_substr($path, -1) !== "/") {
                $path .= "/";
            }
            if (mb_substr($route['regex', -1) !== "/") {
                $route['regex' .= "/";
            }*/

            if (preg_match('#^' . $route['regex'] . '$#', $path, $matches)) {


                $pathMatched = true;

                if (strtolower($method) === strtolower($route['method'])) {
                    array_shift($matches);

                    if ($nonRootBasePath) {
                        array_shift($matches);
                    }

                    $this->callCallbacks($route['callbacks'], $matches);

                    $routeMatched = true;
                    break;
                }
            }
        }

        // Couldn't route request?
        if (!$routeMatched) {

            $errorCode = 404;

            // Route fits but wrong method
            if ($pathMatched) {
                $errorCode = 405;
            }

            http_response_code($errorCode);
            if (isset($this->errorResponses[$errorCode])) {
                call_user_func_array($this->errorResponses[$errorCode], [$path, $method]);
            }
        }
    }

    public function redirect($location, $code = 302) {
        header("Location: " . $location, true, $code);
        die();
    }

    /**
     * Call callbacks one by one, stop on first false return.
     * @param mixed $callbacks Single callback or array of them.
     * @param $matches
     */
    private function callCallbacks($callbacks, $params) {

        if (is_array($callbacks)) {
            foreach ($callbacks as $callback) {
                if (!$this->callCallback($callback, $params)) {
                    break;
                }
            }
        } else {
            $this->callCallback($callbacks, $params);
        }
    }

    /**
     * Call a callback (closure or callable string).
     * @param mixed $callback
     * @param array $params
     * @return bool|mixed
     */
    private function callCallback($callback, $params) {
        if (is_callable($callback)) {

            // call closure in context of Controller
            if ($callback instanceof \Closure) {
                $controller = new Controller($this->container);
                return call_user_func_array(\Closure::bind($callback, $controller), $params);
            }

            return call_user_func_array($callback, $params);

        } else {

            // string in the form of Controller@method?
            if (strpos($callback, '@') !== false) {

                $arr = explode("@", $callback);

                $controllerName = "App\\Controllers\\" . $arr[0];
                $controller = new $controllerName($this->container);
                call_user_func_array([$controller, $arr[1]], $params);

            } else {
                // maybe it's just a classname? try calling the execute() method on it
                $this->callCallback($callback . "@execute", $params);
            }

        }
        return false;
    }

    public function getRoutes() {
        return $this->routes;
    }

}
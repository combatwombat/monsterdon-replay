<?php

namespace RTF;

use RTF;
use RTF\Auth;
use RTF\Controller;

class Router {

    private $container;
    public $routes = [];
    public $errorResponses;
    public $currentRoute;

    public function __construct($container) {
        $this->container = $container;
        $this->errorResponses = [];
    }

    /**
     * Add a route.
     * @param string $regex
     * @param string|array $methods 1-n methods
     * @param $callback
     */
    public function add($methods, $path, $callbacks, $name = null) {
        if (!is_array($methods)) {
            $methods = [$methods];
        }
        foreach ($methods as $method) {
            $this->routes[] = [
                "path" => $path,
                'method'    => $method,
                'callbacks'  => $callbacks,
                'name'  => $name
            ];
        }
    }

    // Shortcuts to add routes for all HTTP methods

    public function get($path, $callbacks, $name = null) {
        $this->add('get', $path, $callbacks, $name);
    }

    public function post($path, $callbacks, $name = null) {
        $this->add('post', $path, $callbacks, $name);
    }

    public function put($path, $callbacks, $name = null) {
        $this->add('put', $path, $callbacks, $name);
    }

    public function delete($path, $callbacks, $name = null) {
        $this->add( 'delete', $path, $callbacks, $name);
    }

    public function patch($path, $callbacks, $name = null) {
        $this->add('patch', $path, $callbacks, $name);
    }

    public function connect($path, $callbacks, $name = null) {
        $this->add('connect', $path, $callbacks, $name);
    }

    public function options($path, $callbacks, $name = null) {
        $this->add('options', $path, $callbacks, $name);
    }

    public function trace($path, $callbacks, $name = null) {
        $this->add('trace', $path, $callbacks, $name);
    }


    /**
     * Route requests
     * @param string $basePath App root. Useful if it's in a subdirectory
     */
    public function route($url, $method, $basePath = '/') {

        $this->currentRoute = null;

        $nonRootBasePath = !empty($basePath) && $basePath !== '/';

        $parsedURL = parse_url($url);
        $path = isset($parsedURL['path']) ? $parsedURL['path'] : '/';
        $method = $method;

        $pathMatched = $routeMatched = false;

        // does path not end with /? add it
        if (substr($path, -1) !== '/') {
            $path .= '/';
        }

        // Check routes
        foreach ($this->routes as $route) {

            $routeMatched = false;
            $pathMatched = false;

            // Add basepath to route path
            if ($nonRootBasePath) {
                $route['path'] = $basePath . $route['path'];
            }

            // Convert route path to regular expression.
            $pattern = preg_replace_callback('/{(\w+)(\?)?}/', function ($matches) {
                if (isset($matches[2]) && $matches[2] == '?') {
                    // Optional parameter
                    return '(?P<' . $matches[1] . '>[\w-]+)?';
                } else {
                    // Required parameter
                    return '(?P<' . $matches[1] . '>[\w-]+)';
                }
            }, $route['path']);

            $pattern = '/^' . str_replace('/', '\/', $pattern) . '\/?$/i';

            if (preg_match($pattern, $path, $matches)) {

                // Filter out the numeric array items
                $params = array_filter($matches, function ($key) {
                    return !is_numeric($key);
                }, ARRAY_FILTER_USE_KEY);

                $pathMatched = true;

                if (strtolower($method) === strtolower($route['method'])) {
                    $routeMatched = true;
                    $this->currentRoute = $route;

                    $this->callCallbacks($route['callbacks'], $params);
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
                $this->callCallback($this->errorResponses[$errorCode], [$path, $method]);
            }
        }
    }

    public function executeErrorResponse($errorCode) {
        if (isset($this->errorResponses[$errorCode])) {
            $this->callCallback($this->errorResponses[$errorCode], []);
        }
    }

    public function onError($errorCode, $callback) {
        $this->errorResponses[$errorCode] = $callback;
    }

    public function getParamNamesForRoute($route) {
        preg_match_all('/\{(\w+)(\?)?\}/', $route, $matches);
        return $matches[1];
    }

    /**
     * Get path for a route. getPath("articles", ["id" => 1]) => "/articles/1"
     * @param $routeName string name of the route
     * @param $params array of parameters
     * @return string|null the path or null if a route with that name doesnt exist
     */
    public function getPath($routeName, $params = []) {

        $path = null;
        $route = null;

        // get route by name
        foreach ($this->routes as $aRoute) {
            if ($aRoute['name'] === $routeName) {
                $route = $aRoute;
                break;
            }
        }

        if ($route) {
            // replace parameters in route path (can be {simple} or {optional?} with a question mark
            $path = $route['path'];
            foreach ($params as $key => $param) {
                $path = str_replace(["{".$key."}", "{".$key."?}"], $param, $path);
            }

            // throw out all optional parameters that are left
            $path = preg_replace('/{(\w+)\?}/', '', $path);
        }

        return $path;

    }

    public function redirect($location, $code = 302) {
        header("Location: " . $location, true, $code);

        if (isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST']) {
            header("HX-Redirect: " . $location, true);
        }

        die();
    }

    /**
     * Refreshes website via js:
     * document.body.addEventListener("refresh", () => {
     *     window.location.href = window.location.href;
     * });
     * @return void
     */
    public function triggerRefresh() {
        header("HX-Trigger: refresh", true);
    }



    /**
     * Check if current route matches or starts with path.
     * @param $path
     * @return bool true|false
     */
    public function is($path) {
        return strpos($this->currentRoute['path'], $path) === 0;
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
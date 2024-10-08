<?php

namespace RTF;

class CLI {

    public $container;
    public $commands;

    public function __construct($container) {
        $this->container = $container;
        $this->commands = [];
    }

    // add a callback for a command string, or array of commands
    public function add($commands, $callback) {
        if (!is_array($commands)) {
            $commands = [$commands];
        }
        foreach ($commands as $command) {
            if ($command) {

                // $command is something like "generate-articles {n} {x}"
                // extract the command name and the parameters
                $commandParts = explode(" ", $command);
                $commandName = trim($commandParts[0]);
                $commandParams = array_slice($commandParts, 1);

                foreach ($commandParams as $i => $param) {
                    $commandParams[$i] = trim($param);
                    $commandParams[$i] = trim($commandParams[$i], "{");
                    $commandParams[$i] = trim($commandParams[$i], "}");
                }

                $newCommand = [
                    'name' => $commandName,
                    'params' => $commandParams,
                    'callback' => $callback
                ];

                $this->commands[] = $newCommand;
            } else {
                $this->commands['{no-command-default}'] = $callback;
            }
        }

    }

    public function execute() {
        global $argv;
        global $argc;

        $args = array_slice($argv, 2);
        $parsedArgs = [];

        for($i = 0; $i < count($args); $i += 2) {
            $key = $args[$i];
            if (isset($key[0]) && isset($key[1]) && ($key[0] === '-' || $key[1] === '-')) {
                $key = ltrim($key, '-');
                $parsedArgs[$key] = $args[$i+1] ?? null;
            }
        }

        $requestedCommand = [
            'name' => $argv[1],
            'params' => $parsedArgs
        ];

        // no command or param given?
        if (empty($requestedCommand['name'])) {
            $callback = $this->commands['{no-command-default}'];
            if ($callback) {
                $this->callCallback($callback, []);
            }
        } else {

            foreach ($this->commands as $cmd) {
                if ($cmd['name'] === $requestedCommand['name']) {
                    $callback = $cmd['callback'];
                    $params = $requestedCommand['params'];

                    $this->callCallback($callback, $params);
                }
            }
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
                $controller = new $arr[0]($this->container);

                call_user_func_array([$controller, $arr[1]], $params);

            } else {
                // maybe it's just a class name? try calling the execute() method on it
                $this->callCallback($callback . "@execute", $params);
            }

        }
        return false;
    }


}
<?php

namespace RTF;

class Container {

    protected $instances = [];

    public function __get($name) {
        // search for missing properties in DI container
        if ($this->has($name)) {
            return $this->get($name);
        }
    }

    public function __call($name, $args) {
        if ($this->has($name)) {
            return $this->get($name)($args);
        }
    }

    public function get(string $id, $params = []) {
        return $this->resolve($id, $params);
    }

    public function has(string $id) {
        return array_key_exists($id, $this->instances);
    }

    public function set($id, $instance = null) {
        // so we can just say set("bla") and it instantiates the Bla class
        if ($instance === null) {
            $instance = $id;
        }
        $this->instances[$id] = $instance;
    }

    public function resolve($instanceId, $params) {
        $instance = $this->instances[$instanceId];


        // simple closure? return
        if ($instance instanceof \Closure) {
            return $instance($this, $params);
        }

        // object?
        if (gettype($instance) === 'object') {
            return $instance;
        }

        if (gettype($instance) === 'string') {

            // can't instantiate?
            $ref = new \ReflectionClass($instance);
            if (!$ref->isInstantiable()) {
                throw new \Exception("Class " . $instance . " is not instantiable");
            }

            // no constructor? just return an instance
            $constructor = $ref->getConstructor();
            if (is_null($constructor)) {
                $obj = $ref->newInstance();
                if (method_exists($obj, 'setContainer')) {
                    $obj->setContainer($this);
                }
                $this->instances[$instanceId] = $obj;
                return $obj;
            }

            // constructor? fill dependencies, instantiate
            $params = $constructor->getParameters();
            $deps = $this->getDependencies($params);

            $obj = $ref->newInstanceArgs($deps);

            if (method_exists($obj, 'setContainer')) {
                $obj->setContainer($this);
            }

            $this->instances[$instanceId] = $obj;
            return $obj;

        }
    }


    public function getDependencies($parameters) {
        $dependencies = [];
        foreach ($parameters as $parameter) {
            $dependency = $parameter->getClass();
            if ($dependency === NULL) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new \Exception("Can not resolve class dependency {$parameter->name}");
                }
            } else {
                $dependencies[] = $this->get($dependency->name);
            }
        }
        return $dependencies;
    }
}
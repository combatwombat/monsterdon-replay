<?php

namespace RTF;

class Component extends Base {

    public $params;
    public $isClient; // request via htmx?

    public function __construct($container, $params = null) {
        $this->container = $container;
        $this->params = $params;
        $this->isClient = $this->params['_isClient'];
    }

    public function view($template, $data) {
        $data['_isClient'] = $this->params['_isClient'];
        $this->container->view->render("components/" . $template, $data);
    }


}
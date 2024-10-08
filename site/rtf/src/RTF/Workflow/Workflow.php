<?php

namespace RTF\Workflow;

use RTF\Base;

/**
 * Workflows can be executed as Jobs with a job queue.
 */
class Workflow extends Base {
    public $params;

    public function __construct($container, $params = null) {
        $this->container = $container;
        $this->params = $params;

    }

}
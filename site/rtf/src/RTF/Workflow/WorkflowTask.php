<?php

namespace RTF\Workflow;

use RTF\Base;

/**
 * A task that's part of a workflow. Workflows can be executed as Jobs with a job queue.
 */
class WorkflowTask extends Base {
    public $params;

    public function __construct($container, $params = null) {
        $this->container = $container;
        $this->params = $params;
    }

}
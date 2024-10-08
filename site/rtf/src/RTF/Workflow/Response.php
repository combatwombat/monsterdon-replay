<?php

namespace RTF\Workflow;

class Response {

    public $status;
    public $message;
    public $data;

    function __construct($status, $message = '', $data = null) {
        $this->status = $status;
        $this->message = $message;
        $this->data = $data;
    }

}
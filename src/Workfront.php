<?php namespace willvincent\Workfront;

use willvincent\Workfront\WorkfrontClient;

class Workfront {
  protected $instance;

  public function __construct($config) {
    $this->instance = new WorkfrontClient($config);
  }

  public function client() {
    return $this->instance;
  }
}

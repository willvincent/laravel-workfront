<?php namespace willvincent\Workfront\Facades;

use Illuminate\Support\Facades\Facade;

class WorkfrontFacade extends Facade {

  protected static function getFacadeAccessor() {
    return 'workfront';
  }

}

<?php namespace components\payment; if(!defined('TX')) die('No direct access.');

mk('Component')->load('payment', 'methods\\ideal\\BaseHandler', false);

class Views extends \dependencies\BaseViews
{
  
  protected function settings()
  {
    
    return array(
      'ideal' => methods\ideal\BaseHandler::get_config()
    );
    
  }
  
}

<?php namespace components\payment; if(!defined('TX')) die('No direct access.');


class Views extends \dependencies\BaseViews
{
  
  protected function settings()
  {
    
    mk('Component')->load('payment', 'methods\\ideal\\IdealBaseHandler', false);
    mk('Component')->load('payment', 'methods\\paypal\\PayPalHandler', false);
    
    return array(
      'ideal' => methods\ideal\IdealBaseHandler::get_config(),
      'paypal' => methods\paypal\PayPalHandler::get_config()
    );
    
  }
  
}

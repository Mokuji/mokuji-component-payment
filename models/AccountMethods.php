<?php namespace components\payment\models; if(!defined('MK')) die('No direct access.');

class AccountMethods extends \dependencies\BaseModel
{
  
  protected static
  
    $table_name = 'payment_account_methods',
  
    $relations = array(
    );
  
  public function get_settings_object()
  {
    
    return Data(json_decode($this->settings->get('string'), true));
    
  }
  
}
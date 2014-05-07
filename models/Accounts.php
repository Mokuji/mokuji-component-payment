<?php namespace components\payment\models; if(!defined('MK')) die('No direct access.');

class Accounts extends \dependencies\BaseModel
{
  
  protected static
  
    $table_name = 'payment_accounts',
  
    $relations = array(
    );
  
  public function get_ideal()
  {
    
    return $this->table('AccountMethods')
      ->where('account_id', $this->id)
      ->where('method', "'IDEAL'")
      ->execute_single();
    
  }
  
  public function get_paypal()
  {
    
    return $this->table('AccountMethods')
      ->where('account_id', $this->id)
      ->where('method', "'PAYPAL'")
      ->execute_single();
    
  }
  
}
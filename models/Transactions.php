<?php namespace components\payment\models; if(!defined('TX')) die('No direct access.');

class Transactions extends \dependencies\BaseModel
{
  
  protected static
  
    $table_name = 'payment_transactions',
  
    $relations = array(
    );
  
  public static function create($total_price = null, $currency = 'EUR')
  {
    
    $tx = mk('Sql')->model('payment', 'Transactions');
    
    //Create reference.
    $time = time().microtime();
    $salt = mk('Security')->random_string(32);
    $algoritm = mk('Security')->pref_hash_algo(128, false);
    $tx->transaction_reference->set(
      mk('Security')->hash("transaction|$time|$salt", $algoritm)
    );
    
    //Store currency.
    $tx->currency->set(strtoupper($currency));
    
    //Store price.
    if($total_price)
      $tx->total_price->set((double)$total_price);
    
    //Set initial status.
    $tx->status->set('UNCONFIRMED');
    
    //To create an ID and a creation date.
    $tx->save();
    
    return $tx;
    
  }
  
}
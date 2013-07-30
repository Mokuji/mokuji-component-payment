<?php namespace components\payment\models; if(!defined('TX')) die('No direct access.');

class Transactions extends \dependencies\BaseModel
{
  
  protected static
  
    $table_name = 'payment_transactions',
  
    $relations = array(
    );
  
  public static function create_transaction($total_price = null, $currency = 'EUR')
  {
    
    $tx = mk('Sql')->model('payment', 'Transactions');
    
    //Create unique reference.
    $algoritm = mk('Security')->pref_hash_algo(128, false);
    do{
      
      $time = time().microtime();
      $salt = mk('Security')->random_string(32);
      $nonce = 0;
      $tx->transaction_reference->set(
        mk('Security')->hash("transaction|$time|$salt|$nonce", $algoritm)
      );
      
      $is_unique = mk('Sql')
        ->table('payment', 'Transactions')
        ->where('transaction_reference', "'{$tx->transaction_reference}'")
        ->count()->get() <= 0;
      
    } while(!$is_unique);
    
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
  
  public function claim($method, $handler)
  {
    
    if(!$this->method->get() === 'UNCONFIRMED')
      return false;
    
    $this->merge(array(
      'method' => strtoupper($method),
      'handler' => $handler
    ));
    
    return $this->save();
    
  }
  
}
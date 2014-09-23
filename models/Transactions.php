<?php namespace components\payment\models; if(!defined('TX')) die('No direct access.');

class Transactions extends \dependencies\BaseModel
{
  
  const
    EXPIRE_AFTER = 86400; //24 hours
  
  protected static
  
    $table_name = 'payment_transactions',
  
    $relations = array(
    );
  
  public static function create_transaction($total_price = null, $currency = 'EUR', $account_id=null)
  {
    
    raw($total_price, $currency, $account_id);
    
    $tx = mk('Sql')->model('payment', 'Transactions');
    
    //Create unique reference.
    $algoritm = mk('Security')->pref_hash_algo(128, false);
    $time = time().microtime();
    $salt = mk('Security')->random_string(32);
    $nonce = 0;
    
    do{
      
      $tx->transaction_reference->set(
        mk('Security')->hash("transaction|$time|$salt|$nonce", $algoritm)
      );
      
      $is_unique = mk('Sql')
        ->table('payment', 'Transactions')
        ->where('transaction_reference', "'{$tx->transaction_reference}'")
        ->count()->get() <= 0;
      
      $nonce++;
      
    } while(!$is_unique);
    
    //Store currency.
    $tx->currency->set(strtoupper($currency));
    
    //Store price.
    if($total_price)
      $tx->total_price->set((double)$total_price);
    
    //Set initial status.
    $tx->status->set('UNCONFIRMED');
    
    if($account_id > 0)
      $tx->account_id->set(intval($account_id));
    
    //To create an ID and a creation date.
    $tx->save();
    
    return $tx;
    
  }
  
  //Get the associated account.
  //Please note: the Data class breaks any kind of getting and setting of this object.
  //Always use it as a class of it's own.
  public function getAccount()
  {
    
    return $this->table('Accounts')
      ->pk($this->account_id)
      ->execute_single();
    
  }
  
  /**
   * Binds the transaction to a specific payment method and handler.
   * 
   * Note: This should be done as soon as the remote server of the payment method registers
   *   the generated transaction_reference.
   * @param  string $method
   * @param  int    $handler
   * @return $this
   */
  public function claim($method, $handler)
  {
    
    if($this->get_is_claimed())
      return false;
    
    $this->merge(array(
      'method' => strtoupper($method),
      'handler' => $handler
    ));
    
    return $this->save();
    
  }
  
  public function handler()
  {
    
    return \components\payment\methods\BaseHandler::find_handler($this);
    
  }
  
  public function report()
  {
    
    if($this->report_helper->is_set()){
      
      $parts = explode('/', $this->report_helper->get('string'));
      
      try{
        mk('Component')->helpers($parts[0])->_call($parts[1], array($this->transaction_reference->get('string')));
      }
      
      catch(\Exception $ex){
        mk('Logging')->log('Payment', 'Report transaction update', 'Error'.n.$ex->getMessage().n.'in '.$ex->getFile().'('.$ex->getLine().')'.n.$ex->getTraceAsString(), false, true);
      }
      
    }
    
  }
  
  public function get_is_claimed()
  {
    
    return $this->method->get() !== null &&
      $this->status->get() === 'UNCONFIRMED';
    
  }
  
  public function get_is_expired()
  {
    
    $status = $this->status->get('string');
    if($status === 'EXPIRED')
      return true;
    
    if($status === 'UNCONFIRMED'){
      
      $created = strtotime($this->dt_created->get('string'));
      
      if($created+self::EXPIRE_AFTER < time())
      {
        
        $this->status->set('EXPIRED');
        $this->save();
        $this->report();
        return true;
        
      }
      
    }
    
    return false;
    
  }
  
  public function get_is_status_final(){
    return in_array($this->status->get(), array('SUCCESS', 'EXPIRED', 'CANCELLED'));
  }
  
  public function get_is_permanently_failed(){
    return $this->is_status_final->get('boolean') && $this->status->get('string') !== 'SUCCESS';
  }
  
}
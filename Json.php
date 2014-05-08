<?php namespace components\payment; if(!defined('TX')) die('No direct access.');

class Json extends \dependencies\BaseComponent
{
  
  public function get_status_updates($options, $sub_routes)
  {
    
    $updated = 0;
    $errors = array();
    
    $result = mk('Sql')->table('payment', 'Transactions')
      ->where('status', array("'OPEN'", "'UNCONFIRMED'"))
      ->execute()
      ->each(function($tx)use(&$updated, &$errors){
        
        try{
          
          //1,5 minute per item seems more than enough.
          set_time_limit(90);
          $handler = $tx->handler();
          
          //Check with the handler if the status is updated.
          if($handler && $handler->update_status($tx))
            $updated++;
          
          //Check if the transaction expired.
          elseif($tx->status->get('string') === 'UNCONFIRMED' && $tx->is_expired->get('boolean') === true)
            $updated++;
          
        }
        
        catch(\Exception $ex){
          $errors[$tx->id->get('int')] = $ex->getMessage();
        }
        
      });
    
    mk('Logging')->log('Payment', 'Manual status updates', $updated.'/'.$result->size().' updated with '.count($errors).' errors.');
    
    return array(
      'updated' => $updated,
      'matched' => $result->size(),
      'errors' => $errors
    );
    
  }
  
  protected function get_account($options, $sub_routes)
  {
    
    return mk('Sql')->table('payment', 'Accounts')
      ->order('id')
      ->execute()
      ->each(function($account){
        $account->ideal->settings_object;
        $account->paypal->settings_object;
      });
    
  }
  
  protected function post_account($data, $sub_routes, $options)
  {
    
    //Make new account.
    $account = mk('Sql')->model('payment', 'Accounts');
    return $this->save_account($account, $data);
    
  }
  
  protected function put_account($data, $sub_routes, $options)
  {
    
    //Get our target.
    $account = mk('Sql')->table('payment', 'Accounts')
      ->pk($sub_routes->{0}->get('int'))
      ->execute_single()
      ->is('empty', function(){
        throw new \exception\NotFound('No account with this ID.');
      });
    
    return $this->save_account($account, $data);
    
  }
  
  protected function delete_account($options, $sub_routes)
  {
    
    //Get our target.
    $account = mk('Sql')->table('payment', 'Accounts')
      ->pk($sub_routes->{0}->get('int'))
      ->execute_single()
      ->is('empty', function(){
        throw new \exception\NotFound('No account with this ID.');
      });
    
    //Destroy it!
    $account->delete();
    
  }
  
  private function save_account($account, $data)
  {
    
    //Set title.
    $account->merge($data->having('title'));
    
    //Store it.
    $account->save();
    
    //Get our buddies.
    $methods = array('paypal', 'ideal');
    foreach($methods as $method)
    {
      
      $account->{$method}
        
        //Method wasn't known yet?
        ->is('empty', function()use($account, $data, $method){
          return $account->{$method}->become(
            mk('Sql')->model('payment', 'AccountMethods')
              ->set(array(
                'account_id' => $account->id,
                'method' => strtoupper($method)
              ))
          );
        })
        
        //Insert settings we could have changed.
        ->merge(array(
          'handler' => max($data->{$method}->handler->get('int'), 0),
          'is_enabled' => $data->{$method}->handler->get('int') > 0,
          'is_test_mode' => $data->{$method}->is_test_mode->get('boolean'),
          'settings' => $data->{$method}->without('handler', 'is_test_mode')->as_json()
        ))
        
        //Store it.
        ->save();
      
    }
    
    return $account;
    
  }
  
}

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
  
}

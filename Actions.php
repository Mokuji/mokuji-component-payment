<?php namespace components\payment; if(!defined('TX')) die('No direct access.');

class Actions extends \dependencies\BaseComponent
{
  
  protected
    $permissions = array(
      // 'rabobank_omnikassa_return' => 0,
      // 'rabobank_omnikassa_report' => 0,
      'paypal_express_checkout' => 0,
      'paypal_express_checkout_return' => 0
    );
  
  protected function paypal_express_checkout($data)
  {
    
    mk('Component')->load('payment', 'methods\\paypal\\PayPalHandler', false);
    
    $tx = mk('Sql')->table('payment', 'Transactions')
      ->where('transaction_reference', "'".mk('Data')->get->tx."'")
      ->execute_single();
    
    if($tx->is_empty())
      throw \exception\NotFound('No transaction with this reference.');
    
    $handler = methods\paypal\PayPalHandler::get_handler($tx->account->get());
    $handler->set_express_checkout($tx);
    exit;
    
  }
  
  protected function paypal_express_checkout_return($data)
  {
    
    //Get the TX for now, just to find out which account we need for the handler.
    $tx = mk('Sql')->table('payment', 'Transactions')
      ->where('transaction_reference', "'".mk('Data')->get->tx."'")
      ->execute_single();
    
    mk('Component')->load('payment', 'methods\\paypal\\PayPalHandler', false);
    $handler = methods\paypal\PayPalHandler::get_handler($tx->account->get());
    
    $tx = $handler->transaction_callback(mk('Data')->request);
    
    if($tx === false)
      exit;
    
    mk('Url')->redirect(url(
      mk('Data')->session->payment->tx_return_urls->{$tx->transaction_reference->get()}->get()
    ,true));
    
    mk('Data')->session->payment->tx_return_urls->{$tx->transaction_reference->get()}->un_set();
    
  }
  
}

<?php namespace components\payment; if(!defined('TX')) die('No direct access.');

class Actions extends \dependencies\BaseComponent
{
  
  protected
    $permissions = array(
      // 'rabobank_omnikassa_return' => 0,
      // 'rabobank_omnikassa_report' => 0,
      'paypal_express_checkout' => 0,
      'paypal_express_checkout_return' => 0,
      'ing_ideal_basic_success'=> 0,
      'ing_ideal_basic_error'=> 0,
      'ing_ideal_basic_cancelled'=> 0,
      'ing_ideal_basic_xml_notification'=> 0
    );
  
  protected function paypal_express_checkout($data)
  {
    
    mk('Component')->load('payment', 'methods\\paypal\\PayPalHandler', false);
    
    $tx = mk('Sql')->table('payment', 'Transactions')
      ->where('transaction_reference', "'".mk('Data')->get->tx."'")
      ->execute_single();
    
    if($tx->is_empty())
      throw \exception\NotFound('No transaction with this reference.');
    
    $handler = methods\paypal\PayPalHandler::get_handler(data_of($tx->account));
    $handler->set_express_checkout($tx);
    exit;
    
  }
  
  protected function paypal_express_checkout_return($data)
  {
    
    //Get the TX for now, just to find out which account we need for the handler.
    $tx = mk('Sql')->table('payment', 'Transactions')
      ->where('transaction_reference', "'".mk('Data')->get->tx."'")
      ->execute_single();
    
    if($tx->is_empty())
      exit;
    
    mk('Component')->load('payment', 'methods\\paypal\\PayPalHandler', false);
    $handler = methods\paypal\PayPalHandler::get_handler(data_of($tx->account));
    
    $tx = $handler->transaction_callback(mk('Data')->request);
    
    if($tx === false)
      exit;
    
    mk('Url')->redirect(url(
      mk('Data')->session->payment->tx_return_urls->{$tx->transaction_reference->get()}->get()
    ,true));
    
    mk('Data')->session->payment->tx_return_urls->{$tx->transaction_reference->get()}->un_set();
    
  }
  
  protected function ing_ideal_basic_success($data)
  {
    
    //Get the TX to find out which account we need for the handler.
    $tx = mk('Sql')->table('payment', 'Transactions')
      ->where('transaction_reference', "'".mk('Data')->get->tx."'")
      ->execute_single();
    
    if($tx->is_empty())
      exit;
    
    $handler = methods\ideal\IdealBaseHandler::get_handler(data_of($tx->account));
    
    $tx = $handler->transaction_callback(array(
      'tx' => $tx->transaction_reference,
      'endpoint' => 'SUCCESS'
    ));
    
    if($tx === false)
      exit;
    
    mk('Url')->redirect(url(
      mk('Data')->session->payment->tx_return_urls->{$tx->transaction_reference->get()}->get()
    ,true));
    
    mk('Data')->session->payment->tx_return_urls->{$tx->transaction_reference->get()}->un_set();
    
  }
  
  protected function ing_ideal_basic_cancelled($data)
  {
    
    //Get the TX to find out which account we need for the handler.
    $tx = mk('Sql')->table('payment', 'Transactions')
      ->where('transaction_reference', "'".mk('Data')->get->tx."'")
      ->execute_single();
    
    if($tx->is_empty())
      exit;
    
    $handler = methods\ideal\IdealBaseHandler::get_handler(data_of($tx->account));
    
    $tx = $handler->transaction_callback(array(
      'tx' => $tx->transaction_reference,
      'endpoint' => 'CANCELLED'
    ));
    
    if($tx === false)
      exit;
    
    mk('Url')->redirect(url(
      mk('Data')->session->payment->tx_return_urls->{$tx->transaction_reference->get()}->get()
    ,true));
    
    mk('Data')->session->payment->tx_return_urls->{$tx->transaction_reference->get()}->un_set();
    
  }
  
  protected function ing_ideal_basic_error($data)
  {
    
    //Get the TX to find out which account we need for the handler.
    $tx = mk('Sql')->table('payment', 'Transactions')
      ->where('transaction_reference', "'".mk('Data')->get->tx."'")
      ->execute_single();
    
    if($tx->is_empty())
      exit;
    
    $handler = methods\ideal\IdealBaseHandler::get_handler(data_of($tx->account));
    
    $tx = $handler->transaction_callback(array(
      'tx' => $tx->transaction_reference,
      'endpoint' => 'ERROR'
    ));
    
    if($tx === false)
      exit;
    
    mk('Url')->redirect(url(
      mk('Data')->session->payment->tx_return_urls->{$tx->transaction_reference->get()}->get()
    ,true));
    
    mk('Data')->session->payment->tx_return_urls->{$tx->transaction_reference->get()}->un_set();
    
  }
  
  protected function ing_ideal_basic_xml_notification($data)
  {
    
    //Parse the XML.
    $xml = file_get_contents("php://input");
    $data = methods\ideal\IngIdealBasicHandler::parse_xml_callback($xml);
    
    //Get the TX for the handler.
    $tx = mk('Sql')->table('payment', 'Transactions')
      ->where('transaction_reference', "'".$data['transaction_reference']."'")
      ->execute_single();
    
    if($tx->is_empty()){
      mk('Logging')->log('Payment', 'ING iDeal Basic XML callback', 'Unknown TX: '.$data['transaction_reference']);
      exit;
    }
    
    //Get the handler with populated settings.
    $handler = $tx->handler();
    
    //Verify the callback key.
    if(!$handler->verify_xml_key(mk('Data')->get->key, $tx, $xml))
      exit;
    
    //Hand the data to the handler.
    $handler->transaction_callback($data);
    
    //Exit either way.
    exit;
    
  }
  
}

<?php namespace components\payment; if(!defined('TX')) die('No direct access.');

class Actions extends \dependencies\BaseComponent
{
  
  protected
    $permissions = array(
      'rabobank_omnikassa_return' => 0,
      'rabobank_omnikassa_report' => 0
    );
  
  protected function rabobank_omnikassa_return($data)
  {
    
    mk('Component')->load('payment', 'methods\\ideal\\BaseHandler', false);
    $handler = methods\ideal\BaseHandler::get_handler(methods\ideal\BaseHandler::TYPE_RABOBANK_OMNIKASSA);
    
    $tx = $handler->transaction_callback(
      json_decode(base64_decode(mk('Data')->session->mokuji_payment_ideal_rabobank_omnikassa_callback->get()), true)
    );
    mk('Data')->session->mokuji_payment_ideal_rabobank_omnikassa_callback->un_set();
    
    if($tx === false)
      exit;
    
    mk('Url')->redirect(
      mk('Data')->session->payment->tx_return_urls->{$tx->transaction_reference->get()}->get()
    );
    
    mk('Data')->session->payment->tx_return_urls->{$tx->transaction_reference->get()}->un_set();
    
  }
  
  protected function rabobank_omnikassa_report($data)
  {
    
    mk('Component')->load('payment', 'methods\\ideal\\BaseHandler', false);
    $handler = methods\ideal\BaseHandler::get_handler(methods\ideal\BaseHandler::TYPE_RABOBANK_OMNIKASSA);
    
    $handler->transaction_callback(
      json_decode(base64_decode(mk('Data')->session->mokuji_payment_ideal_rabobank_omnikassa_callback->get()), true)
    );
    mk('Data')->session->mokuji_payment_ideal_rabobank_omnikassa_callback->un_set();
    exit;
    
  }
  
  protected function paypal_express_checkout($data)
  {
    
    mk('Component')->load('payment', 'methods\\paypal\\PayPalHandler', false);
    
    trace($data);
    
    $tx = mk('Sql')->table('payment', 'Transactions')
      ->where('transaction_reference', "'".mk('Data')->get->tx."'")
      ->execute_single();
    
    
    if($tx->is_empty())
      throw \exception\NotFound('No transaction with this reference.');
    
    $handler = methods\paypal\PayPalHandler::get_handler();
    $handler->set_express_checkout($tx);
    exit;
    
  }
  
  protected function paypal_return($data)
  {
    
    trace(mk('Data')->get->dump());
    trace(mk('Data')->post->dump());
    
    mk('Component')->load('payment', 'methods\\paypal\\PayPalHandler', false);
    $handler = methods\paypal\PayPalHandler::get_handler();
    
    $details = $handler->get_express_checkout_details(mk('Data')->get->token);
    trace($details['response'], $details['model']->dump());
    
    if($details['response']['CHECKOUTSTATUS'] === 'PaymentActionNotInitiated')
      $handler->do_express_checkout_payment($details['model']);
    
    exit;
    
  }
  
}

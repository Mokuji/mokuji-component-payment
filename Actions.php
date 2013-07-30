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
  
}

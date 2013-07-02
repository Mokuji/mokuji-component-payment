<?php namespace components\payment\methods\ideal; if(!defined('TX')) die('No direct access.');

mk('Sql')->model('payment', 'Transaction');
use \components\payment\models\Transaction;

mk('Component')->load('payment', 'methods\\ideal\\BaseHandler', false);

//Add the library provided by Rabobank.
require_once(PATH_COMPONENTS.DS.'payment'.DS.'methods'.DS.'ideal'.DS.
  'lib-omnikassa'.DS.'omnikassa.cls.5.php');

/**
 * The Rabobank OmniKassa iDeal payment method handler.
 */
class RabobankOmniKassaHandler extends BaseHandler
{
  
  /**
   * An instance of the OmniKassa class provided by the Rabobank library.
   * @var \OmniKassa
   */
  protected $lib;
  
  /**
   * Constructs a new instance based on the Rabobank iDeal settings provided.
   * @param \depencies\Data $config Specific Rabobank iDeal settings.
   */
  public function __construct(\depencies\Data $config)
  {
    
    //Create Rabobank library instance.
    $this->lib = new \OmniKassa();
    
    //Config: Test mode
    if($config->omnikassa->test_mode->get('boolean') === true)
      $this->lib->setTestMode();
    
    //Config: Merchant
    $lib->setMerchant(
      $config->omnikassa->merchant_id->get('string'),
      $config->omnikassa->merchant_sub_id->get('string')
    );
    
    //Config: Security key
    $lib->setSecurityKey(
      $config->omnikassa->security_key->get('string'),
      $config->omnikassa->security_key_version->get('string')
    );
    
    //Handle: return and report URL's
    //These are aliases because GET parameters are forbidden. Uses .htaccess to do it anyway.
    $lib->setReportUrl(self::get_ideal_url().'rabobank-omnikassa-report.php');
    $lib->setReturnUrl(self::get_ideal_url().'rabobank-omnikassa-return.php');
    
    //Fixed value: payment method = iDeal
    $lib->setPaymentMethod('IDEAL');
    
  }
  
  /**
   * Builds the request data to send in a form starting the transaction.
   * @param  \components\payment\models\Transaction $tx The transaction model to base the transaction on.
   * @return \dependencies\Data The method, action and data to build the form with.
   */
  public function transaction_start_request(Transaction $tx)
  {
    
    //Ensure the required fields are present.
    $tx->check_required(array('order_id', 'amount'));
    
    //Add extra fields to the library.
    $this->lib->setOrderId($tx->order_id->get('string'));
    $this->lib->setAmount($tx->amount->get('double'));
    $this->lib->setTransactionReferences($tx->transaction_reference->get('string'));
    
    //Have the library build a raw dataset.
    $raw_data = $this->lib->get_raw_data();
    
    return Data(array(
      'action' => $raw_data['_action']
      'method' => 'POST',
      'data' => array(
        'Data' => $raw_data['Data'],
        'Seal' => $raw_data['Seal'],
        'InterfaceVersion' => $raw_data['InterfaceVersion']
      )
    ));
    
  }
  
  /**
   * Builds a form starting the transaction.
   * @param  \components\payment\models\Transaction $tx The transaction model to base the transaction on.
   * @param boolean $immediate Whether or not to immediately start the transaction after this output has been included.
   * @return string The HTML form.
   */
  public function transaction_start_button(Transaction $tx, $immediate=false)
  {
    
    $request = self::transaction_start_request($tx);
    
    $uname = 'tx_'.$tx->transaction_reference->get('string').'_'.sha1(uniqid($tx->order_id->get('string'), true));
    
    $html = t.'<form action="'.$request['action'].'" method="'.$request['method'].'" name="'.$uname.'">'.n;
    
    foreach($request['data'] as $name => $value)
      $html .= t.t.'<input type="hidden" name="'.$name.'" value="'.$value.'" />'.n;
    
    $html .= t.t.'<input class="payment-method ideal start-transaction" type="submit" value="'.__('payment', 'Pay with iDeal').'" />'.n;
    
    $html .= t.'</form>'.n;
    
    if($immediate === true)
      $html .= t.'<script type="text/javascript"> setTimeout(function(){ document.forms["'.$uname.'"].submit(); }, 100); </script>'.n;
    
    return $html;
    
  }
  
}

#TODO: .htaccess schrijven om de return php files te simuleren.

/*
  
  Model operaties hier doen, of toch op de models zelf?
    -> Models zelf
  
  Process TX callback
    = Set status
  
  Get status
  Refresh status (no such thing here)
  
*/
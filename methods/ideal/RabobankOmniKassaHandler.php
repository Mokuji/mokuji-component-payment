<?php namespace components\payment\methods\ideal; if(!defined('TX')) die('No direct access.');

mk('Sql')->model('payment', 'Transaction');
use \components\payment\models\Transaction;

mk('Component')->load('payment', 'methods\\ideal\\BaseHandler', false);

//Add the library provided by Rabobank.
require_once(BaseHandler::get_ideal_path().DS.'lib-omnikassa'.DS.'omnikassa.cls.5.php');

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
    if($config->rabobank->omnikassa->test_mode->get('boolean') === true)
      $this->lib->setTestMode();
    
    //Config: Merchant
    $lib->setMerchant(
      $config->rabobank->omnikassa->merchant_id->get('string'),
      $config->rabobank->omnikassa->merchant_sub_id->get('string')
    );
    
    //Config: Security key
    $lib->setSecurityKey(
      $config->rabobank->omnikassa->security_key->get('string'),
      $config->rabobank->omnikassa->security_key_version->get('string')
    );
    
    //Handle: return and report URL's
    //These are aliases because GET parameters are forbidden. Uses .htaccess to do it anyway.
    $lib->setReportUrl(self::get_ideal_url().'rabobank-omnikassa-report.php');
    $lib->setReturnUrl(self::get_ideal_url().'rabobank-omnikassa-return.php');
    
    //Fixed value: payment method = iDeal
    $lib->setPaymentMethod('IDEAL');
    
  }
  
  /**
   * Builds the request meta-data to send in a form starting the transaction.
   * @param  \components\payment\models\Transaction $tx The transaction model to base the transaction on.
   * @return \dependencies\Data The method, action and data to build the form with.
   */
  public function transaction_start_request(Transaction $tx)
  {
    
    //Ensure the required fields are present.
    if(!$tx->id->is_set())
      throw new \exception\InvalidArgument("The transaction ID is a required field.");
    
    if(!$tx->transaction_reference->is_set())
      throw new \exception\InvalidArgument("The transaction reference is a required field.");
    
    if(!$tx->total_price->is_set())
      throw new \exception\InvalidArgument("The total price is a required field.");
    
    //Check the currency.
    if(!in_array($tx->currency->get('string'), array('EUR')))
      throw new \exception\InvalidArgument("This payment method handler only supports the 'EUR' currency.");
    
    //Add extra fields to the library.
    $this->lib->setOrderId($tx->id->get('string')); #TODO: this should be an order ID from any higher-level components.
    $this->lib->setAmount($tx->total_price->get('double'));
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
    
    //Get the request information.
    $request = self::transaction_start_request($tx);
    
    //Create a unique name for the form.
    $uname = 'tx_'.$tx->transaction_reference->get('string').'_'.sha1(uniqid($tx->order_id->get('string'), true));
    
    //Build the form.
    $html = t.'<form action="'.$request['action'].'" method="'.$request['method'].'" name="'.$uname.'">'.n;
    
    //Add it's data.
    foreach($request['data'] as $name => $value)
      $html .= t.t.'<input type="hidden" name="'.$name.'" value="'.$value.'" />'.n;
    
    //Add the button.
    $html .= t.t.'<input class="payment-method ideal start-transaction" type="submit" value="'.__('payment', 'Pay with iDeal').'" />'.n;
    
    //End the form.
    $html .= t.'</form>'.n;
    
    //If we need to submit immediately, without user interaction, add this javascript.
    //I'm aware that it's really ugly. But even Rabobank provided this method, since the client must make the request.
    //An alternative would be using AJAX in a frame-like view, that's a hassle though.
    if($immediate === true)
      $html .= t.'<script type="text/javascript"> setTimeout(function(){ document.forms["'.$uname.'"].submit(); }, 100); </script>'.n;
    
    return $html;
    
  }
  
}
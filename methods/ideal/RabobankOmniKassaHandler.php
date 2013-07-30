<?php namespace components\payment\methods\ideal; if(!defined('TX')) die('No direct access.');

mk('Sql')->model('payment', 'Transactions');
use \components\payment\models\Transactions;

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
  public function __construct(\dependencies\Data $config)
  {
    
    //Create Rabobank library instance.
    $this->lib = new \OmniKassa();
    
    //Config: Test mode
    if($config->rabobank->omnikassa->test_mode->get('boolean') === true)
      $this->lib->setTestMode();
    
    //Config: Merchant
    $this->lib->setMerchant(
      $config->rabobank->omnikassa->merchant_id->get('string'),
      $config->rabobank->omnikassa->merchant_sub_id->get('string')
    );
    
    //Config: Security key
    $this->lib->setSecurityKey(
      $config->rabobank->omnikassa->security_key->get('string'),
      $config->rabobank->omnikassa->security_key_version->get('string')
    );
    
    //Handle: return and report URL's
    //These are aliases because GET parameters are forbidden. Uses .htaccess to do it anyway.
    $this->lib->setReportUrl(self::get_ideal_url().'RabobankOmniKassaReport.php');
    $this->lib->setReturnUrl(self::get_ideal_url().'RabobankOmniKassaReturn.php');
    
    //Fixed value: payment method = iDeal
    $this->lib->setPaymentMethod('IDEAL');
    
  }
  
  /**
   * Builds the request meta-data to send in a form starting the transaction.
   * @param  \components\payment\models\Transactions $tx The transaction model to base the transaction on.
   * @return \dependencies\Data The method, action and data to build the form with.
   */
  public function transaction_start_request(Transactions $tx)
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
    // $this->lib->setOrderId($tx->order_id->get('string')); #TODO: this should be an order ID from any higher-level components.
    $this->lib->setAmount($tx->total_price->get('double'));
    $this->lib->setTransactionReference($tx->transaction_reference->get('string'));
    
    //Have the library build a raw dataset.
    $raw_data = $this->lib->get_raw_data();
    
    return Data(array(
      'action' => $raw_data['_action'],
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
   * @param  \components\payment\models\Transactions $tx The transaction model to base the transaction on.
   * @param string $return_url The location where the eventual status should be reported (ie. the webshop order confirmation page).
   * @param boolean $immediate Whether or not to immediately start the transaction after this output has been included.
   * @return string The HTML form.
   */
  public function transaction_start_button(Transactions $tx, $return_url, $immediate=false)
  {
    
    //Get the request information.
    $request = self::transaction_start_request($tx);
    
    //Store the location where we should return.
    mk('Data')->session->payment->tx_return_urls->{$tx->transaction_reference->get()}->set($return_url);
    
    //Create a unique name for the form.
    $uname = 'tx_'.$tx->transaction_reference->get('string').'_'.sha1(uniqid($tx->order_id->get('string'), true));
    
    //Build the form.
    $html = t.'<form action="'.$request['action'].'" method="'.$request['method'].'" name="'.$uname.'">'.n;
    
    //Add it's data.
    foreach($request['data'] as $name => $value)
      $html .= t.t.'<input type="hidden" name="'.$name.'" value="'.$value.'" />'.n;
    
    //Add the button.
    $html .= t.t.'<input class="payment-method ideal start-transaction" type="submit" value="'.__('payment', 'Pay with iDeal', true).'" />'.n;
    
    //End the form.
    $html .= t.'</form>'.n;
    
    //If we need to submit immediately, without user interaction, add this javascript.
    //I'm aware that it's really ugly. But even Rabobank provided this method, since the client must make the request.
    //An alternative would be using AJAX in a frame-like view, that's a hassle though.
    if($immediate === true)
      $html .= t.'<script type="text/javascript"> setTimeout(function(){ document.forms["'.$uname.'"].submit(); }, 100); </script>'.n;
    
    return $html;
    
  }
  
  /**
   * Processes a callback from the Rabobank page and updates the corresponding transaction.
   * @param  array $post_data The full POST data provided with the callback.
   * @return \components\payment\models\Transactions $tx The transaction that has been updated.
   */
  public function transaction_callback($post_data)
  {
    
    $_POST = $post_data;
    $response = $this->lib->validate();
    unset($_POST);
    
    if($response === false)
      return false;
    
    $tx = mk('Sql')
      ->table('payment', 'Transactions')
      ->where('transaction_reference', "'{$response['transaction_reference']}'")
      ->execute_single();
    
    if($tx->is_empty())
      return false;
    
    if(!$tx->claim('IDEAL', self::TYPE_RABOBANK_OMNIKASSA))
      return false;
    
    $codes = array(
      '00' => 'SUCCESS',
      '05' => 'FAILED',
      '17' => 'CANCELLED',
      '60' => 'OPEN',
      '90' => 'OPEN',
      '97' => 'EXPIRED',
      '99' => 'FAILED'
    );
    
    $errors = array(
      '00' => 'NULL',
      '05' => 'General error',
      '17' => 'NULL',
      '60' => 'NULL',
      '90' => 'Failure sending in',
      '97' => 'NULL',
      '99' => 'Technical error'
    );
    
    $code = $response['raw_data']['responseCode'];
    $status = array_key_exists($code, $codes) ? $codes[$code] : 'FAILED';
    $error = array_key_exists($code, $errors) ? $errors[$code] : 'Unknown responseCode: '.$code;
    
    $tx->merge(array(
      'status' => $status,
      'error_information' => $error,
      'confirmed_amount' => ($status === 'SUCCESS' ? $tx->total_price : 0),
      'dt_status_changed' => date('Y-m-d H:i:s'),
      'dt_transaction_local' => date('Y-m-d H:i:s'),
      'dt_transaction_remote' => date('Y-m-d H:i:s', strtotime($response['raw_data']['transactionDateTime'])),
      'consumer_iban' => array_key_exists('maskedPan', $responseCode['raw_data']) ? $responseCode['raw_data']['maskedPan'] : 'NULL'
    ));
    
    return $tx->save();
    
  }
  
}
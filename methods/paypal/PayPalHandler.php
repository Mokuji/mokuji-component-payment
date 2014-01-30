<?php namespace components\payment\methods\paypal; if(!defined('TX')) die('No direct access.');

mk('Sql')->model('payment', 'Transactions');
use \components\payment\models\Transactions;

mk('Component')->load('payment', 'methods\\BaseHandler', false);
use \components\payment\methods\BaseHandler;

/**
 * A base class that serves as a factory for the different available PayPal payment method handlers as well
 * as defining common functionality across PayPal implementations.
 */
class PayPalHandler extends BaseHandler
{
  
  //The only type available for PayPal.
  const TYPE_PAYPAL = 1;
  
  protected static
    $CHECKOUT_STATUSSES = array(
      'PaymentActionNotInitiated' => 'OPEN',
      'PaymentActionInProgress' => 'OPEN',
      'PaymentActionFailed' => 'FAILED',
      'PaymentActionCompleted' => 'SUCCESS'
    ),
    $PAYMENT_STATUSSES = array(
      'Completed' => 'SUCCESS',
      'Canceled-Reversal' => 'SUCCESS',
      'Denied' => 'FAILED',
      'Expired' => 'EXPIRED',
      'Failed' => 'FAILED',
      'In-Progress' => 'OPEN',
      'None' => 'OPEN',
      'Partially-Refunded' => 'OPEN',
      'Pending' => 'OPEN',
      'Refunded' => 'CANCELLED',
      'Reversed' => 'CANCELLED',
      'Processed' => 'OPEN',
      'Voided' => 'CANCELLED',
      'Completed-Funds-Held' => 'SUCCESS'
    );
  
  /**
   * Gets a new PayPal payment method handler instance based on the provided type or the type setting.
   * @param integer $type Optional type constant identifying the type of handler.
   *                      Default: the mokuji_payment_ideal_handler configuration value.
   * @return BaseHandler  The requested type of handler.
   */
  public static function get_handler($type=null)
  {
    
    raw($type);
    
    $config = self::get_config();
    
    //When not set, retrieve the default.
    if($type === null){
      $type = $config->handler->get('integer');
    }
    
    //Require integer.
    if(!is_integer($type)){
      throw new \exception\InvalidArgument(
        'The $type variable needs to be an integer, "%s" provided. Please use the available constants.',
        gettype($type)
      );
    }
    
    //Initialize the requested type.
    switch($type){
      
      case self::TYPE_PAYPAL:
        return new PaypalHandler($config);
      
      default:
        throw new \exception\InvalidArgument('Provided type "%s" is not supported. Please use the available constants.', $type);
      
      case -1:
        throw new \exception\Programmer('Paypal has not been configured or is disabled.');
      
    }
    
  }
  
  /**
   * Gets all configuration values related to PayPal.
   * @return \dependencies\Data An associative data array of configuration values.
   */
  public static function get_config()
  {
    
    return Data(array(
      
      'handler' => mk('Config')
        ->user('mokuji_payment_paypal_handler')
        ->otherwise(-1)
        ->get('integer'),
      
      //Express Checkout
      'ec' => array(
        
        'user' => mk('Config')
          ->user('mokuji_payment_paypal_ec_user')
          ->otherwise('sdk-three_api1.sdk.com')
          ->get('string'),
        
        'pwd' => mk('Config')
          ->user('mokuji_payment_paypal_ec_pwd')
          ->otherwise('QFZCWN5HZM8VBG7Q')
          ->get('string'),
        
        'signature' => mk('Config')
          ->user('mokuji_payment_paypal_ec_signature')
          ->otherwise('A-IzJhZZjhg29XQ2qnhapuwxIDzyAZQ92FRP5dqBzVesOkzbdUONzmOU')
          ->get('string'),
        
        'description' => mk('Config')
          ->user('mokuji_payment_paypal_ec_description')
          ->get('string'),
        
        'sandbox' => mk('Config')
          ->user('mokuji_payment_paypal_ec_sandbox')
          ->otherwise(true)
          ->get('boolean')
        
      )
      
    ));
    
  }
  
  /**
   * The config for PayPal.
   * @var \dependencies\Data
   */
  protected $config;
  
  /**
   * The title used in log entries.
   * @var string
   */
  protected $title;
  
  /**
   * Constructs a new instance based on the PayPal settings provided.
   * @param \depencies\Data $config Specific PayPal settings.
   */
  public function __construct(\dependencies\Data $config)
  {
    
    $this->config = $config;
    $this->title = $this->config->ec->sandbox->get('boolean') ? 'PayPal Sandbox' : 'PayPal';
    
  }
  
  /**
   * Builds the request meta-data to send in a form starting the transaction.
   * @param  \components\payment\models\Transactions $tx The transaction model to base the transaction on.
   * @param string $return_url The location where the eventual status should be reported (ie. the webshop order confirmation page).
   * @return \dependencies\Data The method, action and data to build the form with.
   */
  public function transaction_start_request(Transactions $tx, $return_url)
  {
    
    //Ensure the required fields are present.
    if(!$tx->id->is_set())
      throw new \exception\InvalidArgument("The transaction ID is a required field.");
    
    if(!$tx->transaction_reference->is_set())
      throw new \exception\InvalidArgument("The transaction reference is a required field.");
    
    if(!$tx->total_price->is_set())
      throw new \exception\InvalidArgument("The total price is a required field.");
    
    //Store the location where we should return.
    mk('Data')->session->payment->tx_return_urls->{$tx->transaction_reference->get()}->set($return_url);
    
    return Data(array(
      'action' => url('/', true),
      'method' => 'GET',
      'data' => array(
        'action' => 'payment/paypal_express_checkout',
        'tx' => $tx->transaction_reference
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
    
    if($immediate === true)
      throw new \exception\Programmer('Not supported yet.');
    
    //Get the request information.
    $request = self::transaction_start_request($tx, $return_url);
    
    //Create a unique name for the form.
    $uname = 'tx_'.$tx->transaction_reference->get('string').'_'.sha1(uniqid($tx->order_id->get('string'), true));
    
    //Build the form.
    $html = t.'<form action="'.$request['action'].'" method="'.$request['method'].'" name="'.$uname.'">'.n;
    
    //Add it's data.
    foreach($request['data'] as $name => $value)
      $html .= t.t.'<input type="hidden" name="'.$name.'" value="'.$value.'" />'.n;
    
    //Add the button.
    $html .= t.t.'<input class="payment-method paypal start-transaction" type="submit" value="'.__('payment', 'Pay with PayPal', true).'" />'.n;
    
    //End the form.
    $html .= t.'</form>'.n;
    
    return $html;
    
  }
  
  /**
   * Processes a callback from the acquiring service and updates the corresponding transaction.
   * @param  array $post_data The full POST data provided with the callback.
   * @return \components\payment\models\Transactions $tx The transaction that has been updated.
   */
  public function transaction_callback($post_data)
  {
    
    $post_data = Data($post_data);
    
    $details = $this->get_express_checkout_details($post_data->token);
    $tx = $details['model'];
    
    if($details['response']['CHECKOUTSTATUS'] === 'PaymentActionNotInitiated')
      $this->do_express_checkout_payment($tx);
    
    mk('Logging')->log('Payment', $this->title, 'TX callback completed '.$tx->transaction_reference.' = '.$tx->status);
    
    $tx->report();
    
    return $details['model'];
    
  }
  
  /**
   * Makes a SetExpressCheckout call to the PayPal servers.
   * Note: This claims the transaction, as it should only be performed when the user selected this payment method.
   * Note: This attempt to redirect, but does not exit.
   * @param Transactions $tx The transaction to start.
   * @return \components\payment\models\Transactions $tx The transaction that has been updated.
   */
  public function set_express_checkout(Transactions $tx)
  {
    
    //Go claim it.
    if(!$tx->claim('PayPal', self::TYPE_PAYPAL))
      throw new \exception\Programmer('TX was already claimed.');
    
    $response = $this->api_call(array(
      'METHOD' => 'SetExpressCheckout',
      'RETURNURL' => (string)url('/?action=payment/paypal_express_checkout_return&tx='.$tx->transaction_reference, true),
      'CANCELURL' => (string)url('/?action=payment/paypal_express_checkout_return&tx='.$tx->transaction_reference, true),
      'PAYMENTREQUEST_0_PAYMENTACTION' => 'SALE',
      'PAYMENTREQUEST_0_CURRENCYCODE' => $tx->currency->get('string'),
      'PAYMENTREQUEST_0_AMT' => number_format($tx->total_price->get(), 2),
      'PAYMENTREQUEST_0_ITEMAMT' => number_format($tx->total_price->get(), 2),
      'PAYMENTREQUEST_0_INVNUM' => $tx->transaction_reference->get('string'),
      'L_PAYMENTREQUEST_0_NAME0' => $this->config->ec->description->get('string'),
      'L_PAYMENTREQUEST_0_AMT0' => number_format($tx->total_price->get(), 2),
      'L_PAYMENTREQUEST_0_QTY0' => '1',
      'NOSHIPPING' => '1'
    ));
    
    $tx->entry_code->set($response['TOKEN']);
    $tx->save();
    
    mk('Logging')->log('Payment', $this->title, 'Initialized express checkout '.$tx->transaction_reference);
    
    header('Location: '. 
      ($this->config->ec->sandbox->get('boolean') ?
        'https://www.sandbox.paypal.com/cgi-bin/webscr':
        'https://www.paypal.com/cgi-bin/webscr'
      ).
      '?cmd=_express-checkout&token='.
      $response['TOKEN']
    );
    
    return $tx;
    
  }
  
  public function get_express_checkout_details($token)
  {
    
    raw($token);
    
    $response = $this->api_call(array(
      'METHOD' => 'GetExpressCheckoutDetails',
      'TOKEN' => $token
    ));
    
    $tx = mk('Sql')->table('payment', 'Transactions')
      ->where('entry_code', $token)
      ->execute_single();
    
    $dr = Data($response);
    
    //Fallback would be FAILED.
    $status = array_key_exists($response['CHECKOUTSTATUS'], self::$CHECKOUT_STATUSSES) ?
      self::$CHECKOUT_STATUSSES[$response['CHECKOUTSTATUS']] : 'FAILED';
    
    $tx->merge(array(
      'status' => $status === 'SUCCESS' ? $tx->status->get() : $status,
      'dt_status_changed' => date('Y-m-d H:i:s'),
      'transaction_id_remote' => isset($response['PAYMENTREQUESTINFO_0_TRANSACTIONID']) ? $response['PAYMENTREQUESTINFO_0_TRANSACTIONID'] : null,
      'consumer_name' => $response['FIRSTNAME'].' '.$response['LASTNAME'],
      'consumer_email' => $response['EMAIL'],
      'consumer_payerid' => $response['PAYERID'],
      'error_information' => "{$dr['CHECKOUTSTATUS']}: {$dr['PAYMENTREQUESTINFO_0_SHORTMESSAGE']} {$dr['PAYMENTREQUESTINFO_0_LONGMESSAGE']} (ErrorNR {$dr['PAYMENTREQUESTINFO_0_ERRORCODE']})"
    ));
    
    $tx->save();
    
    mk('Logging')->log('Payment', $this->title, 'Got express checkout details '.$tx->transaction_reference);
    
    //Be nice to people, providing the model instance too.
    return array(
      'model' => $tx,
      'response' => $response
    );
    
  }
  
  public function do_express_checkout_payment(Transactions $tx)
  {
    
    $response = $this->api_call(array(
      'METHOD' => 'DoExpressCheckoutPayment',
      'TOKEN' => $tx->entry_code->get('string'),
      'PAYERID' => $tx->consumer_payerid->get('string'),
      'PAYMENTREQUEST_0_PAYMENTACTION' => 'SALE',
      'PAYMENTREQUEST_0_ITEMAMT' => number_format($tx->total_price->get(), 2),
      'PAYMENTREQUEST_0_AMT' => number_format($tx->total_price->get(), 2),
      'PAYMENTREQUEST_0_CURRENCYCODE' => $tx->currency->get('string'),
      'PAYMENTREQUEST_0_INVNUM' => $tx->transaction_reference->get('string')
    ));
    
    //Fallback would be FAILED.
    $status = array_key_exists($response['PAYMENTINFO_0_PAYMENTSTATUS'], self::$PAYMENT_STATUSSES) ?
      self::$PAYMENT_STATUSSES[$response['PAYMENTINFO_0_PAYMENTSTATUS']] : 'FAILED';
    
    $tx->merge(array(
      'dt_transaction_local' => date('Y-m-d H:i:s'),
      'dt_transaction_remote' => date('Y-m-d H:i:s', strtotime($response['PAYMENTINFO_0_ORDERTIME'])),
      'status' => $status,
      'dt_status_changed' => date('Y-m-d H:i:s'),
      'transaction_id_remote' => $response['PAYMENTINFO_0_TRANSACTIONID'],
      'error_information' => "{$response['PAYMENTINFO_0_PAYMENTSTATUS']}: {$response['PAYMENTINFO_0_PENDINGREASON']} (ErrorNR {$response['PAYMENTINFO_0_ERRORCODE']})"
    ));
    
    //Include the confirmed amount.
    if($tx->status->get('string') === 'SUCCESS')
      $tx->confirmed_amount->set($tx->total_price->get());
    
    $tx->save();
    
    mk('Logging')->log('Payment', $this->title, 'Completed express checkout '.$tx->transaction_reference.' = '.$tx->status);
    
    return true;
    
  }
  
  /**
   * Attempts to update the status of the transaction.
   * Note: Errors will throw an exception, but if updating was not required or not supported, FALSE will be returned.
   * @param  Transactions $tx The transaction to update the status for.
   * @return boolean Whether or not the status was updated.
   */
  public function update_status(Transactions $tx)
  {
    
    //If this TX has a status that doesn't need updating.
    if($tx->is_status_final->get('boolean') === true)
      return false;
    
    //If we do not have a transaction ID (yet) to refresh with.
    if($tx->transaction_id_remote->is_empty())
      return false;
    
    $response = $this->api_call(array(
      'METHOD' => 'GetTransactionDetails',
      'TRANSACTIONID' => $tx->transaction_id_remote->get('string')
    ));
    
    $old_status = $tx->status->get();
    $new_status = self::$PAYMENT_STATUSSES[$response['PAYMENTSTATUS']];
    
    $tx->merge(array(
      'status' => $new_status,
      'transaction_id_remote' => $response['TRANSACTIONID'],
      'error_information' => "{$response['PAYMENTSTATUS']}: {$response['PENDINGREASON']} (Reason {$response['REASONCODE']})"
    ));
    
    //Only update timestamp if the status is actually different.
    if($old_status !== $new_status)
      $tx->merge(array('dt_status_changed' => date('Y-m-d H:i:s')));
    
    //Include the confirmed amount.
    if($tx->status->get('string') === 'SUCCESS')
      $tx->confirmed_amount->set($tx->total_price->get());
    
    $tx->save();
    
    mk('Logging')->log('Payment', $this->title, 'Updated transaction '.$tx->transaction_reference.' = '.$tx->status);
    
    $tx->report();
    
    return $old_status !== $new_status;
    
  }
  
  protected function api_call($data)
  {
    
    $url = $this->config->ec->sandbox->get('boolean') ?
      'https://api-3t.sandbox.paypal.com/nvp':
      'https://api-3t.paypal.com/nvp';
    
    $data = array_merge($data, array(
      'VERSION' => '93',
      'USER' => $this->config->ec->user->get('string'),
      'PWD' => $this->config->ec->pwd->get('string'),
      'SIGNATURE' => $this->config->ec->signature->get('string')
    ));
    
    //Curl to the server.
    $response = curl_call($url, $data); #TODO: use curl_call()
    $parsed = mk('Component')->helpers('payment')->parse_query($response['data']);
    
    if($parsed['ACK'] !== 'Success'){
      trace($data, $parsed, $response);
      throw new \exception\Programmer('Error with API call.');
    }
    
    return $parsed;
    
  }
  
}
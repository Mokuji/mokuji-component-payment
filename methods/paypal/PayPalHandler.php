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
        
      )/*,
      
      'wpp' => array(
        
        'business' => mk('Config')
          ->user('mokuji_payment_paypal_wpp_business')
          ->get('string'),
        'sandbox' => mk('Config')
          ->user('mokuji_payment_paypal_wpp_sandbox')
          ->otherwise(true)
          ->get('boolean')
        
      )*/
      
    ));
    
  }
  
  /**
   * The config for PayPal.
   * @var \dependencies\Data
   */
  protected $config;
  
  /**
   * Constructs a new instance based on the PayPal settings provided.
   * @param \depencies\Data $config Specific PayPal settings.
   */
  public function __construct(\dependencies\Data $config)
  {
    
    $this->config = $config;
    
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
    
    die('Help :['.br.'I don\'t know what to do!');
    
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
      'RETURNURL' => url('/?action=payment/paypal_return&tx='.$tx->transaction_reference, true)->output,
      'CANCELURL' => url('/?action=payment/paypal_return&tx='.$tx->transaction_reference, true)->output,
      'PAYMENTREQUEST_0_PAYMENTACTION' => 'SALE',
      'PAYMENTREQUEST_0_AMT' => number_format($tx->total_price->get(), 2),
      'PAYMENTREQUEST_0_CURRENCYCODE' => $tx->currency,
      'L_PAYMENTREQUEST_0_NAME0' => $this->config->ec->description->get('string'),
      'L_PAYMENTREQUEST_0_AMT0' => number_format($tx->total_price->get(), 2),
      'L_PAYMENTREQUEST_0_QTY0' => '1',
      'NOSHIPPING' => '1'
    ));
    
    $tx->entry_code->set($response['TOKEN']);
    $tx->save();
    
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
    
    $statusses = array(
      'PaymentActionNotInitiated' => 'OPEN',
      'PaymentActionInProgress' => 'OPEN',
      'PaymentActionFailed' => 'FAILED',
      'PaymentActionCompleted' => 'SUCCESS'
    );
    
    $response = $this->api_call(array(
      'METHOD' => 'GetExpressCheckoutDetails',
      'TOKEN' => $token
    ));
    
    $tx = mk('Sql')->table('payment', 'Transactions')
      ->where('entry_code', $token)
      ->execute_single();
    
    $tx->merge(array(
      'status' => $statusses[$response['CHECKOUTSTATUS']],
      'dt_status_changed' => date('Y-m-d H:i:s'),
      'consumer_name' => $response['FIRSTNAME'].' '.$response['LASTNAME'],
      'consumer_email' => $response['EMAIL'],
      'consumer_payerid' => $response['PAYERID']
    ));
    
    $tx->save();
    
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
      'PAYMENTREQUEST_0_AMT' => number_format($tx->total_price->get(), 2),
      'PAYMENTREQUEST_0_CURRENCYCODE' => $tx->currency->get('string')
    ));
    
    $statusses = array(
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
    
    $tx->merge(array(
      'status' => $statusses[$response['PAYMENTINFO_0_PAYMENTSTATUS']],
      'dt_status_changed' => date('Y-m-d H:i:s'),
      'error_information' => "{$response['PAYMENTINFO_0_PAYMENTSTATUS']} | {$response['PAYMENTINFO_0_PENDINGREASON']} | {$response['PAYMENTINFO_0_ERRORCODE']}"
    ));
    
    $tx->save();
    
    trace($response);
    exit;
    
    return $tx;
    
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
    $response = mk('Component')->helpers('payment')->curl('POST', $url, $data);
    $parsed = mk('Component')->helpers('payment')->parse_query($response);
    
    if($parsed['ACK'] !== 'Success'){
      trace($data, $parsed);
      throw new \exception\Programmer('Error with API call.');
    }
    
    return $parsed;
    
  }
  
  // /**
  //  * The path to the iDeal payment method folder.
  //  * @return string Absolute path to the iDeal payment method folder.
  //  */
  // public static function get_ideal_path()
  // {
  //   return PATH_COMPONENTS.DS.'payment'.DS.'methods'.DS.'ideal';
  // }
  
  // /**
  //  * The URL to the iDeal payment method folder.
  //  * @return string Absolute URL to the iDeal payment method folder.
  //  */
  // public static function get_ideal_url()
  // {
  //   return URL_COMPONENTS.'payment/methods/ideal/';
  // }
  
}
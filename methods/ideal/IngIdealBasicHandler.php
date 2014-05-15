<?php namespace components\payment\methods\ideal; if(!defined('MK')) die('No direct access.');

use \components\payment\models\Transactions;

/**
 * The ING iDeal Basic payment method handler.
 */
class IngIdealBasicHandler extends IdealBaseHandler
{
  
  CONST TRANSACTION_VALIDITY_TIME = 3600; //Max: 1 hour
  
  /**
   * The title of this handler, used for logging.
   * @var string
   */
  protected $title;
  
  /**
   * The configuration provided to the constructor.
   * @var Data
   */
  protected $config;
  
  /**
   * Constructs a new instance based on the iDeal settings provided.
   * @param \depencies\Data $config Specific iDeal settings.
   */
  public function __construct(\dependencies\Data $config)
  {
    
    //Set title.
    $this->title = "ING iDeal Basic".
      ($config->ing->ideal_basic->test_mode->get('boolean') ? ' TestMode' : '');
    
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
    
    //Validate.
    $this->validate_transaction($tx);
    
    //Store the location where we should return.
    mk('Data')->session->payment->tx_return_urls->{$tx->transaction_reference->get()}->set((string)$return_url);
    
    return Data(array(
      'action' => URL_COMPONENTS.'payment/methods/ideal/IngIdealBasicStart.php',
      'method' => 'GET',
      'data' => array('tx' => $tx->transaction_reference)
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
    $request = self::transaction_start_request($tx, $return_url);
    
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
    //I'm aware that it's really ugly. But even Rabobank provided this method, since the client must make the requests.
    if($immediate === true)
      $html .= t.'<script type="text/javascript"> setTimeout(function(){ document.forms["'.$uname.'"].submit(); }, 100); </script>'.n;
    
    return $html;
    
  }
  
  /**
   * The second stage for the iDeal payment button. Automatically submitting form to submit POST data to ING.
   * @param  array $request_data The full REQUEST data provided with the callback.
   * @return string The form HTML.
   */
  public function generate_to_ing_form($request_data)
  {
    
    //Get transaction.
    $tx = mk('Sql')->table('payment', 'Transactions')
      ->where('transaction_reference', mk('Sql')->escape($request_data['tx']))
      ->execute_single();
    
    //Validate.
    $this->validate_transaction($tx);
    
    //Must claim at this point.
    if(!$tx->claim('IDEAL', IdealBaseHandler::TYPE_ING_IDEAL_BASIC)){
      throw new \exception\Programmer(
        'This transaction was already claimed by %s. Please complete the transaction or return to the checkout page.',
        strtolower($tx->method->get())
      );
    }
    
    $amount = round($tx->total_price->get('double') * 100);
    $time = time();
    
    //Formulate form information.
    $request = Data(array(
      
      'method' => 'POST',
      'action' => $this->config->ing->ideal_basic->test_mode->get('boolean') ?
        'https://idealtest.secure-ing.com/ideal/mpiPayInitIng.do':
        'https://ideal.secure-ing.com/ideal/mpiPayInitIng.do',
      
      'data' => array(
        
        'merchantID' => $this->config->ing->ideal_basic->merchant_id,
        'subID' => $this->config->ing->ideal_basic->merchant_sub_id,
        'amount' => $amount,
        'purchaseID' => $tx->transaction_reference,
        'language' => 'nl',
        'currency' => $tx->currency,
        'description' => $this->config->ing->ideal_basic->description,
        'paymentType' => 'ideal',
        'validUntil' => date('Y-m-d\TH:i:s.000\Z', $time+self::TRANSACTION_VALIDITY_TIME),
        
        #TODO: Decide if this should display actual products.
        'itemNumber1' => substr($tx->transaction_reference->get('string'), 0, 12),
        'itemDescription1' => $this->config->ing->ideal_basic->description,
        'itemQuantity1' => 1,
        'itemPrice1' => $amount,
        
        'urlSuccess' => url('/?action=payment/ing_ideal_basic_success/post&tx='.$tx->transaction_reference, true),
        'urlCancel' => url('/?action=payment/ing_ideal_basic_cancel/post&tx='.$tx->transaction_reference, true),
        'urlError' => url('/?action=payment/ing_ideal_basic_error/post&tx='.$tx->transaction_reference, true),
        
        'hash' => $this->create_hash($tx, $time)
        
      )
      
    ));
    
    //Create a unique name for the form.
    $uname = 'tx_'.$tx->transaction_reference->get('string').'_'.sha1(uniqid($tx->order_id->get('string'), true));
    
    //Build the form.
    $html = t.'<form action="'.$request['action'].'" method="'.$request['method'].'" name="'.$uname.'">'.n;
    
    //Add it's data.
    foreach($request['data'] as $name => $value)
      $html .= t.t.'<input type="hidden" name="'.$name.'" value="'.htmlentities($value).'" />'.n;
    
    //End the form.
    $html .= t.'</form>'.n;
    
    //I'm aware that it's really ugly. But even Rabobank provided this method, since the client browser must make the requests.
    $html .= t.'<script type="text/javascript"> setTimeout(function(){ document.forms["'.$uname.'"].submit(); }, 100); </script>'.n;
    
    return $html;
    
  }
  
  /**
   * Processes a callback from the ING page and updates the corresponding transaction.
   * @param  array $post_data The full POST data provided with the callback.
   * @return \components\payment\models\Transactions $tx The transaction that has been updated.
   */
  public function transaction_callback($post_data)
  {
    throw new \exception\Programmer('Not implemented yet.');
  }
  
  /**
   * Attempts to update the status of the transaction.
   * Note: Errors will throw an exception, but if updating was not required or not supported, FALSE will be returned.
   * @param  Transactions $tx The transaction to update the status for.
   * @return boolean Whether or not the status was updated.
   */
  public function update_status(Transactions $tx)
  {
    
    /*
      ING iDeal Basic only sends reports from the server.
      Polling for a status is not supported.
    */
    
    return false;
    
  }
  
  /**
   * Creates a hashcode to sign the POST parameters using the ING format.
   * @param  Transactions $tx   The transaction this applies to.
   * @param  integer      $time The unix timestamp to use in the calculations.
   * @return string The hash to sign the POST parameters with.
   */
  protected function create_hash(Transactions $tx, $time)
  {
    
    //Shortcut
    $cf = $this->config->ing->ideal_basic;
    
    $amount = round($tx->total_price->get('double') * 100);
    
    //Gather all the input values.
    $input =
      
      //Start with our secret.
      $cf->secret_key.
      
      //Meta-data.
      $cf->merchant_id.
      $cf->merchant_sub_id.
      $amount.
      $tx->transaction_reference.
      'ideal'.
      date('Y-m-d\TH:i:s.000\Z', $time+self::TRANSACTION_VALIDITY_TIME).
      
      //Product 1.
      substr($tx->transaction_reference->get('string'), 0, 12).
      $this->config->ing->ideal_basic->description.
      '1'.
      $amount
      
    ;//End $input
    
    //Clean up forbidden characters.
    $input = preg_replace("~[ \t\n]~", '', $input);
    $input = preg_replace(
      array('~&amp;~i','~&lt;~i','~&gt;~i','~&quot;~i',),
      array(   '&',      '<',      '>',        '"'),
      $input);
    
    return sha1($input);
    
  }
    
}
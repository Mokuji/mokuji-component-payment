<?php namespace components\payment\methods\ideal; if(!defined('MK')) die('No direct access.');

use \components\payment\models\Transactions;

/**
 * The ING iDeal Basic payment method handler.
 */
class IngIdealBasicHandler extends IdealBaseHandler
{
  
  CONST TRANSACTION_VALIDITY_TIME = 3600; //Max: 1 hour
  
  private static $ENDPOINT_MAPPING = array(
    'CANCELLED' => 'CANCELLED',
    'SUCCESS' => 'SUCCESS',
    'ERROR' => 'OPEN'
  );
  
  private static $XML_STATUS_MAPPING = array(
    'Open' => 'OPEN',
    'Expired' => 'EXPIRED',
    'Success' => 'SUCCESS',
    'Cancelled' => 'CANCELLED',
    '' => 'FAILED'
  );
  
  /**
   * A method to parse XML information callbacks.
   * @param  string $xml_string The raw XML as it was received.
   * @return Data An array with data that can be merged directly into a Transactions model.
   */
  public static function parse_xml_callback($xml_string)
  {
    
    //Root document.
    $doc = new \DOMDocument();
    $doc->loadXML($xml_string);
    $xpath = new \DOMXpath($doc);
    $xpath->registerNamespace('msg', 'http://www.idealdesk.com/Message');
    
    //Notification (root) element.
    $notification = $xpath->query('//msg:Notification')->item(0);
    
    //Data nodes.
    $timestampEl = $xpath->query('//msg:Notification/msg:createDateTimeStamp')->item(0);
    $transactionIdEl = $xpath->query('//msg:Notification/msg:transactionID')->item(0);
    $purchaseIdEl = $xpath->query('//msg:Notification/msg:purchaseID')->item(0);
    $statusEl = $xpath->query('//msg:Notification/msg:status')->item(0);
    
    //Parse this information.
    $data = Data();
    
    //Status name.
    if(array_key_exists($statusEl->nodeValue, self::$XML_STATUS_MAPPING)){
      $data->merge(array(
        'status' => self::$XML_STATUS_MAPPING[$statusEl->nodeValue],
        'error_information' => 'NULL' //We can remove this now that we have a proper status.
      ));
    }
    
    //Remote Transaction ID.
    $data->merge(array('transaction_id_remote' => $transactionIdEl->nodeValue));
    
    //Creation timestamp.
    preg_match('~(\d{4})[ \-:]?(\d{2})[ \-:]?(\d{2})[ \-:]?(\d{2})[ \-:]?(\d{2})[ \-:]?(\d{2})~', $timestampEl->nodeValue, $matches);
    $time = mktime(
      $matches[4], //hour
      $matches[5], //minute
      $matches[6], //second
      $matches[2], //month
      $matches[3], //day
      $matches[1]  //year
    );
    $data->merge(array('dt_transaction_remote'=>date('Y-m-d H:i:s', $time)));
    
    //Transaction reference.
    $data->merge(array('transaction_reference' => $purchaseIdEl->nodeValue));
    
    return $data;
    
  }
  
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
    
    $hash = $this->create_hash($tx, $time);
    
    //Formulate form information.
    $request = Data(array(
      
      'method' => 'POST',
      'action' => $this->config->ing->ideal_basic->test_mode->get('boolean') ?
        'https://idealtest.secure-ing.com/ideal/mpiPayInitIng.do':
        'https://ideal.secure-ing.com/ideal/mpiPayInitIng.do',
      
      'data' => array(
        
        'merchantID' => $this->config->ing->ideal_basic->merchant_id,
        'subID' => $this->config->ing->ideal_basic->merchant_sub_id,
        'purchaseID' => $tx->transaction_reference,
        'amount' => $amount,
        'currency' => $tx->currency,
        'language' => 'nl',
        'description' => $this->config->ing->ideal_basic->description,
        'paymentType' => 'ideal',
        'validUntil' => date('Y-m-d\TH:i:s.000\Z', $time+self::TRANSACTION_VALIDITY_TIME),
        
        #TODO: Decide if this should display actual products.
        'itemNumber1' => 1,
        'itemDescription1' => $this->config->ing->ideal_basic->description,
        'itemQuantity1' => 1,
        'itemPrice1' => $amount,
        
        'urlSuccess' => url('/?action=payment/ing_ideal_basic_success/post&tx='.$tx->transaction_reference, true),
        'urlCancel' => url('/?action=payment/ing_ideal_basic_cancelled/post&tx='.$tx->transaction_reference, true),
        'urlError' => url('/?action=payment/ing_ideal_basic_error/post&tx='.$tx->transaction_reference, true),
        
        'hash' => sha1($hash)
        
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
    
    //Was it an XML callback?
    if(isset($post_data['transaction_id_remote'])){
      
      $tx = mk('Sql')->table('payment', 'Transactions')
        ->where('transaction_reference', "'".$post_data['transaction_reference']."'")
        ->execute_single();
      
      $tx->merge($post_data);
      return $tx->save();
      
    }
    
    //Assume an endpoint callback.
    else{
      
      //Fetch the TX.
      $tx = mk('Sql')->table('payment', 'Transactions')
        ->where('transaction_reference', "'".$post_data['tx']."'")
        ->execute_single();
      
      //Must be found.
      if($tx->is_empty())
        return false;
      
      //Check the endpoint value.
      if(!array_key_exists($post_data['endpoint'], self::$ENDPOINT_MAPPING))
        throw new \exception\Programmer('Unknown endpoint value %s', $post_data['endpoint']);
      
      //Set the status and callback information we can determine.
      $tx->merge(array(
        'status' => self::$ENDPOINT_MAPPING[$post_data['endpoint']],
        'dt_transaction_local' => date('Y-m-d H:i:s'),
        'dt_status_changed' => date('Y-m-d H:i:s'),
        'confirmed_amount' => $post_data['endpoint'] === 'SUCCESS' ? $tx->total_price : 0,
        'error_information' => $post_data['endpoint'] === 'ERROR' ?
          transf('payment', 'AMBIGUOUS_ERROR_CALLBACK', date('Y-m-d H:i:s')): 'NULL'
      ));
      
      //Return the newly updated TX.
      return $tx->save();
      
    }
    
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
      '1'.
      $cf->description.
      '1'.
      $amount
      
    ;//End $input
    
    //Clean up forbidden characters.
    $input = preg_replace("~[ \t\n]~", '', $input);
    $input = preg_replace(
      array('~&amp;~i','~&lt;~i','~&gt;~i','~&quot;~i',),
      array(   '&',      '<',      '>',        '"'),
      $input);
    
    return $input;
    
  }
  
}
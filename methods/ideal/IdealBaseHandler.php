<?php namespace components\payment\methods\ideal; if(!defined('TX')) die('No direct access.');

use \components\payment\models\Accounts;
use \components\payment\models\Transactions;
use \components\payment\methods\BaseHandler;
use \components\payment\methods\ideal\RabobankOmniKassaHandler;

/**
 * A base class that serves as a factory for the different available iDeal payment method handlers as well
 * as defining common functionality across iDeal implementations.
 */
abstract class IdealBaseHandler extends BaseHandler
{
  
  //iDeal payment method handler types available.
  const TYPE_RABOBANK_OMNIKASSA = 1;
  
  /**
   * Gets a new iDeal payment method handler instance based on the provided type or the type setting.
   * @param Accounts $account The account to get the associated handler of. Default: the first account.
   * @return BaseHandler  The requested type of handler.
   */
  public static function get_handler(Accounts $account=null)
  {
    
    //Default account?
    if(!$account || $account->is_empty()){
      
      //Get the "Main account" (lowest ID).
      $account = mk('Sql')->table('payment', 'Accounts')
        ->order('id')
        ->execute_single();
      
    }
    
    //Disabled?
    if(!$account->is_enabled->get('boolean'))
      throw new \exception\Programmer('No iDeal payment method has been configured or provided.');
    
    $config = self::get_config($account);
    
    
    //Initialize the requested type.
    switch($account->handler->get('int')){
      
      case self::TYPE_RABOBANK_OMNIKASSA:
        return new RabobankOmniKassaHandler($config);
      
      default:
        throw new \exception\InvalidArgument(
          'Provided type "%s" is not supported. Please use the available constants.',
          $account->handler->get('int')
        );
      
    }
    
  }
  
  /**
   * Gets all configuration values related to iDeal.
   * @param Accounts $account The account to get the config of.
   * @return \dependencies\Data An associative data array of configuration values.
   */
  public static function get_config(Accounts $account)
  {
    
    return Data(array(
      
      'handler' => $account->handler,
      
      'rabobank' => array(
        
        'omnikassa' => array(
          'merchant_id' => $account->ideal->settings_object->merchant_id,
          'merchant_sub_id' => $account->ideal->settings_object->merchant_sub_id,
          'security_key' => $account->ideal->settings_object->security_key,
          'security_key_version' => $account->ideal->settings_object->security_key_version,
          'test_mode' => $account->ideal->is_test_mode,
        )
        
      )
      
    ));
    
  }
  
  /**
   * Builds the request meta-data to send in a form starting the transaction.
   * @param  \components\payment\models\Transactions $tx The transaction model to base the transaction on.
   * @param string $return_url The location where the eventual status should be reported (ie. the webshop order confirmation page).
   * @return \dependencies\Data The method, action and data to build the form with.
   */
  // abstract public function transaction_start_request(Transactions $tx, $return_url);
  
  /**
   * Builds a form starting the transaction.
   * @param  \components\payment\models\Transactions $tx The transaction model to base the transaction on.
   * @param string $return_url The location where the eventual status should be reported (ie. the webshop order confirmation page).
   * @param boolean $immediate Whether or not to immediately start the transaction after this output has been included.
   * @return string The HTML form.
   */
  // abstract public function transaction_start_button(Transactions $tx, $return_url, $immediate=false);
  
  /**
   * Processes a callback from the acquiring service and updates the corresponding transaction.
   * @param  array $post_data The full POST data provided with the callback.
   * @return \components\payment\models\Transactions $tx The transaction that has been updated.
   */
  // abstract public function transaction_callback($post_data);
  
  /**
   * Attempts to update the status of the transaction.
   * Note: Errors will throw an exception, but if updating was not required or not supported, FALSE will be returned.
   * @param  Transactions $tx The transaction to update the status for.
   * @return boolean Whether or not the status was updated.
   */
  // abstract public function update_status(Transactions $tx);
  
  /**
   * The path to the iDeal payment method folder.
   * @return string Absolute path to the iDeal payment method folder.
   */
  public static function get_ideal_path()
  {
    return PATH_COMPONENTS.DS.'payment'.DS.'methods'.DS.'ideal';
  }
  
  /**
   * The URL to the iDeal payment method folder.
   * @return string Absolute URL to the iDeal payment method folder.
   */
  public static function get_ideal_url()
  {
    return URL_COMPONENTS.'payment/methods/ideal/';
  }
  
}
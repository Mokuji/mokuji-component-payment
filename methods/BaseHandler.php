<?php namespace components\payment\methods; if(!defined('TX')) die('No direct access.');

mk('Sql')->model('payment', 'Transactions');
use \components\payment\models\Transactions;

/**
 * A base class that serves as a factory for the different available payment method handlers as well
 * as defining common functionality across implementations.
 */
abstract class BaseHandler
{
  
  /**
   * Gets a new payment method handler instance based on the provided type or the type setting.
   * @param integer $type Optional type constant identifying the type of handler.
   *                      Default: the handler set in the corresponding configuration value.
   * @return BaseHandler  The requested type of handler.
   */
  // abstract public static function get_handler($type=null);
  
  /**
   * Gets all configuration values related to this payment method.
   * @return \dependencies\Data An associative data array of configuration values.
   */
  // abstract public static function get_config();
  
  /**
   * Builds the request meta-data to send in a form starting the transaction.
   * @param  \components\payment\models\Transactions $tx The transaction model to base the transaction on.
   * @param string $return_url The location where the eventual status should be reported (ie. the webshop order confirmation page).
   * @return \dependencies\Data The method, action and data to build the form with.
   */
  abstract public function transaction_start_request(Transactions $tx, $return_url);
  
  /**
   * Builds a form starting the transaction.
   * @param  \components\payment\models\Transactions $tx The transaction model to base the transaction on.
   * @param string $return_url The location where the eventual status should be reported (ie. the webshop order confirmation page).
   * @param boolean $immediate Whether or not to immediately start the transaction after this output has been included.
   * @return string The HTML form.
   */
  abstract public function transaction_start_button(Transactions $tx, $return_url, $immediate=false);
  
  /**
   * Processes a callback from the acquiring service and updates the corresponding transaction.
   * @param  array $post_data The full POST data provided with the callback.
   * @return \components\payment\models\Transactions $tx The transaction that has been updated.
   */
  abstract public function transaction_callback($post_data);
  
}
<?php namespace components\payment\methods\ideal; if(!defined('TX')) die('No direct access.');

/**
 * A base class that serves as a factory for the different available iDeal payment method handlers as well
 * as defining common functionality across iDeal implementations.
 */
abstract class BaseHandler
{
  
  //iDeal payment method handler types available.
  const TYPE_RABOBANK_OMNIKASSA = 1;
  
  /**
   * Gets a new iDeal payment method handler instance based on the provided type or the type setting.
   * @param integer $type Optional type constant identifying the type of handler.
   *                      Default: the mokuji_payment_ideal_handler configuration value.
   * @return IdealHandler The requested type of handler.
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
      
      case self::TYPE_RABOBANK_OMNIKASSA:
        tx('Component')->load('payment', 'methods\\ideal\\RabobankOmniKassaHandler', false);
        return new RabobankOmniKassaHandler($config);
      
      default:
        throw new \exception\InvalidArgument('Provided type "%s" is not supported. Please use the available constants.', $type);
      
      case -1:
        throw new \exception\Programmer('No iDeal payment method has been configured or provided.');
      
    }
    
  }
  
  /**
   * Gets all configuration values related to iDeal.
   * @return \dependencies\Data An associative data array of configuration values.
   */
  public static function get_config()
  {
    
    return Data(array(
      
      'handler' => mk('Config')
        ->user('mokuji_payment_ideal_handler')
        ->otherwise(-1)
        ->get('integer'),
      
      'rabobank' => array(
        
        'omnikassa' => array(
          
          'merchant_id' => mk('Config')
            ->user('mokuji_payment_ideal_rabobank_omnikassa_merchant_id')
            ->otherwise('002020000000001')
            ->get('string'),
          
          'merchant_sub_id' => mk('Config')
            ->user('mokuji_payment_ideal_rabobank_omnikassa_merchant_sub_id')
            ->otherwise('0')
            ->get('string'),
          
          'security_key' => mk('Config')
            ->user('mokuji_payment_ideal_rabobank_omnikassa_security_key')
            ->otherwise('002020000000001_KEY1')
            ->get('string'),
            
          'security_key_version' => mk('Config')
            ->user('mokuji_payment_ideal_rabobank_omnikassa_security_key_version')
            ->otherwise('1')
            ->get('string'),
          
          'test_mode' => mk('Config')
            ->user('mokuji_payment_ideal_rabobank_omnikassa_security_test_mode')
            ->otherwise(true)
            ->get('boolean')
          
        )
        
      )
      
    ));
    
  }
  
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
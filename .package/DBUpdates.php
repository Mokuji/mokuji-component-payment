<?php namespace components\payment; if(!defined('MK')) die('No direct access.');

use \components\payment\methods\paypal\PayPalHandler;
use \components\payment\methods\ideal\IdealBaseHandler;

class DBUpdates extends \components\update\classes\BaseDBUpdates
{
  
  protected
    $component = 'payment',
    $updates = array(
      
      '0.0.1-alpha' => '0.0.2-alpha',
      '0.0.2-alpha' => '0.0.3-alpha',
      '0.0.3-alpha' => '0.0.4-alpha',
      '0.0.4-alpha' => '0.0.5-alpha',
      '0.0.5-alpha' => '0.0.6-alpha',
      '0.0.6-alpha' => '0.0.7-alpha',
      '0.0.7-alpha' => '0.0.8-alpha',
      
      '0.0.8-alpha' => '0.2.0-beta', //No DB updates.
      '0.1.0-beta' => '0.2.0-beta', //No DB updates.
      '0.1.1-beta' => '0.2.0-beta', //No DB updates.
      
      '0.1.2-beta' => '0.2.0-beta'
      
    );
  
  protected function update_to_0_2_0_beta($current_version, $forced)
  {
    
    if($forced){
      mk('Sql')->query("DROP TABLE IF EXISTS `#__payment_accounts`");
      mk('Sql')->query("DROP TABLE IF EXISTS `#__payment_account_methods`");
    }
    
    mk('Sql')->query("
      CREATE TABLE `#__payment_accounts` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NULL,
        PRIMARY KEY (`id`),
        INDEX `title` (`title`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
    
    mk('Sql')->query("
      CREATE TABLE `#__payment_account_methods` (
        `account_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `method` ENUM('IDEAL','PAYPAL') NOT NULL,
        `handler` int(10) unsigned NOT NULL
        `is_enabled` bit(1) NOT NULL DEFAULT b'0',
        `is_test_mode` bit(1) NOT NULL DEFAULT b'0',
        `settings` TEXT NOT NULL,
        PRIMARY KEY (`account_id`, `method`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8
    ");
    
    //Make an account to migrate config values to.
    $account = mk('Sql')->model('payment', 'Accounts')
      ->set(array('title' => __('payment', 'Main account', true)))
      ->save();
    
    //Migrate PayPal Express Checkout.
    if(mk('Config')->user('mokuji_payment_paypal_handler')->otherwise(-1)->get('integer') > 0){
      
      mk('Sql')->model('payment', 'AccountMethods')
        ->set(array(
          
          'account_id' => $account->id,
          'method' => "PAYPAL",
          'handler' => PayPalHandler::TYPE_PAYPAL,
          'is_enabled' => true,
          
          'is_test_mode' => mk('Config')
            ->user('mokuji_payment_paypal_ec_sandbox')
            ->is('empty', function($pair){ if($pair->get() === null) $pair->set(true); })
            ->get('boolean'),
          
          'settings' => Data(array(
            'user' => mk('Config')
              ->user('mokuji_payment_paypal_ec_user')
              ->otherwise('sdk-three_api1.sdk.com'),
            
            'pwd' => mk('Config')
              ->user('mokuji_payment_paypal_ec_pwd')
              ->otherwise('QFZCWN5HZM8VBG7Q'),
            
            'signature' => mk('Config')
              ->user('mokuji_payment_paypal_ec_signature')
              ->otherwise('A-IzJhZZjhg29XQ2qnhapuwxIDzyAZQ92FRP5dqBzVesOkzbdUONzmOU'),
            
            'description' => mk('Config')
              ->user('mokuji_payment_paypal_ec_description'),
            
          ))->as_json()
          
        ))
        ->save();
      
    }
    
    //Migrate Rabobank Omnikassa.
    if(mk('Config')->user('mokuji_payment_ideal_handler')->otherwise(-1)->get('integer') > 0){
      
      mk('Sql')->model('payment', 'AccountMethods')
        ->set(array(
          
          'account_id' => $account->id,
          'method' => "IDEAL",
          'handler' => IdealBaseHandler::TYPE_RABOBANK_OMNIKASSA,
          'is_enabled' => true,
          
          'is_test_mode' => mk('Config')
            ->user('mokuji_payment_ideal_rabobank_omnikassa_test_mode')
            ->is('empty', function($pair){ if($pair->get() === null) $pair->set(true); })
            ->get('boolean'),
          
          'settings' => Data(array(
            
            'merchant_id' => mk('Config')
              ->user('mokuji_payment_ideal_rabobank_omnikassa_merchant_id')
              ->otherwise('002020000000001'),
            
            'merchant_sub_id' => mk('Config')
              ->user('mokuji_payment_ideal_rabobank_omnikassa_merchant_sub_id')
              ->otherwise('0'),
            
            'security_key' => mk('Config')
              ->user('mokuji_payment_ideal_rabobank_omnikassa_security_key')
              ->otherwise('002020000000001_KEY1'),
              
            'security_key_version' => mk('Config')
              ->user('mokuji_payment_ideal_rabobank_omnikassa_security_key_version')
              ->otherwise('1'),
              
          ))->as_json()
          
        ))
        ->save();
      
    }
    
    try{
      
      mk('Sql')->query("
        ALTER TABLE `#__payment_transactions`
          ADD COLUMN `account_id` int(10) unsigned NOT NULL DEFAULT '0' after `id`
      ");
      
      //Set all old transactions to the newly created "Main account".
      mk('Sql')->query(mk('Sql')->make_query("
        UPDATE `#__payment_transactions`
          SET `account_id` = ?
          WHERE `account_id` = 0
      ", $account->id));
      
      mk('Sql')->query("
        DELETE FROM `#__core_config`
          WHERE `key` LIKE 'mokuji_payment_%'
          AND `site_id` = '".mk('Site')->id."'
      ");
      
    }catch(\exception\Sql $ex){
      if(!$forced) throw $ex; //Throw when not forced.
    }
    
  }
  
  protected function update_to_0_0_8_alpha($current_version, $forced)
  {
    
    try{
      
      mk('Sql')->query("
        ALTER TABLE `#__payment_transactions`
          ADD COLUMN `report_helper` varchar(255) NULL DEFAULT NULL
      ");
      
    }catch(\exception\Sql $ex){
      //When it's not forced, this is a problem.
      //But when forcing, ignore this.
      if(!$forced) throw $ex;
    }
    
  }
  
  protected function update_to_0_0_7_alpha($current_version, $forced)
  {
    
    try{
      
      mk('Sql')->query("
        ALTER TABLE `#__payment_transactions`
          ADD COLUMN `dt_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      ");
      
    }catch(\exception\Sql $ex){
      //When it's not forced, this is a problem.
      //But when forcing, ignore this.
      if(!$forced) throw $ex;
    }
    
  }
  
  protected function update_to_0_0_6_alpha($current_version, $forced)
  {
    
    try{
      
      mk('Sql')->query("
        ALTER TABLE `#__payment_transactions`
          ADD COLUMN `transaction_id_remote` varchar(255) NULL DEFAULT NULL AFTER `dt_status_changed`
      ");
      
    }catch(\exception\Sql $ex){
      //When it's not forced, this is a problem.
      //But when forcing, ignore this.
      if(!$forced) throw $ex;
    }
    
  }
  
  protected function update_to_0_0_5_alpha($current_version, $forced)
  {
    
    try{
      
      mk('Sql')->query("
        ALTER TABLE `#__payment_transactions`
          ADD COLUMN `consumer_payerid` varchar(255) NULL DEFAULT NULL AFTER `consumer_email`
      ");
      
    }catch(\exception\Sql $ex){
      //When it's not forced, this is a problem.
      //But when forcing, ignore this.
      if(!$forced) throw $ex;
    }
    
  }
  
  protected function update_to_0_0_4_alpha($current_version, $forced)
  {
    
    try{
      
      mk('Sql')->query("
        ALTER TABLE `#__payment_transactions`
          CHANGE COLUMN `method` `method` ENUM('IDEAL','PAYPAL') NULL DEFAULT NULL AFTER `id`
      ");
      
    }catch(\exception\Sql $ex){
      //When it's not forced, this is a problem.
      //But when forcing, ignore this.
      if(!$forced) throw $ex;
    }
    
  }
  
  protected function update_to_0_0_3_alpha($current_version, $forced)
  {
    
    //Queue self-deployment with CMS component.
    $this->queue(array(
      'component' => 'cms',
      'min_version' => '0.4.1-beta'
      ), function($version){
        
        mk('Component')->helpers('cms')->_call('ensure_pagetypes', array(
          array(
            'name' => 'payment',
            'title' => 'Payment'
          ),
          array(
            'manager' => 'DELETE',
            'settings' => 'SETTINGS'
          )
        ));
        
      }
    ); //END - Queue CMS
    
  }
  
  protected function update_to_0_0_2_alpha($current_version, $forced)
  {
    
    if($forced){
      mk('Sql')->query("DROP TABLE IF EXISTS `#__payment_transactions`");
    }
    
    mk('Sql')->query("
      CREATE TABLE `#__payment_transactions` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `method` ENUM('IDEAL') NULL,
        `handler` int(10) unsigned NULL,
        `transaction_reference` varchar(255) NULL,
        `dt_transaction_local` DATETIME NULL,
        `dt_transaction_remote` DATETIME NULL DEFAULT NULL,
        `currency` varchar(10) NULL,
        `total_price` double NULL,
        `confirmed_amount` double NOT NULL DEFAULT '0',
        `status` ENUM('SUCCESS', 'CANCELLED', 'EXPIRED', 'OPEN', 'FAILED', 'UNCONFIRMED') NOT NULL DEFAULT 'UNCONFIRMED',
        `dt_status_changed` DATETIME NULL DEFAULT NULL,
        `error_information` TEXT NULL DEFAULT NULL,
        `entry_code` varchar(255) NULL DEFAULT NULL,
        `dt_entry_code_expires` DATETIME NULL DEFAULT NULL,
        `consumer_name` varchar(255) NULL DEFAULT NULL,
        `consumer_email` varchar(255) NULL DEFAULT NULL,
        `consumer_iban` varchar(255) NULL DEFAULT NULL,
        `consumer_bic` varchar(255) NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        INDEX `transaction_reference` (`transaction_reference`),
        INDEX `status` (`status`)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8
    ");
    
  }
  
  protected function install_0_0_1_alpha($dummy_data, $forced)
  {
    
    //Queue self-deployment with CMS component.
    $this->queue(array(
      'component' => 'cms',
      'min_version' => '0.4.1-beta'
      ), function($version){
        
        mk('Component')->helpers('cms')->_call('ensure_pagetypes', array(
          array(
            'name' => 'payment',
            'title' => 'Payment'
          ),
          array(
            'manager' => 'MANAGER'
          )
        ));
        
      }
    ); //END - Queue CMS
    
  }
  
}
<?php namespace components\payment; if(!defined('TX')) die('No direct access.');

//Make sure we have the things we need for this class.
mk('Component')->check('update');
mk('Component')->load('update', 'classes\\BaseDBUpdates', false);

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
      
      '0.0.8-alpha' => '0.1.0-beta', //No DB updates.
      '0.1.0-beta' => '0.1.1-beta' //No DB updates.
      
    );
  
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
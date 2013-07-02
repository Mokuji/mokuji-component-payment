<?php namespace components\payment; if(!defined('TX')) die('No direct access.');

//Make sure we have the things we need for this class.
tx('Component')->check('update');
tx('Component')->load('update', 'classes\\BaseDBUpdates', false);

class DBUpdates extends \components\update\classes\BaseDBUpdates
{
  
  protected
    $component = 'payment',
    $updates = array(
    );
  
  protected function install_0_0_1_alpha($dummy_data, $forced)
  {
    
    //Queue self-deployment with CMS component.
    $this->queue(array(
      'component' => 'cms',
      'min_version' => '0.4.1-beta'
      ), function($version){
        
        tx('Component')->helpers('cms')->_call('ensure_pagetypes', array(
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
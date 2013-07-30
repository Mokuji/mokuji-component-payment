<?php namespace components\payment; if(!defined('TX')) die('No direct access.'); ?>

<p class="settings-description"><?php __($names->component, 'SETTINGS_VIEW_DESCRIPTION'); ?></p>

<form id="payment_configuration_form" class="form edit-payment-configuration-form" action="<?php echo url('?rest=cms/settings',1) ?>" method="put">
  
  <div class="ctrlHolder">
    <label>iDeal handler</label>
    <select class="ideal-handler" name="mokuji_payment_ideal_handler[default]">
      <option value="-1"<?php $data->ideal->handler->eq('-1', function(){ echo ' selected="selected"'; }); ?>><?php __($names->component, 'Disabled'); ?></option>
      <option value="1"<?php $data->ideal->handler->eq('1', function(){ echo ' selected="selected"'; }); ?>>Rabobank OmniKassa</option>
    </select>
  </div>
  
  <div class="ctrlHolder ideal-handler" data-ideal="1">
    <h2>Rabobank OmniKassa</h2>
    
    <?php $omnikassa = $data->ideal->rabobank->omnikassa; ?>
    
    <label><?php __($names->component, 'Merchant ID'); ?></label>
    <input type="text" name="mokuji_payment_ideal_rabobank_omnikassa_merchant_id[default]" value="<?php echo $omnikassa->merchant_id; ?>" />
    
    <label><?php __($names->component, 'Merchant Sub ID'); ?></label>
    <input type="text" name="mokuji_payment_ideal_rabobank_omnikassa_merchant_sub_id[default]" value="<?php echo $omnikassa->merchant_sub_id; ?>" />
    
    <label><?php __($names->component, 'Security key'); ?></label>
    <input type="text" name="mokuji_payment_ideal_rabobank_omnikassa_security_key[default]" value="<?php echo $omnikassa->security_key; ?>" />
    
    <label><?php __($names->component, 'Security key version'); ?></label>
    <input type="text" name="mokuji_payment_ideal_rabobank_omnikassa_security_key_version[default]" value="<?php echo $omnikassa->security_key_version; ?>" />
    
    <label>
      <input type="checkbox" name="mokuji_payment_ideal_rabobank_omnikassa_test_mode[default]" value="1"<?php $omnikassa->test_mode->is(true, function(){ echo ' checked="checked"'; }); ?> />
      <?php __($names->component, 'Test mode'); ?>
    </label>
    
  </div>
  
  <div class="buttonHolder">
    <input type="submit" value="<?php __('Save'); ?>" class="primaryAction button black">
  </div>
  
</form>

<script type="text/javascript">
$(function(){
  
  $('#payment_configuration_form').restForm();
  
  $('#payment_configuration_form').on('change', 'select.ideal-handler', function(e){
    
    var handler = $(e.target).val();
    $('.ctrlHolder.ideal-handler').hide();
    $('.ctrlHolder[data-ideal="'+handler+'"]').show();
    
  });
  
  $('#payment_configuration_form select.ideal-handler').trigger('change');
  
});
</script>

<?php

mk('Component')->load('payment', 'methods\\ideal\\BaseHandler', false);
$tx_model = mk('Sql')->model('payment', 'Transactions');

$handler = methods\ideal\BaseHandler::get_handler();
$tx = $tx_model::create_transaction(5);

echo $handler->transaction_start_button($tx, 'http://localhost/mokuji/admin/index.php?view=cms/settings');

?>
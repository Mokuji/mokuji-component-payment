<?php namespace components\payment; if(!defined('MK')) die('No direct access.'); ?>

<p class="settings-description"><?php __($names->component, 'SETTINGS_VIEW_DESCRIPTION'); ?></p>

<div id="payment-accounts-container">Laden...</div>

<script type="text/javascript">
(function() {
  
  var loadScript = function(url){
    var script_el = document.createElement('script');
    script_el.type = 'text/javascript';
    script_el.async = true;
    script_el.src = url;
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(script_el, s);
  };
  
  loadScript("<?php echo URL_PLUGINS.'ejs/src/ejs.js'; ?>");
  loadScript("<?php echo $paths->component.'includes/js/account-settings.js'; ?>");
  
})();
</script>

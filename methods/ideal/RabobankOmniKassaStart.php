<?php

//Let Mokuji know who we are.
define('WHOAMI', 'Payment::RabobankOmniKassaStart');

//Override paths.
$url_path = str_replace('/mokuji/components/payment/methods/ideal/RabobankOmniKassaStart.php', '', $_SERVER['PHP_SELF']);
if(isset($url_path[0]) && $url_path[0] === '/'){ //Not an array, but first string character.
  $url_path = substr($url_path, 1);
}
$root = str_replace($_SERVER['PHP_SELF'], '', $_SERVER['SCRIPT_FILENAME']).'/'.$url_path.'/';

//Load init files.
require_once($root.'mokuji/system/dependencies/init/Initializer.php');
use \dependencies\init\Initializer;
use \dependencies\init\Environments;

//Init minimal environment.
$init = Initializer::get_instance()
  ->enable_debugging(true)
  ->set_root($root)
  ->set_url_path($url_path)
  ->set_environment(Environments::MINIMAL)
  ->run_environment();

//Get the handler.
use \components\payment\methods\ideal\IdealBaseHandler;

//Get transaction.
$tx = mk('Sql')->table('payment', 'Transactions')
  ->where('transaction_reference', mk('Sql')->escape(mk('Data')->get->tx))
  ->execute_single();

$tx_account = ($tx->account instanceof \components\payment\models\Accounts ? $tx->account : $tx->account->get());
$handler = IdealBaseHandler::get_handler($tx_account);

//Show the ridiculous supposed-to-be-hidden-form that the Rabobank requires to start the transaction.
try{
  $form = $handler->generate_to_rabobank_form(mk('Data')->get->as_array());
}catch(\Exception $ex){
  mk('Logging')->log('Payment', 'RabobankOmniKassaStart', 'Failed to start: '.$ex->getMessage());
  mk('Logging')->log('Payment', 'RabobankOmniKassaStart', mk('Data')->get->dump());
  die($ex->getMessage());
}

?>

<html>
<head>
  <title>Doorsturen naar betaalpagina...</title>
</head>
<body>
  
  Doorsturen naar betaalpagina...
  <?php echo $form; ?>
  
</body>
</html>

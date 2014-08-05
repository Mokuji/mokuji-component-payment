<?php

//Let Mokuji know who we are.
define('WHOAMI', 'Payment::RabobankOmniKassaReport');

//Override paths.
$url_path = str_replace('/mokuji/components/payment/methods/ideal/RabobankOmniKassaReport.php', '', $_SERVER['PHP_SELF']);
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

//Get transaction.
use \components\payment\methods\ideal\RabobankOmniKassaHandler;
$tx = RabobankOmniKassaHandler::tx_from_callback(mk('Data')->post->as_array());

//Get the handler.
use \components\payment\methods\ideal\IdealBaseHandler;
$handler = IdealBaseHandler::get_handler(data_of($tx->account));

//Process data.
$handler->transaction_callback(mk('Data')->post->as_array());

//Don't output. Just 200.
exit;
<?php

//Let Mokuji know who we are.
define('WHOAMI', 'Payment::RabobankOmniKassaReturn');

//Override paths.
$url_path = str_replace('/mokuji/components/payment/methods/ideal/RabobankOmniKassaReturn.php', '', $_SERVER['PHP_SELF']);
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
mk('Component')->load('payment', 'methods\\ideal\\IdealBaseHandler', false);
use \components\payment\methods\ideal\IdealBaseHandler;
$handler = IdealBaseHandler::get_handler(IdealBaseHandler::TYPE_RABOBANK_OMNIKASSA);

//Process data.
$tx = $handler->transaction_callback(mk('Data')->post->as_array());
if($tx === false) exit;

//Move to target URL.
header('Location: '. mk('Data')->session->payment->tx_return_urls->{$tx->transaction_reference->get()}->get());
mk('Data')->session->payment->tx_return_urls->{$tx->transaction_reference->get()}->un_set();

//Don't output. Just 302.
exit;
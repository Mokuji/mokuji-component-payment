<?php namespace components\payment; if(!defined('TX')) die('No direct access.');

class Helpers extends \dependencies\BaseComponent
{
  
  public function parse_query($string)
  {
    $parts = explode('&', $string);
    $data = array();
    foreach ($parts as $part) {
      $kv_pair = explode('=', $part);
      $data[$kv_pair[0]] = array_key_exists(1, $kv_pair) ? urldecode($kv_pair[1]) : null;
    }
    return $data;
  }
  
}

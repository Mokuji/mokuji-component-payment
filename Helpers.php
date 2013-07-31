<?php namespace components\payment; if(!defined('TX')) die('No direct access.');

class Helpers extends \dependencies\BaseComponent
{
  
  public function curl($method, $url, $post_data='')
  {
    
    if(is_string($post_data)){
      $POST = $post_data;
    }
    
    elseif(is_array($post_data)){
      $POST = '';
      $first = true;
      foreach ($post_data as $key => $value){
        $POST .= ($first ? '' : '&').urlencode($key).'='.urlencode($value);
        $first = false;
      }
    }
    
    $handle = curl_init();
    curl_setopt_array($handle, array(
      CURLOPT_URL => $url,
      CURLOPT_CUSTOMREQUEST => strtoupper($method),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_POSTFIELDS => $POST
    ));
    
    $response = curl_exec($handle);
    
    curl_close($handle);
    return $response;
    
  }
  
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

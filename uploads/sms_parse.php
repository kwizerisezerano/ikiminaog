<?php
/*
*	HDEV SMS Gateway 
*	@email :  info@hdevtech.cloud
*	@link : https://sms-api.hdev.rw
*
*/

/*
	Master SMS controller
*/
if (!defined('hdev_sms')) {
  class hdev_sms
  {
    private static $api_id = null;
    private static $api_key = null;
    public static function  api_key($value='')
    {
      self::$api_key = $value;
    }
    public static function api_id($value='')
    {
      self::$api_id = $value;
    }
    public static function send($sender_id,$tel,$message,$link=''){
      $curl = curl_init();

      curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://sms-api.hdev.rw/v1/api/'.self::$api_id.'/'.self::$api_key,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('ref'=>'sms','sender_id' => $sender_id,'tel' => $tel,'message' => $message,'link'=>$link),
      ));

      $response = curl_exec($curl);

      curl_close($curl);
      return json_decode($response);
    }
    public static function topup($tel,$amount,$transaction_ref="",$link=''){
      $transaction_ref = "HDEVSMS-".time().rand(100000,999999);
      $curl = curl_init();

      curl_setopt_array($curl, array(
         CURLOPT_URL => 'https://sms-api.hdev.rw/v1/api/'.self::$api_id.'/'.self::$api_key,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('ref'=>'pay','tel' => $tel,'tx_ref' => $transaction_ref,'amount' => $amount,'link'=>$link),
      ));

      $response = curl_exec($curl);

      curl_close($curl);
      return json_decode($response);
    }
    public static function get_topup($tx_ref='')
    {
      $curl = curl_init();

      curl_setopt_array($curl, array(
         CURLOPT_URL => 'https://sms-api.hdev.rw/v1/api/'.self::$api_id.'/'.self::$api_key,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('ref'=>'read','tx_ref' => $tx_ref),
      ));

      $response = curl_exec($curl);

      curl_close($curl);
      return json_decode($response);
    }
  }
  
// hdev_sms::api_id("HDEV-d00e779c-0336-4743-aaa4-9c5091c530e9-ID");
// hdev_sms::api_key("HDEV-d4e1bbec-017d-495e-80e9-a8cf4d615ee9-KEY");



hdev_sms::api_id("HDEV-4d5eaa39-b056-4be1-af82-aa067ec5d914-ID");
hdev_sms::api_key("HDEV-9b832336-d5fd-4823-8db3-8f2a0407d7b7-KEY");

}
?>
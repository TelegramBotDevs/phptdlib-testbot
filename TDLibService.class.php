<?php
declare(strict_types=1);

use TDLib\JsonClient;
Error_Reporting(E_ALL);
ini_set('display_errors', '1');

class TDLibService
{
    public const AUTH_STATE_UNKNOWN = 'authorizationStateUnknown';
    public const AUTH_STATE_READY = 'authorizationStateReady';
    public const AUTH_STATE_WAITING_PARAMS = 'authorizationStateWaitTdlibParameters';
    public const AUTH_STATE_WAIT_PHONE_NUMBER = 'authorizationStateWaitPhoneNumber'; //If state is this  checkAuthenticationBotToken
    public const AUTH_STATE_WAIT_2FA = 'authorizationStateWaitPassword';
    public const AUTH_STATE_WAIT_CODE = 'authorizationStateWaitCode';
    public const AUTH_STATE_CLOSED = 'authorizationStateClosed';
    public const AUTH_STATE_CLOSING = 'authorizationStateClosing';
    public const AUTH_STATE_LOGGINGOUT = 'authorizationStateLoggingOut';
    public const AUTH_STATE_WAIT_ENC_KEY = 'authorizationStateWaitEncryptionKey';
    
    /**
     * @var JsonClient $clients
     */
    private $client;
    /**
     * @var string $authorizationState
     */
    private $authorizationState = TDLibService::AUTH_STATE_UNKNOWN;
    private $is_bot=false;
    private $debug=false;
    public $me;
    
    public function __construct(array $parameters, string $encryption_key = null,$cliParams)
    {
        $this->client = new JsonClient();
        if($cliParams->is_bot){
          $this->setAsBot();
        }
        $this->getAuthorizationState();
        $this->setTdlibParameters($parameters);
        $this->getAuthorizationState();
        $this->setDatabaseEncryptionKey($encryption_key);
        $this->cliLogIn();
        $this->me = $this->getMe();
    }
    
    public function setAsBot(){
      $this->is_bot=true;
    }
    public function setDebug($status=null){
      $this->debug=(($status??(!$this->debug))?true:false);
    }
    private function d($str){
      if($this->debug){
        echo $str;
      }
    }
    
    public function query(array $queryArray, ?int $timeout = null): ?\stdClass
    {
        $responseObject = null;
        try {
            $responseJsonString = $this->client->query(json_encode($queryArray), $timeout ?? 1);
            $responseObject = json_decode($responseJsonString);
            //var_dump($responseObject);
            //$this->checkAuthorizationState($responseObject);
            $this->d( "\e[0m\e[0;35m" . ">> " . "\e[1m".$queryArray["@type"].":\e[0;35m"." $responseJsonString"."\e[0m\n"); 
        } catch (\Exception $e) {
            var_dump(__CLASS__ . '::' . __METHOD__, $e);
        }
        return $responseObject;
    }
    
    public function barequery(string $queryString, ?int $timeout = null): ?string
    {
        $responseJsonString = null;
        try {
            $responseJsonString = $this->client->query($queryString, $timeout ?? 1);
        } catch (\Exception $e) {
            var_dump(__CLASS__ . '::' . __METHOD__, $e);
            $responseJsonString = json_encode([
              '@type' => 'error',
              'code' => 500,
              'message' => 'CLIENT_QUERY_ERROR',
              'error' => $e
            ]);
            
        }
        return $responseJsonString;
    }
    
    public function receive(?int $timeout = null){
      $responseObject = null;
      try {
        
          $responseJsonString = $this->client->receive($timeout ?? 1);
          $responseObject = json_decode($responseJsonString);
          //var_dump($responseObject);
          //$this->checkAuthorizationState($responseObject);
          if($responseObject!==null)
            $this->d( "\e[0m\e[0;33m" . "<< " . "\e[1m".$responseObject->{"@type"}.":\e[0;33m"." $responseJsonString"."\e[0m\n");  
          
      } catch (\Exception $e) {
          var_dump(__CLASS__ . '::' . __METHOD__, $e);
      }
      return $responseObject;
    }
    
    /**
     * authorizationState sets in $this->checkReceivedResponses() 
     * if responses contains type updateAuthorizationState
     *
     * @return string $authorizationState
     */
    public function getAuthorizationState()
    {
        $queryArray = ['@type' => 'getAuthorizationState'];
        $object = $this->query($queryArray);
        if($object!==null)
          $this->authorizationState = $object->{"@type"};
        else
          $this->authorizationState = TDLibService::AUTH_STATE_UNKNOWN;
        return $object;
    }
    
    // public function checkAuthorizationState($response)
    // {
    //   $type = $response->{'@type'} ?? '';
    //   switch ($type){
    //       case 'updateAuthorizationState':
    //           $this->authorizationState = $response->{'authorization_state'}->{'@type'};
    //       break;
    //   }
    // }
    
    public function setTdlibParameters(array $parameters = [])
    {
        $queryArray = [
            '@type' => 'setTdlibParameters',
            'parameters' => $parameters
        ];
        return $this->query($queryArray);
    }
    
    public function setDatabaseEncryptionKey(?string $newEncryptionKey = null)
    {
        $queryArray = [
            '@type' => 'setDatabaseEncryptionKey'
        ];
        if (!empty($newEncryptionKey)) {
            $queryArray['new_encryption_key'] = $newEncryptionKey;
        }
        return $this->query($queryArray);
    }
    
    public function setAuthenticationPhoneNumber(string $phoneNumber, bool $allowFlashCall = false, bool $isCurrentPhoneNumber = false)
    {
        $queryArray = [
            '@type' => 'setAuthenticationPhoneNumber',
            'phone_number' => $phoneNumber,
            'allow_flash_call' => $allowFlashCall,
            'is_current_phone_number' => $isCurrentPhoneNumber
        ];
        return $this->query($queryArray, 3);
        
    }
    
    
    
    public function checkAuthenticationBotToken(string $token){
      $this->is_bot = true;
      $queryArray = [
            '@type' => 'checkAuthenticationBotToken',
            'token' => $token
        ];
        return $this->query($queryArray);
    }
    
    public function checkAuthenticationCode(string $code)
    {
        $queryArray = [
            '@type' => 'checkAuthenticationCode',
            'code' => $code
        ];
        return $this->query($queryArray);
    }
    
    public function checkAuthenticationPassword(string $password){
      $queryArray = [
          '@type' => 'checkAuthenticationPassword',
          'password' => $password
      ];
      return $this->query($queryArray);
    }
    
    
    public function logOut()
    {
        $queryArray = [
            '@type' => 'logOut'
        ];
        return $this->query($queryArray);
    }
    
    public function getMe()
    {
        $queryArray = [
            '@type' => 'getMe'
        ];
        return $this->query($queryArray);
    }
    
    public function __call(string $name,$arguments = null){
      $queryArray = [
            '@type' => $name
      ];
      if(isset($arguments[0]) && is_array($arguments[0])){
        $queryArray = $queryArray+$arguments[0];
      }
      return $this->query($queryArray);
    }
    
    public function cliLogIn(){
      $tries=0;
      $max_tries = 3;
      $status = $this->getAuthorizationState();
      while($status->{"@type"} !== TDLibService::AUTH_STATE_READY){
        
        
        
        if(in_array($status->{"@type"},[TDLibService::AUTH_STATE_WAIT_PHONE_NUMBER,TDLibService::AUTH_STATE_WAIT_CODE,TDLibService::AUTH_STATE_WAIT_2FA])){
          
          if($status->{"@type"}==TDLibService::AUTH_STATE_WAIT_PHONE_NUMBER){
            echo "Do you want to login as User [u] or as a Bot [b]? [u]";
            $stdin = trim(fgets(STDIN));
          }elseif(in_array($status->{"@type"},[TDLibService::AUTH_STATE_WAIT_CODE,TDLibService::AUTH_STATE_WAIT_2FA])){
            $stdin = "u";
          }
          
          
          
          if($stdin == "b"){
            
            echo "Write your bot Token: ";
            $token = trim(fgets(STDIN));
            $response = $this->checkAuthenticationBotToken($token);
            var_dump($response);
            
          }else{
            if($status->{"@type"}==TDLibService::AUTH_STATE_WAIT_PHONE_NUMBER){
              echo "Write your Phone number [+11223344556]: ";
              $phone_number = trim(fgets(STDIN));
              $response = $this->setAuthenticationPhoneNumber($phone_number);
              var_dump($response);
              $status = $this->getAuthorizationState();
            }
            
            if($status->{"@type"}==TDLibService::AUTH_STATE_WAIT_CODE){
              while($status->{"@type"} == TDLibService::AUTH_STATE_WAIT_CODE){
                echo "Write the code you recived: ";
                $auth_code = trim(fgets(STDIN));
                
                if( !$status->is_registered ){
                  echo "Write your name: ";
                  $name = trim(fgets(STDIN));
                  $response = $this->checkAuthenticationCode($auth_code,$name,"");
                }else{
                  $response = $this->checkAuthenticationCode($auth_code);
                }
                var_dump($response);
                $status = $this->getAuthorizationState();
              }
            }
            
            if($status->{"@type"}==TDLibService::AUTH_STATE_WAIT_2FA){
              while( $status->{"@type"} == TDLibService::AUTH_STATE_WAIT_2FA ){
                $hint = $status->password_hint;
                echo "Write the your password, Hint[$hint] : "."\e[0m\e[0;30m\e[40m";
                $auth2fa = trim(fgets(STDIN));
                echo "\e[0m\n";
                $response = $this->checkAuthenticationPassword($auth2fa);
                var_dump($response);
                $status = $this->getAuthorizationState();
              }
            }
          }
        }else{
          echo "Unknown Status: $this->authorizationState \n";
        }
        
        
        if($tries++>$max_tries){
          echo "too many tries\n";
          exit(1); 
        }
      }
    }
    
    public function strlen($str){
      return TDLibUtils::strlen($str);
    }
    public function parseText($text){
      return $this->parseTextEntities([
        'text'=> $text,
        'parse_mode' => ['@type'=>'textParseModeHTML']
      ]);
    }
    
    public function sendMsg($chat_id,$text,$parse=false,$keyboard_rows=false,$replyto=false){
      
      $queryArray = [
            '@type' => "sendMessage",
            'chat_id' => $chat_id,
            
            'input_message_content' =>[
              '@type' => 'inputMessageText',
              'text' => [
                 '@type' => 'formattedText',
                 'text' => $text,
                 'entities' => []
               ]
            ]
      ];
      
      if($replyto){
        $queryArray['reply_to_message_id'] = $replyto;
      }
      
      if($parse){
        $queryArray['input_message_content']['text'] = $this->parseTextEntities([
          'text'=> $text,
          'parse_mode' => ['@type'=>'textParseModeHTML']
        ]);
      }
      
      if($keyboard_rows){
        $keyboard = TDLibKeyboard::parseInline($keyboard_rows);
        var_export($keyboard);
        // Keyboard_rows =[[{"text":"I understand the rules","url":"https://telegram.me/geeksTelegramBot?start=confirm"}]];
        if($keyboard){
          $queryArray['reply_markup'] = $keyboard;
        }
      }
      //echo json_encode($queryArray);
      return $this->query($queryArray);
    }
}

class TDLibKeyboard{
  
  public static function parseInline($rows){
    if(is_array($rows[0][0])){
      $rows = json_decode(json_encode($rows));
    }elseif(is_string($rows)){
      $rows = json_decode($rows);
    }elseif(is_object($rows[0][0])){
      
    }else{
      return false;
    }
    var_export($rows);
    if(!isset($rows[0][0]->text)) return false;
    
    $keyboard['@type'] = 'replyMarkupInlineKeyboard';
    foreach($rows as $row_num => $row){
      foreach($row as $button_num => $button){
        $keyboard["rows"][$row_num][$button_num]["@type"]="inlineKeyboardButton";
        $keyboard["rows"][$row_num][$button_num]["text"] = $button->text;
        if(!empty($button->url)){
          $keyboard["rows"][$row_num][$button_num]["type"]["@type"] = "inlineKeyboardButtonTypeUrl";
          $keyboard["rows"][$row_num][$button_num]["type"]["url"] = $button->url;
        }elseif(!empty($button->callback_data)){
          $keyboard["rows"][$row_num][$button_num]["type"]["@type"] = "inlineKeyboardButtonTypeCallback";
          $keyboard["rows"][$row_num][$button_num]["type"]["data"] = base64_encode($button->callback_data);
        }elseif(!empty($button->data)){
          $keyboard["rows"][$row_num][$button_num]["type"]["@type"] = "inlineKeyboardButtonTypeCallback";
          $keyboard["rows"][$row_num][$button_num]["type"]["data"] = base64_encode($button->data);
        }elseif(!empty($button->switch_inline_query)){
          $keyboard["rows"][$row_num][$button_num]["type"]["@type"] = "inlineKeyboardButtonTypeSwitchInline";
          $keyboard["rows"][$row_num][$button_num]["type"]["query"] = $button->switch_inline_query;
          $keyboard["rows"][$row_num][$button_num]["type"]["in_current_chat"] = $button->switch_inline_query_current_chat??false;
        }
        //TODO: More types
      }
      $keyboard["rows"][$row_num] = array_values($keyboard["rows"][$row_num]);
    }
    if(!isset($keyboard["rows"])) return false;
    return $keyboard;
  }
}

class TDLibUtils{
  public static function strlen($str){
    return strlen(iconv('utf-8', 'utf-16le', $str)) / 2;
  }
  public static function parseInlineKeyboard($rows){
    return TDLibKeyboard::parseInline($rows);
  }
}

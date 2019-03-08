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
    
    public function authorizationStateWaitPassword(string $password){
      
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
      $this->getAuthorizationState();
      while($this->authorizationState !== TDLibService::AUTH_STATE_READY){
        echo "Do you want to login as User [u] or as a Bot [b]? [u]";
        $stdin = trim(fgets(STDIN));
        
        if( $this->authorizationState == TDLibService::AUTH_STATE_WAIT_PHONE_NUMBER ){
          if($stdin == "b"){
            
            echo "Write your bot Token: ";
            $token = trim(fgets(STDIN));
            $this->checkAuthenticationBotToken($token);
            
          }else{
            echo "Write your Phone number [+11223344556]: ";
            $phone_number = trim(fgets(STDIN));
            $response = $this->setAuthenticationPhoneNumber($phone_number);
            //var_dump($response);
            
            while($this->authorizationState == TDLibService::AUTH_STATE_WAIT_CODE){
              echo "Write the code you recived: ";
              $auth_code = trim(fgets(STDIN));
              
              if( !$response->is_registered ){
                echo "Write your name: ";
                $name = trim(fgets(STDIN));
                $response = $this->checkAuthenticationCode($auth_code,$name,"");
              }else{
                $response = $this->checkAuthenticationCode($auth_code);
              }
              //var_dump($response);
              
              while( $this->authorizationState == TDLibService::AUTH_STATE_WAIT_2FA ){
                $hint = $response->password_hint;
                echo "Write the your password, Hint[$hint] : ";
                $auth2fa = trim(fgets(STDIN));
                $response = $this->checkAuthenticationCode($auth2fa);
                //var_dump($response);
              }
            }
          }
        }
        if($tries++>$max_tries){
          echo "too many tries\n";
          exit(1); 
        }
      }
    }
    
    public function strlen($str){
      return strlen(iconv('utf-8', 'utf-16le', $str)) / 2;
    }
}
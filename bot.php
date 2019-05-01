#!/usr/bin/env php
<?php
Error_Reporting(E_ALL);
ini_set('display_errors', 1);

require_once("TDLibService.class.php");
$config = json_decode(file_get_contents('/app/config.json'));

try {
  \TDApi\LogConfiguration::setLogVerbosityLevel(\TDApi\LogConfiguration::LVL_ERROR);
  $client = new \TDLibService((array)$config->tdlibparams,$config->password,$config->cliParams);
} catch (\Exception $e) {
  echo "Exception: ".$e->getCode().PHP_EOL;
  echo "Message: ".$e->getMessage().PHP_EOL;
  echo "Line: ".$e->getLine().PHP_EOL.PHP_EOL;
  echo $e->getTraceAsString().PHP_EOL;
}

//var_dump($result);
$quit=false;
while(!$quit){
  try {
    $update = $client->receive(60);
  } catch (\Exception $e) {
    echo "Exception: ".$e->getCode().PHP_EOL;
    echo "Message: ".$e->getMessage().PHP_EOL;
    echo "Line: ".$e->getLine().PHP_EOL.PHP_EOL;
    echo $e->getTraceAsString().PHP_EOL;
  }
  if($update==null) continue; 
  
  if($update->{"@type"}=="updateNewMessage"){
    $M = &$update->message;
    
    if($M->is_outgoing==false && $M->content->{"@type"}=="messageText"){
      if($M->content->text->text=="/ping"){
        
        try {
          //https://core.telegram.org/tdlib/docs/classtd_1_1td__api_1_1send_message.html
          $client->sendMessage([
             "chat_id" => $M->sender_user_id                          // std::int64_t
            ,"reply_to_message_id" => $M->id                          // std::int64_t
            //,"disable_notification" => true                         // bool
            //,"from_background" => true                              // bool
            //,"reply_markup" =>                                      // object_ptr< ReplyMarkup >
            ,"input_message_content" => [                             // object_ptr< InputMessageContent >
                "@type" => "inputMessageText",
                "text" =>  $client->parseTextEntities([  
                   "text"=> "<b>Pong!!</b>",
                   "parse_mode" => ["@type"=>"textParseModeHTML"]
                ])
            ]
          ]);
        } catch (\Exception $e) {
          echo "Exception: ".$e->getCode().PHP_EOL;
          echo "Message: ".$e->getMessage().PHP_EOL;
          echo "Line: ".$e->getLine().PHP_EOL.PHP_EOL;
          echo $e->getTraceAsString().PHP_EOL;
        }
        
      }
    }
  }
}


<?php
//1. Receive POST from AT
  $isActive  = $_POST['isActive'];
  $callerNumber =$_POST['callerNumber'];
  $dtmfDigits = $_POST['dtmfDigits'];
  $sessionId = $_POST['sessionId'];

  //2. Check if isActive=1 to act on the call or isActive=='0' to store the result
  if($isActive=='1'){
    //2a. Switch through the DTMFDigits
    switch ($dtmfDigits) {
      case "0":
          //2b. Compose response - talk to sales...
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Say>Please hold while we connect you to Sales.</Say>';     
        $response .= '<Dial phoneNumbers="880.welovenerds@ke.sip.africastalking.com" ringbackTone="http://62.12.117.25:8010/media/SautiFinaleMoney.mp3"/>';
        $response .= '</Response>';
         
        // Print the response onto the page so that our gateway can read it
        header('Content-type: text/plain');
        echo $response;
          break;
      case "1":
          //2c. Compose response - talk to support...
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Say>Please hold while we connect you to Support.</Say>';     
        $response .= '<Dial phoneNumbers="880.welovenerds@ke.sip.africastalking.com" ringbackTone="http://62.12.117.25:8010/media/SautiFinaleMoney.mp3"/>';
        $response .= '</Response>';
         
        // Print the response onto the page so that our gateway can read it
        header('Content-type: text/plain');
        echo $response;
          break;
      case "2":
          //2d. Redirect to the main IVR...
        $response  = '<?xml version="1.0" encoding="UTF-8"?>';
        $response .= '<Response>';
        $response .= '<Redirect>http://62.12.117.25/MF-Ussd-Live/voiceCall.php</Redirect>';
        $response .= '</Response>';

        // Print the response onto the page so that our gateway can read it
        header('Content-type: text/plain');
        echo $response;     
          break;
      default:
        //2e. By default talk to support
      $response  = '<?xml version="1.0" encoding="UTF-8"?>';
      $response .= '<Response>';
      $response .= '<Say>Please hold while we connect you to Support.</Say>';     
      $response .= '<Dial phoneNumbers="880.welovenerds@ke.sip.africastalking.com" ringbackTone="http://62.12.117.25:8010/media/SautiFinaleMoney.mp3"/>';
      $response .= '</Response>';
       
      // Print the response onto the page so that our gateway can read it
      header('Content-type: text/plain');
      echo $response;    
    }

  }else{
    //3. Store the data from the POST
      $durationInSeconds=$_POST['durationInSeconds'];
      $direction=$_POST['direction'];
      $amount=$_POST['amount'];
      $callerNumber=$_POST['callerNumber'];
      $destinationNumber=$_POST['destinationNumber'];
      $sessionId=$_POST['sessionId'];
      $callStartTime=$_POST['callStartTime'];
      $isActive=$_POST['isActive'];
      $currencyCode=$_POST['currencyCode'];
    $status=$_POST['status'];

    //3a. Store the data, write your SQL statements here...
  }

?>

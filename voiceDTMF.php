<?php
// This is a unique ID generated for this call
$sessionId = $_POST['sessionId'];
$isActive  = $_POST['isActive'];

if ($isActive == 1)  {
  $callerNumber = $_POST['callerNumber'];

  // Compose the response
  $response  = '<?xml version="1.0" encoding="UTF-8"?>';
  $response .= '<Response>';
  $response .= '<GetDigits timeout="30" finishOnKey="#" callbackUrl="https://b11cd817.ngrok.io/MfUSSD/voiceMenu.php">';
  $response .= '<Say>"Please enter your pin to log into Nerd Microfinance."</Say>';
  $response .= '</GetDigits>';
  $response .= '<Say>"Thank you for calling. Good bye!"</Say>';
  $response .= '</Response>';
   
  // Print the response onto the page so that our gateway can read it
  header('Content-type: text/plain');
  echo $response;

} else {
  
  // Read in call details (duration, cost). This flag is set once the call is completed.
  // Note that the gateway does not expect a response in thie case
  
  $duration     = $_POST['durationInSeconds'];
  $currencyCode = $_POST['currencyCode'];
  $amount       = $_POST['amount'];
  
  // You can then store this information in the database for your records

}

?>
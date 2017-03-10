<?php
//1. Receive Voice Call POST from AT
  $isActive  = $_POST['isActive'];
  $callerNumber =$_POST['callerNumber'];
  $dtmfDigits = $_POST['dtmfDigits'];
  $sessionId = $_POST['sessionId'];

  //2. Check if isActive=1 to act on the call or isActive=='0' to store the result
  if($isActive=='1'){
    //2a. Get the DTMFDigits. 
    if($_POST['dtmfDigits']){
      //2b. Check for validation the ussd.microfinance
        $sql2a = "SELECT * FROM microfinance WHERE phoneNumber LIKE '%".$phoneNumber."%' LIMIT 1";
        $userQuery=$db->query($sql2a);
        $userAvailable=$userQuery->fetch_assoc();
      //2c. If valid edit user
        if($dtmfDigits == $userAvailable['validation']){
            //2d. Edit the user
            if($userResponse==""){
              //2e. On receiving a Blank. Advise user to input correctly based on level
              switch ($level) {
                  case 0:
                    //2f. Graduate the user to the next level, so you dont serve them the same menu
                     $sql2f = "INSERT INTO `session_levels`(`session_id`, `phoneNumber`,`level`) VALUES('".$sessionId."','".$phoneNumber."', 1)";
                     $db->query($sql2f);

                     //10d. Serve the menu request for name
                     $response = "CON Please enter your new name";

                    // Print the response onto the page so that our gateway can read it
                    header('Content-type: text/plain');
                    echo $response; 
                      break;

                  case 1:
                    //10e. Request again for name
                      $response = "CON Name not supposed to be empty. Please enter your name \n";

                    // Print the response onto the page so that our gateway can read it
                    header('Content-type: text/plain');
                    echo $response; 
                      break;

                  case 2:
                    //10f. Request for city again
                  $response = "CON City not supposed to be empty. Please reply with your city \n";

                    // Print the response onto the page so that our gateway can read it
                    header('Content-type: text/plain');
                    echo $response; 
                      break;

                  case 3:
                    //10f. Request for PIN again
                  $response = "CON Your PIN is not supposed to be empty. Please reply with your new or old PIN. \n";

                    // Print the response onto the page so that our gateway can read it
                    header('Content-type: text/plain');
                    echo $response; 
                      break;

                  default:
                    //10g. Serve default and end
                  $response = "END Apologies, something went wrong... \n";

                    // Print the response onto the page so that our gateway can read it
                    header('Content-type: text/plain');
                    echo $response; 
                      break;
              }
            }else{
              //3. Update microfinance table based on input to correct level
              switch ($level) {
                  case 0:
                     //3a. Serve the menu request for name
                     $response = "END This level should not be seen...";

                    // Print the response onto the page so that our gateway can read it
                    header('Content-type: text/plain');
                    echo $response; 
                      break;

                  case 1:
                    //3b. Update Name, Request for city
                      $sql3b = "UPDATE `microfinance` SET `name`='".$userResponse."' WHERE `phonenumber` LIKE '%". $phoneNumber ."%'";
                      $db->query($sql3b);

                      //3c. We graduate the user to the city level
                      $sql3c = "UPDATE `session_levels` SET `level`=2 WHERE `session_id`='".$sessionId."'";
                      $db->query($sql3c);

                      //We request for the city
                      $response = "CON Please enter your new city";

                    // Print the response onto the page so that our gateway can read it
                    header('Content-type: text/plain');
                    echo $response; 
                      break;

                  case 2:
                    //3d. Update city, Request for validation
                      $sql3d = "UPDATE `microfinance` SET `city`='".$userResponse."' WHERE `phonenumber` = '". $phoneNumber ."'";
                      $db->query($sql3d);

                    //3e. We graduate the user to the validation level
                      $sql3e = "UPDATE `session_levels` SET `level`=3 WHERE `session_id`='".$sessionId."'";
                      $db->query($sql3e);  

                      //We request for the validation
                      $response = "CON Please enter your new or old PIN";          

                    // Print the response onto the page so that our gateway can read it
                    header('Content-type: text/plain');
                    echo $response; 
                      break;

                  case 3:
                    //3f. Update validation
                      $sql3f = "UPDATE `microfinance` SET `validation`='".$userResponse."' WHERE `phonenumber` = '". $phoneNumber ."'";
                      $db->query($sql3f);

                    //3g. Change level back to 1 and serve menu at level 0
                      $sql3g = "UPDATE `session_levels` SET `level`=1 WHERE `session_id`='".$sessionId."'";
                      $db->query($sql3g); 

                    //3h. Call db for user details
                      $sql3h = "SELECT * FROM microfinance WHERE phoneNumber LIKE '%".$phoneNumber."%' LIMIT 1";
                      $userQuery=$db->query($sql3h);
                      $userAvailable=$userQuery->fetch_assoc();                        

                    //3i. Serve services menu...
                      $response = "CON All set " . $userAvailable['username']  . "! Choose a service.\n";
                      $response .= " 1. Edit account.\n";
                      $response .= " 2. Please call me.\n";
                      $response .= " 3. Deposit\n";
                      $response .= " 4. Withdraw\n";
                      $response .= " 5. Send Money\n";    
                      $response .= " 6. Buy Airtime\n";                     

                    // Print the response onto the page so that our gateway can read it
                    header('Content-type: text/plain');
                    echo $response; 
                      break;                        

                  default:
                    //3g. Serve default menu
                  $response = "END Apologies, something went wrong... \n";

                    // Print the response onto the page so that our gateway can read it
                    header('Content-type: text/plain');
                    echo $response; 
                      break;
              }     
            }
        }
    }
  }else{
    //4. Store the data from the POST
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

    //4a. Store the data, write your SQL statements here...
  }

?>
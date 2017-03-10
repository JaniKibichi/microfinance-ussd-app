#Setting Up a USSD with Registration
####A step-by-step guide

- Setting up the logic for USSD is easy with the [Africa's Talking API](docs.africastalking.com/ussd). This is a guide to how to use the
code provided on this [repository]() to create a USSD that allows users to get registered and then access a menu of services.

##Prerequisites
- First, create a config.php file in your root directory and fill in your Africa's Talking API credentials as below.

```PHP
<?php
//This is the config.php file

// Specify your login credentials
$username   = "yourUsername";
$apikey     = "yourAPIKey";

?>
```
- You need to set up on the sandbox and [create](https://sandbox.africastalking.com/ussd/createchannel) a USSD channel that you will use to test by dialing into it via our [simulator](https://simulator.africastalking.com:1517/).

- Assuming that you are doing your development on a localhost, you have to expose your application living in the webroot of your localshost to the internet via a tunneling application like [Ngrok](https://ngrok.com/). Otherwise, if your server has a public IP, you are good to go! Your URL callback for this demo will become:
 http://<your ip address>/RegUSSD/RegistrationUSSD.php

- This application has been developed on an Ubuntu 16.04LTS and lives in the web root at /var/www/html/RegUSSD. Courtesy of Ngrok, the publicly accessible url is: https://b11cd817.ngrok.io (instead of http://localhost) which is referenced in the code as well. 
(Create your own which will be different.)

- The webhook or callback to this application therefore becomes: 
https://b11cd817.ngrok.io/RegUSSD/RegistrationUSSD.php. 
To allow the application to talk to the Africa's Talking USSD gateway, this callback URL is placed in the dashboard, [under ussd callbacks here](https://account.africastalking.com/ussd/callback).

- Finally, this application works with a connection to a MYSQL database. Create a database with a name, username and password of your choice. Also create a session_levels table and a users table. These details are configured in the dbConnector.php and this is required in the main application script RegistrationUSSD.php.

mysql> describe users;

| Field         | Type                         | Null  | Key | Default | Extra |
| ------------- |:----------------------------:| -----:|----:| -------:| -----:|
| username      | varchar(30)                  |   YES |     | NULL    |       |
| phonenumber   | varchar(20)                  |   YES |     | NULL    |       |
| city          | varchar(30)                  |   YES |     | NULL    |       |
| status        | enum('ACTIVE','SUSPENDED')   |   YES |     | NULL    |       |
4 rows in set (0.53 sec)

mysql> describe session_levels;

| Field         | Type                         | Null  | Key | Default | Extra |
| ------------- |:----------------------------:| -----:|----:| -------:| -----:|
| session_id    | varchar(50)                  |   YES |     | NULL    |       |
| phonenumber   | varchar(25)                  |   YES |     | NULL    |       |
| level         | tinyint(1)                   |   YES |     | NULL    |       |
3 rows in set (0.02 sec)


##Features on the Services List
This USSD application has the following user journey.

- The user dials the ussd code - something like `*384*303#`

- The application checks if the user is registered or not. If the user is registered, the services menu is served which allows the user to receive SMS, receive Airtime or receive a call with an IVR menu.

- In case the user is not registered, the application prompts the user for their name and city (with validations), before successfully serving the services menu.

##Code walkthrough
This documentation is for the USSD application that lives in https://b11cd817.ngrok.io/RegUSSD/RegistrationUSSD.php.

```PHP
<?php
//1. ensure this code runs only after a POST from AT
if(!empty($_POST)){
```
Require all the necessary scripts to run this application
```PHP
	require_once('dbConnector.php');
	require_once('AfricasTalkingGateway.php');
	require_once('config.php');
```	

Receive the HTTP POST from AT
```PHP
	//2. receive the POST from AT
	$sessionId=$_POST['sessionId'];
	$serviceCode=$_POST['serviceCode'];
	$phoneNumber=$_POST['phoneNumber'];
	$text=$_POST['text'];
```

The AT USSD gateway keeps chaining the user response. We want to grab the latest input from a string like 1*1*2
```PHP
	//3. Explode the text to get the value of the latest interaction - think 1*1
	$textArray=explode('*', $text);
	$userResponse=trim(end($textArray));
```

Interactions with the user can be managed using the received sessionId and a level management process that your application implements as follows.
The USSD session has a set time limit(20-180 secs based on provider) under which the sessionId does not change. Using this sessionId, it is easy to navigate your user across the USSD menus by graduating their level(menu step) so that you dont serve them the same menu or lose track of where the user is. 
..* Set the default level to 0 (or your own numbering scheme) -- the home menu.
..* Check the session_levels table for a user with the same phone number as that received in the HTTP POST. If this exists, the user is returning and they therefore have a stored level. Grab that level and serve that user the right menu. Otherwise, serve the user the home menu.
```PHP
	//4. Set the default level of the user
	$level=0;

	//5. Check the level of the user from the DB and retain default level if none is found for this session
	$sql = "select level from session_levels where session_id ='".$sessionId." '";
	$levelQuery = $db->query($sql);
	if($result = $levelQuery->fetch_assoc()) {
  		$level = $result['level'];
	}

	//6. Update level accordingly
	if($result){
		$level = $result['level'];
	}
```

Before serving the menu, check if the incoming phone number request belongs to a registered user(sort of a login). If they are registered, they can access the menu, otherwise, they should first register.
```PHP
	//7. Check if the user is in the db
	$sql7 = "SELECT * FROM users WHERE phoneNumber LIKE '%".$phoneNumber."%' LIMIT 1";
	$userQuery=$db->query($sql7);
	$userAvailable=$userQuery->fetch_assoc();

	//8. Check if the user is available (yes)->Serve the menu; (no)->Register the user
	if($userAvailable && $userAvailable['city']!=NULL && $userAvailable['username']!=NULL){
		//9. Serve the Services Menu
```

If the user is available and all their mandatory fields are complete, then the application switches between their responses to figure out which menu to serve. The first menu is usually a result of receiving a blank text -- the user just dialed in.
```PHP
//9a. Check that the user actually typed something, else demote level and start at home
switch ($userResponse) {
    case "":
        if($level==0){
        	//9b. Graduate user to next level & Serve Main Menu
        	$sql9b = "INSERT INTO `session_levels`(`session_id`,`phoneNumber`,`level`) VALUES('".$sessionId."','".$phoneNumber."',1)";
        	$db->query($sql9b);

        	//Serve our services menu
			$response = "CON Karibu " . $userAvail['username']  . ". Please choose a service.\n";
			$response .= " 1. Send me todays voice tip.\n";
			$response .= " 2. Please call me!\n";
			$response .= " 3. Send me Airtime!\n";

  			// Print the response onto the page so that our gateway can read it
  			header('Content-type: text/plain');
	  			echo $response;						
        }
        break;
    case "1":
        if($level==1){
        	//9c. Send the user todays voice tip via AT SMS API
        	$response = "END Please check your SMS inbox.\n";

			$code = '20880';
			$recipients = $phoneNumber;
			$message    = "https://hahahah12-grahamingokho.c9.io/kaka.mp3";
			$gateway    = new AfricasTalkingGateway($username, $apikey);
			try { $results = $gateway->sendMessage($recipients, $message, $code); }
			catch ( AfricasTalkingGatewayException $e ) {echo "Encountered an error while sending: ".$e->getMessage(); }

  			// Print the response onto the page so that our gateway can read it
  			header('Content-type: text/plain');
	  			echo $response;	            						        	
        }
        break;
    case "2":
        if($level==1){
        	//9d. Call the user and bridge to a sales person
          	$response = "END Please wait while we place your call.\n";

          	//Make a call
         	$from="+254711082300"; $to=$phoneNumber;
          	// Create a new instance of our awesome gateway class
          	$gateway = new AfricasTalkingGateway($username, $apikey);
          	try { $gateway->call($from, $to); }
          	catch ( AfricasTalkingGatewayException $e ){echo "Encountered an error when calling: ".$e->getMessage();}

  			// Print the response onto the page so that our gateway can read it
  			header('Content-type: text/plain');
	  			echo $response;	 
        }
        break;
    case "3":
    	if($level==1){
    		//9e. Send user airtime
			$response = "END Please wait while we load your account.\n";

			// Search DB and the Send Airtime
			$recipients = array( array("phoneNumber"=>"".$phoneNumber."", "amount"=>"KES 10") );
			//JSON encode
			$recipientStringFormat = json_encode($recipients);
			//Create an instance of our gateway class, pass your credentials
			$gateway = new AfricasTalkingGateway($username, $apiKey);    
			try { $results = $gateway->sendAirtime($recipientStringFormat);}
			catch(AfricasTalkingGatewayException $e){ echo $e->getMessage(); }

  			// Print the response onto the page so that our gateway can read it
  			header('Content-type: text/plain');
	  			echo $response;	 			    		
    	}
        break;			        
    default:
    	if($level==1){
	        // Return user to Main Menu & Demote user's level
	    	$response = "CON You have to choose a service.\n";
	    	$response .= "Press 0 to go back.\n";
	    	//demote
	    	$sqlLevelDemote="UPDATE `session_levels` SET `level`=0 where `session_id`='".$sessionId."'";
	    	$db->query($sqlLevelDemote);

	    	// Print the response onto the page so that our gateway can read it
	  		header('Content-type: text/plain');
		  		echo $response;	
    	}
}
```

If the user is not registered, then two scenarios unfold for which we have two switch statements. One, the user responds with a blank ussd response anywhere along the Registration process, for which we serve the appropriate response based on the level the user is at.
```PHP
//10. Register the user
if($userResponse==""){
//10a. On receiving a Blank. Advise user to input correctly based on level
switch ($level) {
    case 0:
	    //10b. Graduate the user to the next level, so you dont serve them the same menu
	     $sql10b = "INSERT INTO `session_levels`(`session_id`, `phoneNumber`,`level`) VALUES('".$sessionId."','".$phoneNumber."', 1)";
	     $db->query($sql10b);

	     //10c. Insert the phoneNumber, since it comes with the first POST
	     $sql10c = "INSERT INTO `users`(`phonenumber`) VALUES ('".$phoneNumber."')";
	     $db->query($sql10c);

	     //10d. Serve the menu request for name
	     $response = "CON Please enter your name";

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
    	//10f. Request fir city again
		$response = "CON City not supposed to be empty. Please reply with your city \n";

  		// Print the response onto the page so that our gateway can read it
  		header('Content-type: text/plain');
	  		echo $response;	
        break;

    default:
    	//10g. Request fir city again
		$response = "END Apologies, something went wrong-  \n";

  		// Print the response onto the page so that our gateway can read it
  		header('Content-type: text/plain');
	  		echo $response;	
        break;
}
}
```

Or Two, the user responds with a completed ussd response, for which we update the database according to the data we expect at that level that the user responds from. Of course you can include more validations for this data, but for simplicity, this will suffice.
```PHP
else{
//11. Update User table based on input to correct level
switch ($level) {
    case 0:
	     //11a. Serve the menu request for name
	     $response = "END This level should not be seen- ";

  		// Print the response onto the page so that our gateway can read it
  		header('Content-type: text/plain');
	  		echo $response;	
        break;

    case 1:
    	//11b. Update Name, Request for city
        $sql11b = "UPDATE `users` SET `username`='".$userResponse."' WHERE `phonenumber` LIKE '%". $phoneNumber ."%'";
        $db->query($sql11b);

        //11c. We graduate the user to the city level
        $sql11c = "UPDATE `session_levels` SET `level`=2 WHERE `session_id`='".$sessionId."'";
        $db->query($sql11c);

        //We request for the city
        $response = "CON Please enter your city";

  		// Print the response onto the page so that our gateway can read it
  		header('Content-type: text/plain');
	  		echo $response;	
        break;

    case 2:
    	//11d. Update city
        $sql11d = "UPDATE `users` SET `city`='".$userResponse."' WHERE `phonenumber` = '". $phoneNumber ."'";
        $db->query($sql11d);

    	//11e. Change level to 0
    	$sql11e = "INSERT INTO `session_levels`(`session_id`,`phoneNumber`,`level`) VALUES('".$sessionId."','".$phoneNumber."',1)";
    	$db->query($sql11e);   

    	//11f. Serve services menu- 
		$response = "CON Please choose a service.\n";
		$response .= " 1. Send me todays voice tip.\n";
		$response .= " 2. Please call me!\n";
		$response .= " 3. Send me Airtime!\n";				    	

  		// Print the response onto the page so that our gateway can read it
  		header('Content-type: text/plain');
	  		echo $response;	
        break;

    default:
    	//11g. Request for city again
		$response = "END Apologies, something went wrong-  \n";

  		// Print the response onto the page so that our gateway can read it
  		header('Content-type: text/plain');
	  		echo $response;	
        break;
		}			
	}
}
}
?>
```
##Complexities of Voice.
- The voice service included in this script requires a few juggling acts and probably requires a short review of its own.
When the user requests a to get a call, the following happens.
a) The script at https://b11cd817.ngrok.io/RegUSSD/RegistrationUSSD.php requests the call() method through the Africa's Talking Voice Gateway, passing the number to be called and the caller/dialer Id. The call is made and it comes into the users phone. When they answer isActive becomes 1.

```PHP
	case "2":
	    if($level==1){
	    	//9d. Call the user and bridge to a sales person
	      	$response = "END Please wait while we place your call.\n";

	      	//Make a call
	     	$from="+254711082300"; $to=$phoneNumber;
	      	// Create a new instance of our awesome gateway class
	      	$gateway = new AfricasTalkingGateway($username, $apikey);
	      	try { $gateway->call($from, $to); }
	      	catch ( AfricasTalkingGatewayException $e ){echo "Encountered an error when calling: ".$e->getMessage();}

			// Print the response onto the page so that our gateway can read it
			header('Content-type: text/plain');
			echo $response;	 
	    }
    break;
```			        
b) As a result, Africa's Talking gateway check the callback for the voice number in this case +254711082300.
c) The callback is a script we have created in the root folder of this application voiceCall.php whose URL is: 	https://b11cd817.ngrok.io/RegUSSD/voiceCall.php
d) The instructions are to respond with a text to speech message for the user to enter dtmf digits.

```PHP						
<?php
// This is a unique ID generated for this call
$sessionId = $_POST['sessionId'];
$isActive  = $_POST['isActive'];

if ($isActive == 1)  {
  $callerNumber = $_POST['callerNumber'];

  // Compose the response
  $response  = '<?xml version="1.0" encoding="UTF-8"?>';
  $response .= '<Response>';
  $response .= '<GetDigits timeout="30" finishOnKey="#" callbackUrl="https://b11cd817.ngrok.io/RegUSSD/voiceMenu.php">';
  $response .= '<Say>"Thank you for calling. Press 0 to talk to sales, 1 to talk to support or 2 to hear this message again."</Say>';
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
```
e) When the user enters the digit - in this case 0, 1 or 2, this digit is submitted to another script also at the root folder voiceMenu.php which lives at https://b11cd817.ngrok.io/RegUSSD/voiceMenu.php and which switches between the various dtmf digits to make an outgoing call to the right recipient, who will be bridged to speak to the person currently listening to music on hold. We specify this music with the ringtone flag as follows: ringbackTone="http://62.12.117.25:8010/media/SautiFinaleMoney.mp3"

```PHP
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
        //2b. Compose response - talk to sales- 
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
        //2c. Compose response - talk to support- 
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
        //2d. Redirect to the main IVR- 
		  $response  = '<?xml version="1.0" encoding="UTF-8"?>';
		  $response .= '<Response>';
		  $response .= '<Redirect>https://b11cd817.ngrok.io/voiceCall.php</Redirect>';
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

	  //3a. Store the data, write your SQL statements here- 
  }

?>
```

When the agent/person picks up, the conversation can go on.

- That is basically our application! Happy coding!
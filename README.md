# Setting Up a USSD Service for MicroFinance Institutions
#### A step-by-step guide (Dial * 384 * 125 # on the sandbox)

- Setting up the logic for USSD is easy with the [Africa's Talking API](docs.africastalking.com/ussd). This is a guide to how to use the code provided on this [repository](https://github.com/JaniKibichi/microfinance-ussd-app) to create a USSD that allows users to get registered and then access a menu of the following services:

| USSD APP Features                            |
| --------------------------------------------:| 
| Request to get a call from support           | 
| Deposit Money to user's account              |   
| Withdraw money from users account            |   
| Send money from users account to another     |   
| Repay loan                                   |   
| Buy Airtime                                  |  

## Prerequisites
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
 http://<your ip address>/MfUSSD/microfinanceUSSD.php

- This application has been developed on an Ubuntu 16.04LTS and lives in the web root at /var/www/html/MfUSSD. Courtesy of Ngrok, the publicly accessible url is: https://b11cd817.ngrok.io (instead of http://localhost) which is referenced in the code as well. 
(Create your own which will be different.)

- The webhook or callback to this application therefore becomes: 
https://b11cd817.ngrok.io/MfUSSD/microfinanceUSSD.php. 
To allow the application to talk to the Africa's Talking USSD gateway, this callback URL is placed in the dashboard, [under ussd callbacks here](https://account.africastalking.com/ussd/callback).

- Finally, this application works with a connection to a MYSQL database. Create a database with a name, username and password of your choice. Also create a session_levels table and a users table. These details are configured in the dbConnector.php and this is required in the main application script microfinanceUSSD.php.

mysql> describe microfinance;

| Field         | Type                         | Null  | Key | Default           | Extra                       |
| ------------- |:----------------------------:| -----:|----:| -----------------:| ---------------------------:|
| id            | int(6)                       |   YES |     | NULL              |                             |
| name          | varchar(30)                  |   YES |     | NULL              |                             |
| phonenumber   | varchar(20)                  |   YES |     | NULL              |                             |
| city          | varchar(30)                  |   YES |     | NULL              |                             |
| validation    | varchar(30)                  |   YES |     | NULL              |                             |
| reg_date      | timestamp                    |   NO  |     | CURRENT_TIMESTAMP | on update CURRENT_TIMESTAMP |

6 rows in set (0.02 sec)

mysql> describe session_levels;

| Field         | Type                         | Null  | Key | Default | Extra |
| ------------- |:----------------------------:| -----:|----:| -------:| -----:|
| session_id    | varchar(50)                  |   YES |     | NULL    |       |
| phonenumber   | varchar(25)                  |   YES |     | NULL    |       |
| level         | tinyint(1)                   |   YES |     | NULL    |       |

3 rows in set (0.02 sec)

mysql> describe account;

| Field         | Type                         | Null  | Key | Default           | Extra                       |
| ------------- |:----------------------------:| -----:|----:| -----------------:| ---------------------------:|
| id            | int(6) unsigned              |   YES |     | NULL              |                             |
| phonenumber   | varchar(20)                  |   YES |     | NULL              |                             |
| balance       | double(5,2) unsigned zerofill|   YES |     | NULL              |                             |
| loan          | double(5,2) unsigned zerofill|   YES |     | NULL              |                             |
| reg_date      | timestamp                    |   NO  |     | CURRENT_TIMESTAMP | on update CURRENT_TIMESTAMP |

5 rows in set (0.00 sec)

mysql> describe checkout;

| Field         | Type                         | Null  | Key | Default           | Extra                       |
| ------------- |:----------------------------:| -----:|----:| -----------------:| ---------------------------:|
| id            | int(6) unsigned              |   YES |     | NULL              |                             |
| status        | varchar(30)                  |   YES |     | NULL              |                             |
| phoneNumber   | varchar(30)                  |   YES |     | NULL              |                             |
| amount        | int(11)                      |   YES |     | NULL              |                             |
| reg_date      | timestamp                    |   NO  |     | CURRENT_TIMESTAMP | on update CURRENT_TIMESTAMP |

5 rows in set (0.00 sec)

## Features on the Services List
This USSD application has the following user journey.

- The user dials the ussd code - something like `*384*303#`

- The application checks if the user is registered or not. If the user is registered, the services menu is served which allows the user to: receive SMS, receive a call with an IVR menu.

- In case the user is not registered, the application prompts the user for their name and city (with validations), before successfully serving the services menu.

## Code walkthrough
This documentation is for the USSD application that lives in https://b11cd817.ngrok.io/MfUSSD/microfinanceUSSD.php.

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

- The USSD session has a set time limit(20-180 secs based on provider) under which the sessionId does not change. Using this sessionId, it is easy to navigate your user across the USSD menus by graduating their level(menu step) so that you dont serve them the same menu or lose track of where the user is. 
- Set the default level to 0 (or your own numbering scheme) -- the home menu.
- Check the session_levels table for a user with the same phone number as that received in the HTTP POST. If this exists, the user is returning and they therefore have a stored level. Grab that level and serve that user the right menu. Otherwise, serve the user the home menu.
```PHP
	//4. Set the default level of the user
	$level=0;

	//5. Check the level of the user from the DB and retain default level if none is found for this session
	$sql = "select level from session_levels where session_id ='".$sessionId." '";
	$levelQuery = $db->query($sql);
	if($result = $levelQuery->fetch_assoc()) {
  		$level = $result['level'];
	}


```

Before serving the menu, check if the incoming phone number request belongs to a registered user(sort of a login). If they are registered, they can access the menu, otherwise, they should first register.
```PHP
	//7. Check if the user is in the db
	$sql7 = "SELECT * FROM microfinance WHERE phoneNumber LIKE '%".$phoneNumber."%' LIMIT 1";
	$userQuery=$db->query($sql7);
	$userAvailable=$userQuery->fetch_assoc();

	//8. Check if the user is available (yes)->Serve the menu; (no)->Register the user
	if($userAvailable && $userAvailable['city']!=NULL && $userAvailable['name']!=NULL){
		//9. Serve the Services Menu
```

If the user is available and all their mandatory fields are complete, then the application switches between their responses to figure out which menu to serve. The first menu is usually a result of receiving a blank text -- the user just dialed in.
```PHP
//9. Serve the Services Menu 
//(if the user is fully registered, level 0 and 1 serve the basic menus, while the rest allow for financial transactions)
if($level==0 || $level==1){
	//9a. Check that the user actually typed something, else demote level and start at home
	switch ($userResponse) {
	    case "":
	        if($level==0){
	        	//9b. Graduate user to next level & Serve Main Menu
	        	$sql9b = "INSERT INTO `session_levels`(`session_id`,`phoneNumber`,`level`) VALUES('".$sessionId."','".$phoneNumber."',1)";
	        	$db->query($sql9b);

	        	//Serve our services menu
				$response = "CON Welcome to Nerd Microfinance, " . $userAvailable['name']  . ". Choose a service.\n";
				$response .= " 1. Please call me.\n";
				$response .= " 2. Deposit Money\n";
				$response .= " 3. Withdraw Money\n";
				$response .= " 4. Send Money\n";							
				$response .= " 5. Buy Airtime\n";
				$response .= " 6. Repay Loan\n";
				$response .= " 7. Account Balance\n";																							

	  			// Print the response onto the page so that our gateway can read it
	  			header('Content-type: text/plain');
		  			echo $response;						
	        }
	        break;
	    case "0":
	        if($level==0){
	        	//9b. Graduate user to next level & Serve Main Menu
	        	$sql9b = "INSERT INTO `session_levels`(`session_id`,`phoneNumber`,`level`) VALUES('".$sessionId."','".$phoneNumber."',1)";
	        	$db->query($sql9b);

	        	//Serve our services menu
				$response = "CON Welcome to Nerd Microfinance, " . $userAvailable['username']  . ". Choose a service.\n";
				$response .= " 1. Please call me.\n";
				$response .= " 2. Deposit\n";
				$response .= " 3. Withdraw\n";
				$response .= " 4. Send Money\n";							
				$response .= " 5. Buy Airtime\n";
				$response .= " 6. Repay Loan\n";
				$response .= " 7. Account Balance\n";																							

	  			// Print the response onto the page so that our gateway can read it
	  			header('Content-type: text/plain');
		  			echo $response;						
	        }
	        break;			        
	    case "1":
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
	    case "2":
	    	if($level==1){
	    		//9e. Ask how much and Launch the Mpesa Checkout to the user
				$response = "CON How much are you depositing?\n";
				$response .= " 1. 19 Shillings.\n";
				$response .= " 2. 18 Shillings.\n";
				$response .= " 3. 17 Shillings.\n";							

				//Update sessions to level 9
		    	$sqlLvl9="UPDATE `session_levels` SET `level`=9 where `session_id`='".$sessionId."'";
		    	$db->query($sqlLvl9);

	  			// Print the response onto the page so that our gateway can read it
	  			header('Content-type: text/plain');
		  			echo $response;	 			    		
	    	}
	        break;	
	    case "3":
	    	if($level==1){
	    		//9e. Ask how much and Launch B2C to the user
				$response = "CON How much are you withdrawing?\n";
				$response .= " 1. 15 Shillings.\n";
				$response .= " 2. 16 Shillings.\n";
				$response .= " 3. 17 Shillings.\n";							

				//Update sessions to level 10
		    	$sqlLvl10="UPDATE `session_levels` SET `level`=10 where `session_id`='".$sessionId."'";
		    	$db->query($sqlLvl10);


	  			// Print the response onto the page so that our gateway can read it
	  			header('Content-type: text/plain');
		  			echo $response;	 			    		
	    	}
	        break;	
	    case "4":
	    	if($level==1){
	    		//9g. Send Another User Some Money
				$response = "CON You can only send 15 shillings.\n";
				$response .= " Enter a valid phonenumber (like 0722122122)\n";					
	  			// Print the response onto the page so that our gateway can read it
	  			header('Content-type: text/plain');
		  			echo $response;	

				//Update sessions to level 11
		    	$sqlLvl11="UPDATE `session_levels` SET `level`=11 where `session_id`='".$sessionId."'";
		    	$db->query($sqlLvl11);

		    	//B2C
		    		//Find account
				$sql10a = "SELECT * FROM account WHERE phoneNumber LIKE '%".$phoneNumber."%' LIMIT 1";
				$balQuery=$db->query($sql10a);
				$balAvailable=$balQuery->fetch_assoc();

				if($balAvailable=$balQuery->fetch_assoc()){
				// Reduce balance
				$newBal = $balAvailable['balance'];	
				$newBal -= 15;				

				    if($newBal > 0){					    	
						$gateway = new AfricasTalkingGateway($username, $apiKey);
						$productName  = "Nerd Payments"; $currencyCode = "KES";
						$recipient1   = array("phoneNumber" => $phoneNumber,"currencyCode" => "KES", "amount"=> 15,"metadata"=>array("name"=> "Clerk","reason" => "May Salary"));
						$recipients  = array($recipient1);
						try { $responses = $gateway->mobilePaymentB2CRequest($productName,$recipients); }
						catch(AfricasTalkingGatewayException $e){ echo "Received error response: ".$e->getMessage(); }											    	
			    		}
		    	   }
		    		
	    	}
	        break;
	    case "5":
	    	if($level==1){
		    	//Find account
				$sql10a = "SELECT * FROM account WHERE phoneNumber LIKE '%".$phoneNumber."%' LIMIT 1";
				$balQuery=$db->query($sql10a);
				$balAvailable=$balQuery->fetch_assoc();

				if($balAvailable=$balQuery->fetch_assoc()){
					// Reduce balance
					$newBal = $balAvailable['balance'];	
					$newBal -= 5;				

					if($newBal > 0){
		    		//9e. Send user airtime
					$response = "END Please wait while we load your airtime account.\n";
		  			// Print the response onto the page so that our gateway can read it
		  			header('Content-type: text/plain');
			  			echo $response;	
						// Search DB and the Send Airtime
						$recipients = array( array("phoneNumber"=>"".$phoneNumber."", "amount"=>"KES 5") );
						//JSON encode
						$recipientStringFormat = json_encode($recipients);
						//Create an instance of our gateway class, pass your credentials
						$gateway = new AfricasTalkingGateway($username, $apikey);    
						try { $results = $gateway->sendAirtime($recipientStringFormat);}
						catch(AfricasTalkingGatewayException $e){ echo $e->getMessage(); }
					} else {
			    	//Alert user of insufficient funds
			    	$response = "END Sorry, you dont have sufficient\n";
			    	$response .= " funds in your account \n";	

		  			// Print the response onto the page so that our gateway can read it
		  			header('Content-type: text/plain');
			  			echo $response;						    						
					}
			    }
		    		
	    	}
	        break;
	    case "6":
	    	if($level==1){
	    		//9e. Ask how much and Launch the Mpesa Checkout to the user
				$response = "CON How much are you depositing?\n";
				$response .= " 4. 15 Shilling.\n";
				$response .= " 5. 16 Shillings.\n";
				$response .= " 6. 17 Shillings.\n";							

				//Update sessions to level 12
		    	$sqlLvl12="UPDATE `session_levels` SET `level`=12 where `session_id`='".$sessionId."'";
		    	$db->query($sqlLvl12);

	  			// Print the response onto the page so that our gateway can read it
	  			header('Content-type: text/plain');
		  			echo $response;	 			    		
	    	}
	        break;	
	    case "7":
	    	if($level==1){
				// Find the user in the db
				$sql7 = "SELECT * FROM microfinance WHERE phoneNumber LIKE '%".$phoneNumber."%' LIMIT 1";
				$userQuery=$db->query($sql7);
				$userAvailable=$userQuery->fetch_assoc();

	    		// Find the account
				$sql7a = "SELECT * FROM account WHERE phoneNumber LIKE '%".$phoneNumber."%' LIMIT 1";
				$BalQuery=$db->query($sql7a);
				$newBal = 0.00; $newLoan = 0.00;

				if($BalAvailable=$BalQuery->fetch_assoc()){
				$newBal = $BalAvailable['balance'];
				$newLoan = $BalAvailable['loan'];
				}
				//Respond with user Balance
				$response = "END Your account statement.\n";
				$response .= "Nerd Microfinance.\n";
				$response .= "Name: ".$userAvailable['name']."\n";	
				$response .= "City: ".$userAvailable['city']."\n";	
				$response .= "Balance: ".$newBal."\n";	
				$response .= "Loan: ".$newLoan."\n";																													
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
}else{
	// Financial Services Delivery
	switch ($level){
	    case 9:
	    	//9a. Collect Deposit from user, update db
			switch ($userResponse) {
			    case "1":
				    //End session
			    	$response = "END Kindly wait 1 minute for the Checkout.\n";
			    	// Print the response onto the page so that our gateway can read it
			  		header('Content-type: text/plain');
 			  		echo $response;	

 			  		$amount=19;
					//Create pending record in checkout to be cleared by cronjobs
		        	$sql9aa = "INSERT INTO checkout (`status`,`amount`,`phoneNumber`) VALUES('pending','".$amount."','".$phoneNumber."')";
		        	$db->query($sql9aa); 	        			       	
		        break;	

			    case "2":
			        // End session
			    	$response = "END Kindly wait 1 minute for the Checkout.\n";
			    	// Print the response onto the page so that our gateway can read it
			  		header('Content-type: text/plain');
 			  		echo $response;	

 			  		$amount=18;
					//Create pending record in checkout to be cleared by cronjobs
		        	$sql9aa = "INSERT INTO checkout (`status`,`amount`,`phoneNumber`) VALUES('pending','".$amount."','".$phoneNumber."')";
		        	$db->query($sql9aa); 		        	       	
			    break;

			    case "3":
			        // End session
			    	$response = "END Kindly wait 1 minute for the Checkout.\n";
			    	// Print the response onto the page so that our gateway can read it
			  		header('Content-type: text/plain');
 			  		echo $response;	

 			  		$amount=17;
					//Create pending record in checkout to be cleared by cronjobs
		        	$sql9aa = "INSERT INTO checkout (`status`,`amount`,`phoneNumber`) VALUES('pending','".$amount."','".$phoneNumber."')";
		        	$db->query($sql9aa); 			        		       	
			    break;

			    default:
				$response = "END Apologies, something went wrong... \n";
			  		// Print the response onto the page so that our gateway can read it
			  		header('Content-type: text/plain');
			  		echo $response;	
			    break;
			}				
        	break;
	    case 10: 
	    	//Withdraw fund from account
			switch ($userResponse) {
			    case "1":
			    	//Find account
					$sql10a = "SELECT * FROM account WHERE phoneNumber LIKE '%".$phoneNumber."%' LIMIT 1";
					$balQuery=$db->query($sql10a);
					$balAvailable=$balQuery->fetch_assoc();

					if($balAvailable=$balQuery->fetch_assoc()){
					// Reduce balance
					$newBal = $balAvailable['balance'];
					$newBal -=15;					
					}

					if($newBal > 0){

				    	//Alert user of incoming Mpesa cash
				    	$response = "END We are sending your withdrawal of\n";
				    	$response .= " KES 15/- shortly... \n";

						//Declare Params
						$gateway = new AfricasTalkingGateway($username, $apikey);
						$productName  = "Nerd Payments";
						$currencyCode = "KES";
						$recipient   = array("phoneNumber" => "".$phoneNumber."","currencyCode" => "KES","amount"=>15,"metadata"=>array("name"=>"Client","reason" => "Withdrawal"));
						$recipients  = array($recipient);
						//Send B2c
						try {$responses = $gateway->mobilePaymentB2CRequest($productName, $recipients);}
						catch(AfricasTalkingGatewayException $e){echo "Received error response: ".$e->getMessage();}	
					} else {
				    	//Alert user of insufficient funds
				    	$response = "END Sorry, you dont have sufficient\n";
				    	$response .= " funds in your account \n";						
					}		    	

					// Print the response onto the page so that our gateway can read it
					header('Content-type: text/plain');
				  	echo $response;	
			    break;

			    case "2":
			    	//Find account
					$sql10b = "SELECT * FROM account WHERE phoneNumber LIKE '%".$phoneNumber."%' LIMIT 1";
					$balQuery=$db->query($sql10b);
					$balAvailable=$balQuery->fetch_assoc();

					if($balAvailable=$balQuery->fetch_assoc()){
					// Reduce balance
					$newBal = $balAvailable['balance'];	
					$newBal -= 16;			
					}

					if($newBal > 0){					    
				    	//Alert user of incoming Mpesa cash
				    	$response = "END We are sending your withdrawal of\n";
				    	$response .= " KES 16/- shortly... \n";

						//Declare Params
						$gateway = new AfricasTalkingGateway($username, $apikey);
						$productName  = "Nerd Payments";
						$currencyCode = "KES";
						$recipient   = array("phoneNumber" => "".$phoneNumber."","currencyCode" => "KES","amount"=>16,"metadata"=>array("name"=>"Client","reason" => "Withdrawal"));
						$recipients  = array($recipient);
						//Send B2c
						try {$responses = $gateway->mobilePaymentB2CRequest($productName, $recipients);}
						catch(AfricasTalkingGatewayException $e){echo "Received error response: ".$e->getMessage();}
				  		// Print the response onto the page so that our gateway can read it
				  		header('Content-type: text/plain');
					  	echo $response;									
					} else {
				    	//Alert user of insufficient funds
				    	$response = "END Sorry, you dont have sufficient\n";
				    	$response .= " funds in your account \n";	

				  		// Print the response onto the page so that our gateway can read it
				  		header('Content-type: text/plain');
					  	echo $response;							    						
					}	
			    break;

			    case "3":
			    	//Find account
					$sql10c = "SELECT * FROM account WHERE phoneNumber LIKE '%".$phoneNumber."%' LIMIT 1";
					$balQuery=$db->query($sql10c);
					$balAvailable=$balQuery->fetch_assoc();

					if($balAvailable=$balQuery->fetch_assoc()){
					// Reduce balance
					$newBal = $balAvailable['balance'];	
					$newBal -= 17;				
					}

					if($newBal > 0){					    
				    	//Alert user of incoming Mpesa cash
				    	$response = "END We are sending your withdrawal of\n";
				    	$response .= " KES 17/- shortly... \n";

						//Declare Params
						$gateway = new AfricasTalkingGateway($username, $apikey);
						$productName  = "Nerd Payments";
						$currencyCode = "KES";
						$recipient   = array("phoneNumber" => "".$phoneNumber."","currencyCode" => "KES","amount"=>17,"metadata"=>array("name"=>"Client","reason" => "Withdrawal"));
						$recipients  = array($recipient);
						//Send B2c
						try {$responses = $gateway->mobilePaymentB2CRequest($productName, $recipients);}
						catch(AfricasTalkingGatewayException $e){echo "Received error response: ".$e->getMessage();}
				  		// Print the response onto the page so that our gateway can read it
				  		header('Content-type: text/plain');
					  	echo $response;								
					} else {
				    	//Alert user of insufficient funds
				    	$response = "END Sorry, you dont have sufficient\n";
				    	$response .= " funds in your account \n";
				  		// Print the response onto the page so that our gateway can read it
				  		header('Content-type: text/plain');
					  	echo $response;						    							
					}										    	
			    break;

			    default:
					$response = "END Apologies, something went wrong... \n";
				  		// Print the response onto the page so that our gateway can read it
				  		header('Content-type: text/plain');
				  		echo $response;	
				break;    
			} 	        	
	    	break;	
	    case 11:
	    	//11d. Send money to person described
			$response = "END We are sending KES 15/- \n";
			$response .= "to the loanee shortly. \n";

	    	//Find and update Creditor
			$sql11d = "SELECT * FROM account WHERE phoneNumber LIKE '%".$phoneNumber."%' LIMIT 1";
			$balQuery=$db->query($sql11d);
			$balAvailable=$balQuery->fetch_assoc();

			if($balAvailable=$balQuery->fetch_assoc()){
			// Reduce balance
			$newBal = $balAvailable['balance'];	
			$newBal -=15;				
			}

			//Send loan only if new balance is above 0 
			if($newBal > 0){

		    	//Find and update Debtor
				$sql11dd = "SELECT * FROM account WHERE phoneNumber LIKE '%".$userResponse."%' LIMIT 1";
				$loanQuery=$db->query($sql11dd);

				if($loanAvailable=$loanQuery->fetch_assoc()){
				$newLoan = $loanAvailable['balance'];
				$newLoan += 15;
				}				

				// SMS New Balance
				$code = '20880';
            	$recipients = $phoneNumber;
            	$message    = "We have sent 15/- to".$userResponse." If this is a wrong number the transaction will fail.
            				   Your new balance is ".$newBal.". Thank you.";
            	$gateway    = new AfricasTalkingGateway($username, $apikey);
            	try { $results = $gateway->sendMessage($recipients, $message, $code); }
            	catch ( AfricasTalkingGatewayException $e ) {echo "Encountered an error while sending: ".$e->getMessage(); }

            	// Update the DB
		        $sql11e = "UPDATE account SET `balance`='".$newBal."' WHERE `phonenumber` = '". $phoneNumber ."'";
		        $db->query($sql11e);

		    	//11f. Change level to 0
	        	$sql11f = "INSERT INTO account (`loan`,`phoneNumber`) VALUES('".$newLoan."','".$phoneNumber."',1)";
	        	$db->query($sql11f);   

				//Declare Params
				$gateway = new AfricasTalkingGateway($username, $apikey);
				$productName  = "Nerd Payments";
				$currencyCode = "KES";
				$recipient   = array("phoneNumber" => "".$phoneNumber."","currencyCode" => "KES","amount"=>15,"metadata"=>array("name"=>"Client","reason" => "Withdrawal"));
				$recipients  = array($recipient);
				//Send B2c
				try {$responses = $gateway->mobilePaymentB2CRequest($productName, $recipients);}
				catch(AfricasTalkingGatewayException $e){echo "Received error response: ".$e->getMessage();}	

				//respond
				$response = "END We have sent money to".$userResponse." \n";	

			} else {
				//respond
				$response = "END Sorry we could not send the money. \n";	
				$response .= "Your dont have enough money. \n";						
			}	    	

	  		// Print the response onto the page so that our gateway can read it
	  		header('Content-type: text/plain');
		  	echo $response;	
	    	break;
	    case 12:
	    	//12. Pay loan
			switch ($userResponse) {
			    case "4":
				    //End session
			    	$response = "END Kindly wait 1 minute for the Checkout. You are repaying 15/-..\n";
			    	// Print the response onto the page so that our gateway can read it
			  		header('Content-type: text/plain');
 			  		echo $response;	

 			  		$amount=15;
					//Create pending record in checkout to be cleared by cronjobs
		        	$sql12a = "INSERT INTO checkout (`status`,`amount`,`phoneNumber`) VALUES('pending','".$amount."','".$phoneNumber."')";
		        	$db->query($sql12a); 							       	
		        break;	

			    case "5":
				    //End session
			    	$response = "END Kindly wait 1 minute for the Checkout. You are repaying 16/-..\n";
			    	// Print the response onto the page so that our gateway can read it
			  		header('Content-type: text/plain');
 			  		echo $response;	

 			  		$amount=16;
					//Create pending record in checkout to be cleared by cronjobs
		        	$sql12a = "INSERT INTO checkout (`status`,`amount`,`phoneNumber`) VALUES('pending','".$amount."','".$phoneNumber."')";
		        	$db->query($sql12a); 									       	
			    break;

			    case "6":
				    //End session
			    	$response = "END Kindly wait 1 minute for the Checkout. You are repaying 17/-..\n";
			    	// Print the response onto the page so that our gateway can read it
			  		header('Content-type: text/plain');
 			  		echo $response;	

 			  		$amount=17;
					//Create pending record in checkout to be cleared by cronjobs
		        	$sql12a = "INSERT INTO checkout (`status`,`amount`,`phoneNumber`) VALUES('pending','".$amount."','".$phoneNumber."')";
		        	$db->query($sql12a); 	       	
			    break;

			    default:
				$response = "END Apologies, something went wrong... \n";
			  		// Print the response onto the page so that our gateway can read it
			  		header('Content-type: text/plain');
			  		echo $response;	
			    break;
			}				
        	break;	
	    default:
	    	//11g. Request for city again
			$response = "END Apologies, something went wrong... \n";

	  		// Print the response onto the page so that our gateway can read it
	  		header('Content-type: text/plain');
		  	echo $response;	
	    	break;		        
	}
}	
```
If the user is not registered, we use the users level - purely to take the user through the registration process. We also enclose the logic in a condition that prevents the user from sending empty responses.
```PHP
	} else{
		//10. Check that user response is not empty
		if($userResponse==""){
			//10a. On receiving a Blank. Advise user to input correctly based on level
			switch ($level) {
			    case 0:
				    //10b. Graduate the user to the next level, so you dont serve them the same menu
				     $sql10b = "INSERT INTO `session_levels`(`session_id`, `phoneNumber`,`level`) VALUES('".$sessionId."','".$phoneNumber."', 1)";
				     $db->query($sql10b);

				     //10c. Insert the phoneNumber, since it comes with the first POST
				     $sql10c = "INSERT INTO microfinance(`phonenumber`) VALUES ('".$phoneNumber."')";
				     $db->query($sql10c);

				     //10d. Serve the menu request for name
				     $response = "CON Please enter your name";

			  		// Print the response onto the page so that our gateway can read it
			  		header('Content-type: text/plain');
 			  		echo $response;	
			        break;

			    case 1:
			    	//10e. Request again for name - level has not changed...
        			$response = "CON Name not supposed to be empty. Please enter your name \n";

			  		// Print the response onto the page so that our gateway can read it
			  		header('Content-type: text/plain');
 			  		echo $response;	
			        break;

			    case 2:
			    	//10f. Request for city again --- level has not changed...
					$response = "CON City not supposed to be empty. Please reply with your city \n";

			  		// Print the response onto the page so that our gateway can read it
			  		header('Content-type: text/plain');
 			  		echo $response;	
			        break;

			    default:
			    	//10g. End the session
					$response = "END Apologies, something went wrong... \n";

			  		// Print the response onto the page so that our gateway can read it
			  		header('Content-type: text/plain');
 			  		echo $response;	
			        break;
			}
		}else{
			//11. Update User table based on input to correct level
			switch ($level) {
			    case 0:
				    //10b. Graduate the user to the next level, so you dont serve them the same menu
				     $sql10b = "INSERT INTO `session_levels`(`session_id`, `phoneNumber`,`level`) VALUES('".$sessionId."','".$phoneNumber."', 1)";
				     $db->query($sql10b);

				     //10c. Insert the phoneNumber, since it comes with the first POST
				     $sql10c = "INSERT INTO microfinance (`phonenumber`) VALUES ('".$phoneNumber."')";
				     $db->query($sql10c);

				     //10d. Serve the menu request for name
				     $response = "CON Please enter your name";

			  		// Print the response onto the page so that our gateway can read it
			  		header('Content-type: text/plain');
				  		echo $response;	
			    	break;		    
			    case 1:
			    	//11b. Update Name, Request for city
			        $sql11b = "UPDATE microfinance SET `name`='".$userResponse."' WHERE `phonenumber` LIKE '%". $phoneNumber ."%'";
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
			        $sql11d = "UPDATE microfinance SET `city`='".$userResponse."' WHERE `phonenumber` = '". $phoneNumber ."'";
			        $db->query($sql11d);

			    	//11e. Change level to 0
		        	$sql11e = "INSERT INTO `session_levels`(`session_id`,`phoneNumber`,`level`) VALUES('".$sessionId."','".$phoneNumber."',1)";
		        	$db->query($sql11e);  

					//11f. Serve the menu request for name
					$response = "END You have been successfully registered. Dial *384*303# to choose a service.";	        	   	

			  		// Print the response onto the page so that our gateway can read it
			  		header('Content-type: text/plain');
				  	echo $response;	
			    	break;			        		        		        
			    default:
			    	//11g. Request for city again
					$response = "END Apologies, something went wrong... \n";

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
## Complexities of Voice.
- The voice service included in this script requires a few juggling acts and probably requires a short review of its own.
When the user requests a to get a call, the following happens.
a) The script at https://b11cd817.ngrok.io/MfUSSD/microfinanceUSSD.php requests the call() method through the Africa's Talking Voice Gateway, passing the number to be called and the caller/dialer Id. The call is made and it comes into the users phone. When they answer isActive becomes 1.

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
  $response .= '<GetDigits timeout="30" finishOnKey="#" callbackUrl="https://b11cd817.ngrok.io/MfUSSD/voiceMenu.php">';
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
e) When the user enters the digit - in this case 0, 1 or 2, this digit is submitted to another script also at the root folder voiceMenu.php which lives at https://b11cd817.ngrok.io/MfUSSD/voiceMenu.php and which switches between the various dtmf digits to make an outgoing call to the right recipient, who will be bridged to speak to the person currently listening to music on hold. We specify this music with the ringtone flag as follows: ringbackTone="http://62.12.117.25:8010/media/SautiFinaleMoney.mp3"

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
		  $response .= '<Redirect>https://b11cd817.ngrok.io/MfUSSD/voiceCall.php</Redirect>';
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
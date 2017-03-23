<?php
//1. Ensure ths code runs only after a POST from AT
if(!empty($_POST) && !empty($_POST['phoneNumber'])){
	require_once('dbConnector.php');
	require_once('AfricasTalkingGateway.php');
	require_once('config.php');

	//2. receive the POST from AT
	$sessionId     =$_POST['sessionId'];
	$serviceCode   =$_POST['serviceCode'];
	$phoneNumber   =$_POST['phoneNumber'];
	$text          =$_POST['text'];

	//3. Explode the text to get the value of the latest interaction - think 1*1
	$textArray=explode('*', $text);
	$userResponse=trim(end($textArray));

	//4. Set the default level of the user
	$level=0;

	//5. Check the level of the user from the DB and retain default level if none is found for this session
	$sql = "select level from session_levels where session_id ='".$sessionId." '";
	$levelQuery = $db->query($sql);
	if($result = $levelQuery->fetch_assoc()) {
  		$level = $result['level'];
	}	

	//6. Create an account and ask questions later
	$sql6 = "SELECT * FROM account WHERE phoneNumber LIKE '%".$phoneNumber."%' LIMIT 1";
	$acQuery=$db->query($sql6);
	if(!$acAvailable=$acQuery->fetch_assoc()){
		$sql1A = "INSERT INTO account (`phoneNumber`) VALUES('".$phoneNumber."')";
		$db->query($sql1A); 
	}

	//7. Check if the user is in the db
	$sql7 = "SELECT * FROM microfinance WHERE phoneNumber LIKE '%".$phoneNumber."%' LIMIT 1";
	$userQuery=$db->query($sql7);
	$userAvailable=$userQuery->fetch_assoc();


	//8. Check if the user is available (yes)->Serve the menu; (no)->Register the user
	if($userAvailable && $userAvailable['city']!=NULL && $userAvailable['name']!=NULL){
		//9. Serve the Services Menu (if the user is fully registered, 
		//level 0 and 1 serve the basic menus, while the rest allow for financial transactions)
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

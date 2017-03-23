<?php 
//Require dependencies
	require_once('dbConnector.php');
	require_once('AfricasTalkingGateway.php');
	require_once('config.php');

$data  = json_decode(file_get_contents('php://input'), true);
print_r($data);

// Process the data...
$category = $data["category"];
$status = $data["status"];

if ( $category == "MobileCheckout" && $status == "Success" ) {

   // We have been paid by one of our customers!!
   $phoneNumber = $data["source"];
   $value       = $data["value"];
   $account     = $data["clientAccount"];

	$valueArray=explode(' ', $value);
	$valAmt=trim(end($valueArray));   

	//string to int
	$valAmt=+0;

    //Find and update Creditor
	$sql11d = "SELECT * FROM account WHERE phoneNumber LIKE '%".$phoneNumber."%' LIMIT 1";
	$balQuery=$db->query($sql11d);
	$balAvailable=$balQuery->fetch_assoc();

	// Manage balance
	if($balAvailable=$balQuery->fetch_assoc()){
	$newBal = $valAmt + $balAvailable['balance'];					
	}

	// Update the DBs
    $sql11e = "UPDATE `account` SET `balance`='".$newBal."' WHERE `phonenumber` = '". $phoneNumber ."'";
    $db->query($sql11e);

    $sql11f = "UPDATE `checkout` SET `status`='received' WHERE `phonenumber` = '". $phoneNumber ."'";
    $db->query($sql11f);    

		// SMS New Balance
	$code = '20880';
	$recipients = $phoneNumber;
	$message    = "We have sent ".$value." via".$userResponse." to Nerd Sacco. Your new balance is ".$newBal.". Thank you.";
	$gateway    = new AfricasTalkingGateway($username, $apikey);
	try { $results = $gateway->sendMessage($recipients, $message, $code); }
	catch ( AfricasTalkingGatewayException $e ) {echo "Encountered an error while sending: ".$e->getMessage(); }

}


?>
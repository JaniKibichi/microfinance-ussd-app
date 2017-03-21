<?php
// Accept a HTTP POST from AT
	require_once('dbConnector.php');
	require_once('AfricasTalkingGateway.php');
	require_once('config.php');
//Concept 
/*
	- query checkout DB which has numbers and status
	- launch checkout.
	- that is all!

*/

//Check the level of the user from the DB and retain default level if none is found for this session
$sql = "select * from checkout where status = 'pending' ";
$statusQuery = $db->query($sql);

while($results = $statusQuery->fetch_assoc()){

	$gateway = new AfricasTalkingGateway($username, $apikey);

	$productName  ="Nerd Payments"; 
	$phoneNumber  = $results['phoneNumber'];;
	$currencyCode = "KES";
	$amount       = $results['amount'];
	$metadata     = array("sacco"=>"Nerds","productId"=>"321");

	try {
	  $transactionId = $gateway->initiateMobilePaymentCheckout($productName, $phoneNumber,$currencyCode, $amount, $metadata);
	  echo "date now ".date("Y-m-d H:i:s")." -- The id here is ".$transactionId."\n";
	}
	catch(AfricasTalkingGatewayException $e){
	  echo "Received error response: ".$e->getMessage()."\n";
	}

}
		

?>
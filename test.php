<?php 
	require_once('dbConnector.php');
	require_once('AfricasTalkingGateway.php');
	require_once('config.php');

	$productName  ="Nerd Payments"; 
	$phoneNumber  = "+254708415904";
	$currencyCode = "KES";
	$amount       = 19;
	$metadata     = array("sacco"=>"Nerds","productId"=>"321");

	try {
	  $transactionId = $gateway->initiateMobilePaymentCheckout($productName, $phoneNumber,$currencyCode, $amount, $metadata);
	  echo "The id here is ".$transactionId;
	}
	catch(AfricasTalkingGatewayException $e){
	  echo "Received error response: ".$e->getMessage();
	}

 ?>
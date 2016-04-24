<?php
	include "init.php";
	include "asana.php";

	// Determine which host we are running on, and hence which API keys to use
	$host = $_SERVER['HTTP_HOST'];
	$asana_config = $asana_app[$host];
	$secure = $asana_config['secure'];

	$client = Asana\Client::oauth(array(
	    'client_id' => $asana_config['key'],
	    'client_secret' => $asana_config['secret'],
	    'redirect_uri' => $asana_config['redirect']
	));

	// Check for OAuth response
	if (isset($_GET['state'])) {
		$state = $_COOKIE['auth_state'];
		unset($_COOKIE['auth_state']);
		setcookie('auth_state', '', time() - 3600);

		if ($_GET['state'] == $state) {
		  $access_token = $client->dispatcher->fetchToken($_GET['code']);
		  $refresh_token = $client->dispatcher->refreshToken;
		  $token = array(
		  	'access_token' => $access_token,
		  	'refresh_token' => $refresh_token,
		  	'expires' => time() + $client->dispatcher->expiresIn,
		  	'host' => $host);

		  setcookie("auth_token", json_encode($token), 0, "", "", $secure, $secure);
		  header("Location: /");
		  echo "<h1>Redirecting back to main interface</h1>";
		} else {
		  echo "Invalid state";
		}

		exit;
	}

	$state = null;
	$url = $client->dispatcher->authorizationUrl($state);

	// Store the state in a cookie
	unset($_COOKIE['auth_token']);
	setcookie('auth_token', '', time() - 3600);
	setcookie("auth_state", $state, 0, "", "", $secure, $secure);

	header("Location: ".$url);
	exit;
?>
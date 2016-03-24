<?php
	include "init.php";
	include "asana.php";

	$client = Asana\Client::oauth(array(
	    'client_id' => $config['asana_app'],
	    'client_secret' => $config['asana_secret'],
	    'redirect_uri' => 'https://asana.kothar.net/auth'
	));

	// Check for OAuth response
	if (isset($_GET['state'])) {
		$state = $_COOKIE['auth_state'];
		unset($_COOKIE['auth_state']);
		setcookie('auth_state', '', time() - 3600);

		if ($_GET['state'] == $state) {
		  $token = $client->dispatcher->fetchToken($_GET['code']);
		  setcookie("auth_token", $token, 0, "", "", true, true);
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
	setcookie("auth_state", $state, 0, "", "", true, true);

	header("Location: ".$url);
	exit;
?>
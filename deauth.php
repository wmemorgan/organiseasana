<?php 
	unset($_COOKIE['auth_token']);
	setcookie('auth_token', '', time() - 3600);

	header("Location: /");
	exit;
?>
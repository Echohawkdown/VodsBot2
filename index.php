<?php
	error_reporting(E_ALL);
	//ini_set('display_errors', 1);
	date_default_timezone_set('UTC');
	require 'bot.php';

	$Bot = new Bot();
	$Bot->update();
	var_dump('foo');
?>
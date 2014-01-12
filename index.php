<?php
	error_reporting(E_ALL);
	date_default_timezone_set('UTC');
	require 'bot.php';
	$Bot = new Bot();
	$Bot->run();
?>
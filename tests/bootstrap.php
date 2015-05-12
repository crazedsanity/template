<?php

//echo "RUNNING (". __FILE__ .")!!!!\n";

// set the timezone to avoid spurious errors from PHP
date_default_timezone_set("America/Chicago");

if(file_exists(__DIR__ .'/../vendor/autoload.php')) {
	require_once(__DIR__ .'/../vendor/autoload.php');
}


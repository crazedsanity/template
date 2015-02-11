<?php

//echo "RUNNING (". __FILE__ .")!!!!\n";

// set the timezone to avoid spurious errors from PHP
date_default_timezone_set("America/Chicago");

require_once(dirname(__FILE__) .'/../vendor/crazedsanity/core/AutoLoader.class.php');

AutoLoader::registerDirectory(dirname(__FILE__) .'/../');
AutoLoader::registerDirectory(dirname(__FILE__) .'/../interfaces/');



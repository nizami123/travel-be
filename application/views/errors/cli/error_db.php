<?php defined('BASEPATH') OR exit('No direct script access allowed');
echo "\nERROR: ", isset($heading) ? $heading : (isset($message) ? $message : 'error'), "\n", isset($message) && isset($heading) ? $message : '', "\n";

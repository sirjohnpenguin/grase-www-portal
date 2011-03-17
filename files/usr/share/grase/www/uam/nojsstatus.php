<?php

require_once('includes/site.inc.php');

// MySQL call to radacct where IP address matches a session that is current, get username
// Show user details

// Meta refresh to update

$ipaddress = $_SERVER['REMOTE_ADDR'];

$user = DatabaseFunctions::getInstance()->getUserDetails(DatabaseFunctions::getInstance()->getRadiusUserByCurrentSession($ipaddress));

$smarty->assign('user', $user);

$smarty->display('nojsstatus.tpl');

//print_r($user);

?>

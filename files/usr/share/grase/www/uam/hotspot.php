<?php
// qrcode
session_start();
// qrcode
require_once('includes/site.inc.php');

load_templates(array('loginhelptext', 'belowloginhtml', 'termsandconditions', 'aboveloginhtml'));

/*$loginurl = parse_url($_GET['loginurl']);
$query = $loginurl['query'];
parse_str($query, $uamopts);*/


// qrcode

if($Settings->getSetting('qrcode') == 'TRUE'){


	if (isset($_GET['qrc'])){
		$qrc = $_GET['qrc'];
		$_SESSION['qrcode'] = $qrc;

		header("Location: http://$lanIP:3990/prelogin");
		die();
		
	}
	
	if ($_SESSION['qrcode']){
		$userQRDATA=DatabaseFunctions::getInstance()->getUserFromQRCodeHash($_SESSION['qrcode']);
		$username=$userQRDATA["Username"];
		
		
		if ($userQRDATA["QRCodeHash"] == NULL){ //if user qrcode disabled, dieee()
			session_destroy();
			header("Location: http://$lanIP:3990/prelogin");
			die();
		}
		
		if ($_SESSION['qrcode'] == $userQRDATA["QRCodeHash"]){
			$user_details=DatabaseFunctions::getInstance()->getUserDetails($username);
			$username=$user_details["Username"];
			$password=$user_details["Password"];
			
		}else{
			session_destroy();
			header("Location: http://$lanIP:3990/prelogin");
			die();
		}

		
		if (\Grase\Validate::MACAddress(strtoupper($username))) {
			session_destroy();
			header("Location: http://$lanIP:3990/prelogin");
			die();
		}
		
		$challenge = $_GET['challenge'];
		$userurl = urlencode($Settings->getSetting('qrcode_user_url'));
		$ident = '00';		
		
		if ($userQRDATA["Autologin"] == 'TRUE'){ //if autologin for current user
			
			if (! ( $username && $password && $challenge) )
			{
				session_destroy();
				header("Location: http://$lanIP:3990/prelogin");
			}
			$hexchal = pack ("H32", $challenge);
			$response = md5("\0" . $password . $hexchal);
			$challenge = urlencode($challenge);
			session_destroy();
			
			header("Location: http://$lanIP:3990/login?username=$username&response=$response&userurl=$userurl");
			
		}else{
			$_SESSION["username"]=$username;
			$smarty->assign("username", 'value='.$_SESSION["username"].'');
			$smarty->assign("password",' autofocus ');
			session_destroy();

		}
		
	}else{
		
	session_destroy();
	$smarty->assign("username", 'autofocus');
	$smarty->assign("password",'');
	}
	
	
	
	
}else{
	session_destroy();
	$smarty->assign("username", 'autofocus');
	$smarty->assign("password",'');

}


// qrcode



if(isset($_GET['disablejs']))
{
    // Set cookie
    setcookie('grasenojs','javascriptdisabled', time()+60*60*24*30);
    // Redirect via header to reload page?
    header("Location: http://$lanIP:3990/prelogin");
}

if(isset($_GET['enablejs']))
{
    // Set cookie
    setcookie('grasenojs','', time()-60*60*24*30);
    // Redirect via header to reload page?
    header("Location: http://$lanIP:3990/prelogin");
}

$res = @$_GET['res'];
$userurl = @$_GET['userurl'];
$challenge = @$_GET['challenge'];

if($userurl == 'http://logout/') $userurl = '';
if($userurl == 'http://1.0.0.0/') $userurl = '';

if($Settings->getSetting('disablejavascript') == 'TRUE')
{
    $nojs = true;
    $smarty->assign("nojs" , true);
    $smarty->assign("js" , false);
    $smarty->assign("jsdisabled" , true);
}elseif( isset($_COOKIE['grasenojs']) && $_COOKIE['grasenojs'] == 'javascriptdisabled')
{
    $nojs = true;
    $smarty->assign("nojs" , true);
    $smarty->assign("js" , false);
}else
{
    $nojs = false;
    $smarty->assign("nojs" , false);
    $smarty->assign("js" , true);
}

$smarty->assign("user_url", $userurl);
$smarty->assign("challenge", $challenge);
$smarty->assign("RealHostname", trim(file_get_contents('/etc/hostname')));
if($Settings->getSetting('autocreategroup'))
{
    $smarty->assign('automac', true);
}

/* Important parts of uamopts
    * challenge
    * userurl
    * res
    
*/    

if(!isset($_GET['res']))
{
    // Redirect to prelogin
        header("Location: http://$lanIP:3990/prelogin");
}

// Already been through prelogin
/*$jsloginlink = "http://$lanIP/grase/uam/mini?$query";
$nojsloginlink = $_GET['loginurl'];*/
    require_once '../radmin/automacusers.php';
if(@$_GET['automac'])
{
    // TODO only if this is enabled? (Although the function will do that 
    // anyway) so maybe only show the link if this is enabled?
    //
    // TODO need to ensure we have a challenge otherwise we need a fresh one, 
    // maybe if we AJAX the call so we always have a challenge?
    automacuser();
    exit;
}

switch($res)
{
    case 'already':
        //if ($userurl) header("Location: $userurl");
        // Fall through to welcome page?
        if($nojs)
        {
            $smarty->display('loggedin.tpl');
            exit;
        }
        break;
    
    case 'failed':
        // Login failed? Show error and display login again
        $reply = array("Login Failed");
        if($_GET['reply'] != '') $reply = array($_GET['reply']);
        $smarty->assign("error", $reply);
        //break; // Fall through?
        
    case 'notyet':
    case 'logoff':
        // Display login
        setup_login_form();
        break;
        
    case 'success':
        //Logged in. Try popup and redirect to userurl
        // If this is an automac login (check UID vs MAC) then we skip the 
        // normal success and go back to portal which should work better as 
        // it's not a nojs login
        if($_GET['uid'] == mactoautousername($_GET['mac']))
        {
            break;
        }
        //
        //if ($nojs){ // if js is disabled
			load_templates(array('loggedinnojshtml'));
			$smarty->display('loggedin.tpl');
			exit;
		//}
        
        break; // show default       
        
}


function setup_login_form()
{
    global $smarty;
    $smarty->display('portal.tpl');
    exit;
}

$smarty->display('portal.tpl');

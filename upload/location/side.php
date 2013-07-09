<?php
if (!defined('MCR')) exit;

require_once(MCR_ROOT.'instruments/monitoring.class.php');

ob_start();

if (!empty($user)) {
  
   if ($mode == 'control') 
   include Theme::Get('admin/side.html');  
   include Theme::Get('mineprofil.html');    
	
} else {
	
	if ($mode == 'register') $addition_events .= "BlockVisible('reg-box',true); BlockVisible('login-box',false);";

	include Theme::Get('login.html');		    
}

$content_side .= ob_get_clean();
?>
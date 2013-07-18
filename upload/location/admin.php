<?php
if (!defined('MCR')) exit;
 
if (empty($user) or $user->lvl() < 15) { header("Location: ".BASE_URL); exit; }

require(MCR_ROOT.'instruments/catalog.class.php');
require(MCR_ROOT.'instruments/alist.class.php');
require(MCR_ROOT.'instruments/monitoring.class.php');
 
function RatioList($selectid = 1) {

$html_ratio = '<option value="1" '.((1 == $selectid)?'selected':'').'>64x32 | 22x17</option>';

	for ($i=2;$i<=32;$i=$i+2)
		$html_ratio .= '<option value="'.$i.'" '.(($i == $selectid)?'selected':'').'>'.(64*$i).'x'.(32*$i).' | '.(22*$i).'x'.(17*$i).'</option>';
		
return $html_ratio;
}

function SaveOptions() {
global $config,$bd_names,$bd_money,$bd_users,$site_ways,$info;

$txt  = '<?php'.PHP_EOL;
$txt .= '$config = '.var_export($config, true).';'.PHP_EOL;
$txt .= '$bd_names = '.var_export($bd_names, true).';'.PHP_EOL;
$txt .= '$bd_users = '.var_export($bd_users, true).';'.PHP_EOL;
$txt .= '/* iconomy or some other plugin, just check names */'.PHP_EOL;
$txt .= '$bd_money = '.var_export($bd_money, true).';'.PHP_EOL;
$txt .= '$site_ways = '.var_export($site_ways, true).';'.PHP_EOL;
$txt .= '/* Put all new config additions here */'.PHP_EOL;
$txt .= '?>';

$result = file_put_contents(MCR_ROOT.'config.php', $txt);

	if (is_bool($result) and $result == false) {

	$info .= lng('WRITE_FAIL').' ( '.MCR_ROOT.'config.php )';	
	return false;
	}

return true;
}

$menu->SetItemActive('admin');

$prefix = 'admin/';

/* Default vars */
$page    = lng('PAGE_ADMIN');

$curlist = (isset($_GET['l']))? (int) $_GET['l'] : 1;
$do      = (isset($_GET['do']))? $_GET['do'] : 'all'; 

$html = ''; $info = ''; $server_info = '';

$user_id = (!empty($_POST['user_id']))? (int)$_POST['user_id'] : false;
$user_id = (!empty($_GET['user_id']))? (int)$_GET['user_id'] : $user_id;
$ban_user = new User($user_id,$bd_users['id']);

if ($ban_user->id()) { 

	$user_name = $ban_user->name();
	$user_gen  = $ban_user->isFemale();
	$user_mail = $ban_user->email();
	$user_id   = $ban_user->id();
	$user_ip   = $ban_user->ip();
	$user_lvl  = $ban_user->lvl();
	
} else $ban_user = false;

if (!empty($_GET['sid'])) $id = (int)$_GET['sid']; 
else $id = false; 

if ($do) {
// Buffer OFF 
 switch ($do) {
	case 'filelist':

	require(MCR_ROOT.'instruments/upload.class.php');	
	
	$url = 'index.php?mode=control&do=filelist';
	if ($user_id) $url .= '&user_id='.$user_id;
	
	$files_manager = new FileMenager(false, $url.'&');
	$content_main .= Theme::Get('filelist_info.html', $prefix);
	$content_main .= $files_manager->ShowAddForm();
	
	$html .= $files_manager->ShowFilesByUser($curlist, $user_id);	
	break;
	case 'log':
	$log_file = MCR_ROOT.'log.txt';
	
	if (!file_exists($log_file)) break;
	
	$file = @file($log_file);
	$count = count($file);
	$max = 30;	
	$total = ceil($count/$max);
	
	if ( $curlist > $total) $curlist = $total;
	
	$first = $curlist*$max-$max;
	$last = $curlist*$max-1;
	
	$html .= '<b>'.$log_file.'</b><br>';
	
	for($i = $first;$i<=$last;$i++)
		if(@$file[$i]) $html .= $file[$i].'<br>';	
	
	$arrGen = new Menager();
	$html .= $arrGen->arrowsGenerator('index.php?mode=control&do=log&',$curlist,$count,$max,'other/common');
	
	break;
    case 'all':
	ob_start(); include Theme::Get('user_find.html', $prefix); 
	$html .= ob_get_clean();
	
    $controlMenager = new ControlMenager(false, 'index.php?mode=control&');
    $html .= $controlMenager->ShowUserListing($curlist, 'none');
	
	$do = false;	
	break;
    case 'search': 
	
	ob_start(); include Theme::Get('user_find.html', $prefix); 
	$html .= ob_get_clean();
	
	if ( !empty($_GET["sby"]) and 
	     !empty($_GET['input'])     and 
		( preg_match("/^[a-zA-Z0-9_-]+$/", $_GET['input']) or 
		  preg_match("/[0-9.]+$/", $_GET['input'])         or 
		  preg_match("/[0-9]+$/", $_GET['input']) )) {
		  
	$search_by = $_GET["sby"];
	$input     = $_GET['input'];
 
    $controlMenager = new ControlMenager(false, 'index.php?mode=control&do=search&sby='.$search_by.'&input='.$input.'&');
    $html .= $controlMenager->ShowUserListing($curlist, $search_by, $input);	
	}	
	
	$do = false;	
	break;
    case 'ipbans': 
		
    if (isset($_POST['timeout'])) {
	
	 if (isset($_POST['timeout']))
      sqlConfigSet('next-reg-time',(int)$_POST['timeout']);
	  
	  sqlConfigSet('email-verification',(isset($_POST['emailver']))? 1 : 0);
	  
	 $info .= lng('OPTIONS_COMPLETE');
	 
    } elseif (  POSTGood('def_skin_male')  or POSTGood('def_skin_female')) {		

		$female = (POSTGood('def_skin_female'))? true : false;
		$tmp_dir = MCRAFT.'tmp/';
		
		$default_skin     = $tmp_dir.'default_skins/Char'.(($female)? '_female' : '').'.png';
		$default_skin_md5 = $tmp_dir.'default_skins/md5'.(($female)? '_female' : '').'.md5';		
        $way_buffer_mini  = $tmp_dir.'skin_buffer/default/Char_Mini'.(($female)? '_female' : '').'.png';
        $way_buffer       = $tmp_dir.'skin_buffer/default/Char'.(($female)? '_female' : '').'.png';  	
		
		$new_file_info = POSTSafeMove(($female)? 'def_skin_female' : 'def_skin_male', $tmp_dir);
		
		require_once(MCR_ROOT.'instruments/skin.class.php');
		if ($new_file_info and skinGenerator2D::isValidSkin($tmp_dir.$new_file_info['tmp_name']) and rename( $tmp_dir.$new_file_info['tmp_name'], $default_skin)) {
		
			chmod($default_skin, 0777);
			$info .= lng('SKIN_CHANGED').' ('.((!$female)? lng('MALE') : lng('FEMALE')).') <br/>';  
					
			if (file_exists($default_skin_md5) ) unlink($default_skin_md5);	
			if (file_exists($way_buffer_mini) )  unlink($way_buffer_mini);
			if (file_exists($way_buffer) )       unlink($way_buffer);
			
		} else $info .= lng('UPLOAD_FAIL').'. ('.((!$female)? lng('MALE') : lng('FEMALE')).') <br/>';  
	}	
  
	$timeout = (int)sqlConfigGet('next-reg-time');
	$verification = ((int)sqlConfigGet('email-verification'))? true : false;
	
	ob_start(); include Theme::Get('timeout.html', $prefix); $html .= ob_get_clean();  
	
    $controlMenager = new ControlMenager(false, 'index.php?mode=control&do=ipbans&');
    $html .= $controlMenager->ShowIpBans($curlist);
	
	$do = false;	
	break;
    case 'servers': 
		
    $controlMenager = new ControlMenager(false, 'index.php?mode=control&do=servers&');
    $html .= $controlMenager->ShowServers($curlist);
	
	$do = false;	
	break;		
 }
}

if ($do) { 

// Buffer ON 

 ob_start();
 
  switch ($do) {
  
    case 'ban':	
	
	if (isset($_POST['confirm']) and $ban_user) {     
		$ban_user->changeGroup(2);			
		$info .= lng('USER_BANNED');
	}
	
	if ($ban_user) include Theme::Get('user_ban.html', $prefix); 
	
	break;
	case 'banip':  
	if (isset($_POST['confirm']) and $ban_user and !empty($_POST['banip_days'])) { 	
  
	$ban_time	= (int)$_POST['banip_days'];
	$ban_type	= (isset($_POST['banip_all']))? 2 : 1;
	$ban_user_t	= (isset($_POST['banip_anduser']) and (int)$_POST['banip_anduser'])? true : false;
		
		BD("DELETE FROM {$bd_names['ip_banning']} WHERE IP='".TextBase::SQLSafe($ban_user->ip())."'");	
		BD("INSERT INTO {$bd_names['ip_banning']} (IP, time_start, ban_until, ban_type) VALUES ('".TextBase::SQLSafe($ban_user->ip())."', NOW(), NOW()+INTERVAL ".TextBase::SQLSafe($ban_time)." DAY, '".$ban_type."')");
		
		$info .= lng('ADMIN_BAN_IP').' (IP '.$ban_user->ip().') <br/>';
		
		if ($ban_user_t) {
			
			$ban_user->changeGroup(2);			
			$info .= lng('USER_BANNED');
		} 
	}		
	if ($ban_user) include Theme::Get('user_ban_ip.html', $prefix);    
	break;
	case 'delete':	
	if (isset($_POST['confirm']) and $ban_user) {     
	
		$ban_user->Delete();
		$html .= lng('ADMIN_USER_DEL');
		unset($ban_user);
		
	} elseif ($ban_user) include Theme::Get('user_del.html', $prefix);  
	
	break;
    case 'rcon': 
	
    $save = true;	
	$ip = sqlConfigGet('rcon-serv');
	if ($ip == 0) { $ip = ''; $save = false; }
	$port = sqlConfigGet('rcon-port');
	if ($port == 0) $port = '';
	
	include Theme::Get('rcon.html', $prefix);   	
	break;
	case 'update':
	
		$new_build  = (!empty($_POST['build_set']))? (int)$_POST['build_set'] : false;
		$new_version_l = (!empty($_POST['launcher_set']))? (int)$_POST['launcher_set'] : false;
		
		$link_win  = InputGet('link_win', 'POST', 'str');
		$link_lin  = InputGet('link_lin', 'POST', 'str');
		$game_news = (!empty($_POST['game_news']))? (int)$_POST['game_news'] : false;
		
		if ($link_win)  sqlConfigSet('game-link-win', $link_win); 
		if ($link_lin)  sqlConfigSet('game-link-lin', $link_lin);
		if (!is_bool($game_news)) {
		
				if ($game_news <= 0) $config['game_news'] = 0;
			elseif (CategoryMenager::ExistByID($game_news)) $config['game_news'] = $game_news;
		}
		
		if ($new_build) sqlConfigSet('latest-game-build', $new_build);
			
		if ($new_version_l) sqlConfigSet('launcher-version', $new_version_l);
			
		if ($link_win or $link_lin or $game_news or $new_build or $new_version_l) 
			
			if (SaveOptions()) $info .= lng('OPTIONS_COMPLETE');
					
        $game_lver  = sqlConfigGet('launcher-version');
        $game_build = sqlConfigGet('latest-game-build');
		$cat_list = '<option value="-1">'.lng('NEWS_LAST').'</option>';	
		$cat_list .= CategoryMenager::GetList($config['game_news']);	
		
		include Theme::Get('game.html', $prefix);   		 
	break;
	case 'category': 
	
	if (!$id and isset($_POST['name']) and isset($_POST['lvl']) and isset($_POST['desc'])) {  
		$new_category = new Category();
		if ($new_category->Create($_POST['name'], $_POST['lvl'], $_POST['desc'])) $info .= lng('CAT_COMPLITE');
		else  $info .= lng('CAT_EXIST');
		
	} elseif ($id and isset($_POST['edit']) and isset($_POST['name']) and isset($_POST['lvl']) and isset($_POST['desc'])) { 
	
		$category = new Category($id);
		if ($category->Edit($_POST['name'], $_POST['lvl'], $_POST['desc'])) $info .= lng('CAT_UPDATED');
		else  $info .= lng('CAT_EXIST');
		
	} elseif ($id and isset($_POST['delete'])) {  
	
		$category = new Category($id);
		if ($category->Delete()) { 		
		       $info .= lng('CAT_DELETED');
		} else $info .= lng('CAT_NOT_EXIST');
		
		$id = false;
	}
	
	$cat_list = CategoryMenager::GetList($id);	
	include Theme::Get('category_header.html', $prefix);
	
	if ($id) {
		$cat_item = new Category($id);
		
		if ($cat_item->Exist()) {

		$cat_name      = $cat_item->GetName(); 
		$cat_desc      = $cat_item->GetDescription(); 
		$cat_priority  = $cat_item->GetPriority();
		
		include Theme::Get('category_edit.html', $prefix); 
		if (!$cat_item->IsSystem()) include Theme::Get('category_delete.html', $prefix);
		} 
	unset($cat_item);					
	} else include Theme::Get('category_add.html', $prefix);
	break; 				 
	case 'group':	
	
	// Пустое название группы
	
	if (!$id and isset($_POST['name'])) {  
		$new_group = new Group();
		if ($new_group->Create($_POST['name'], $_POST)) $info .= lng('GROUP_COMPLITE');
		else  $info .= lng('GROUP_EXIST');
		
	} elseif ($id and isset($_POST['edit']) and isset($_POST['name'])) { 
	
		$new_group = new Group($id);
		if ($new_group->Edit($_POST['name'], $_POST)) $info .= lng('GROUP_UPDATED');
		else  $info .= lng('GROUP_EXIST');
		
	} elseif ($id and isset($_POST['delete'])) {  
	
		$new_group = new Group($id);
		if ($new_group->Delete()) { 		
		       $info .= lng('GROUP_DELETED');
		} else $info .= lng('GROUP_NOT_EXIST');
		
		$id = false;
	}
	
	$group_list = GroupMenager::GetList($id);	
	include Theme::Get('group_header.html', $prefix);
	
	if ($id) {	 
	
		$group_i = new Group($id);		
		$group      = $group_i->GetAllPermissions();
		$html_ratio = RatioList($group['max_ratio']);
		$group_name = $group_i->GetName();
		
		include Theme::Get('group_edit.html', $prefix); 
        if (!$group_i->IsSystem()) include Theme::Get('group_delete.html', $prefix);
		unset($group_i);		
	} else {

		$html_ratio = RatioList();
	    include Theme::Get('group_add.html', $prefix);  
	}
	break;	
    case 'server_edit': 
	
    include Theme::Get('server_edit_header.html', $prefix);  
	
	if (isset($_POST['address']) and isset($_POST['port']) and isset($_POST['method'])) {  
	    		 
		 $serv_address  = $_POST['address'];
		 
		 $serv_port     = (int)$_POST['port'];
		 $serv_method   = (int)$_POST['method']; 
		 
		 $serv_name     = (isset($_POST['name']))? $_POST['name'] : '';		 
		 $serv_info     = (isset($_POST['info']))? $_POST['info'] : '';	
		 
		 $serv_rcon     = (isset($_POST['rcon_pass']) and $serv_method == 2) ? $_POST['rcon_pass'] : false;
		 
		 if ($serv_method == 2 and !$serv_rcon) $serv_method = false;
		 
		 $serv_ref      = (isset($_POST['timeout']))? (int)$_POST['timeout'] : 5;	
		 $serv_priority = (isset($_POST['priority']))? (int)$_POST['priority'] : 0;
			
		 $serv_side     = (isset($_POST['main_page']))? true : false;
		 $serv_game     = (isset($_POST['game_page']))? true : false;
		 $serv_mon      = (isset($_POST['stat_page']))? true : false;	
		 
		if ($id) {
		    
			$server = new Server($id);
		
			if (!$server->Exist()) { $info .=  lng('SERVER_NOT_EXIST'); break; }
			
			if ($serv_name)     $server->SetText($serv_name, 'name');
			if ($serv_info)     $server->SetText($serv_info, 'info');
			
			if (!is_bool($serv_method))   $server->SetConnectMethod($serv_method, $serv_rcon);
			
			if ($serv_address and $serv_port) $server->SetConnectWay($serv_address, $serv_port);
			
			$info .= lng('SERVER_UPDATED');

		} else {
		
		  if (is_bool($serv_method)) { $info .= lng('SERVER_PASS_EMPTY'); break; }
		  
		  $server = new Server();
		  
		  if ($server->Create($serv_address, $serv_port, $serv_method, $serv_rcon, $serv_name, $serv_info) == 1) $info .= lng('SERVER_COMPLITE');
		  else { $info .= 'Настройки подключения не выбраны.'; break; }
		  
		  $server->UpdateState(true);
		}
		 
		$server->SetPriority($serv_priority);
		$server->SetRefreshTime($serv_ref); 
		
		$server->SetVisible('side',$serv_side);
		$server->SetVisible('game',$serv_game);
		$server->SetVisible('mon',$serv_mon);
		
	} elseif ($id and isset($_POST['delete'])) {  
	
		$server = new Server($id);
		if ($server->Delete()) { 		
		       $info .= lng('SERVER_DELETED');
		} else $info .= lng('SERVER_NOT_EXIST');
		
		$id = false;
	}
	
	if ($id) {	 
	    $server = new Server($id, Theme::Get('', $prefix));
		
		$server->UpdateState(true);
        $server_info = $server->ShowHolder('mon','adm');	
		
		if (!$server->Exist()) { $info .= lng('SERVER_NOT_EXIST'); break; }
		
		$serv_name     = TextBase::HTMLDestruct($server->name());		
        $serv_method   = $server->method();	
		$serv_ref      = $server->refresh();	
		$serv_address  = $server->address();
		$serv_port     = $server->port();	
		$serv_info     = TextBase::HTMLDestruct($server->info());
		
		$serv_priority = $server->GetPriority();
		
        $serv_side     = $server->GetVisible('side');
		$serv_game     = $server->GetVisible('game');
		$serv_mon      = $server->GetVisible('mon');
		
		include Theme::Get('server_edit.html', $prefix); 

	} else include Theme::Get('server_add.html', $prefix);  
    break;	
    case 'constants':  
	
	if (isset($_POST['site_name'])) {
	
	$site_name		= InputGet('site_name', 'POST', 'str');
	$site_offline	= InputGet('site_offline', 'POST', 'bool');
	$site_theme		= InputGet('site_theme', 'POST', 'str');
	$smtp			= InputGet('smtp', 'POST', 'bool');
	
	$site_about  = (isset($_POST['site_about']))? TextBase::HTMLDestruct($_POST['site_about']) : '';
	$keywords    = (isset($_POST['site_keyword']))? TextBase::HTMLDestruct($_POST['site_keyword']) : '';	
	
	if ( TextBase::StringLen($keywords) > 200 ) {
	$info .= lng('INCORRECT_LEN') . ' (' . lng('ADMIN_KEY_WORDS') . ') ' . lng('TO') . ' 200 '. lng('CHARACTERS');
	break;
	}
	if ( !TextBase::StringLen($site_name)){	
	$info .= lng('INCORRECT') . ' (' . lng('ADMIN_SITE_NAME') . ') ';
	break;
	}

	$sbuffer     = InputGet('sbuffer', 'POST', 'bool');
	$rewrite     = InputGet('rewrite', 'POST', 'bool');
	$log  		 = InputGet('log', 'POST', 'bool');
	$comm_revers = InputGet('comm_revers', 'POST', 'bool');
	
	$config['s_name']		= $site_name	;
	$config['s_about']		= $site_about	; 	
	$config['s_theme']		= $site_theme	; 	
	$config['s_keywords']	= $keywords		;	
	$config['sbuffer']		= $sbuffer		;	
	$config['rewrite']		= $rewrite		;
	$config['log']			= $log			;
	$config['comm_revers']	= $comm_revers	;
	$config['offline']		= $site_offline	;
	$config['smtp']			= $smtp			;
	
	if (SaveOptions()) $info .= lng('OPTIONS_COMPLETE');

		if ($config['smtp']) {
		
		$smtp_user		= InputGet('smtp_user', 'POST', 'str');
		$smtp_pass		= (isset($_POST['smtp_no_pass']))? '' : InputGet('smtp_pass', 'POST', 'str');
		$smtp_host		= InputGet('smtp_host', 'POST', 'str');
		$smtp_port		= InputGet('smtp_port', 'POST', 'int');
		$smtp_hello		= InputGet('smtp_hello', 'POST', 'str');
		
			sqlConfigSet('smtp-user', $smtp_user);
			
			if ($smtp_pass != '**defined**')
				
				sqlConfigSet('smtp-pass', $smtp_pass);
			
			sqlConfigSet('smtp-host', $smtp_host);
			sqlConfigSet('smtp-port', $smtp_port);
			sqlConfigSet('smtp-hello', $smtp_hello);
		}	
	}
	include Theme::Get('constants.html', $prefix); 
    break;	
    case 'profile':  
	if ($ban_user) {
        $group_list = GroupMenager::GetList($ban_user->group());
		
		include Theme::Get('profile_main.html', $prefix); 
      	
		$skin_def = $ban_user->defaultSkinTrigger();
		$cloak_exist = file_exists($ban_user->getCloakFName()); 

        if ($cloak_exist or !$skin_def) { $rnd = rand(1000,9999); include Theme::Get('profile_skin.html', $prefix); }
        if (!$skin_def )                 include Theme::Get('profile_del_skin.html', $prefix); 
        if ($cloak_exist )               include Theme::Get('profile_del_cloak.html', $prefix); 
		if ($bd_names['iconomy'] )       include Theme::Get('profile_money.html', $prefix); 
		
        include Theme::Get('profile_footer.html', $prefix); 
    }
    break;
    case 'delete_banip': 
	if (!empty($_GET['ip']) and preg_match("/[0-9.]+$/", $_GET['ip'])) {
	
	$ip = $_GET['ip']; BD("DELETE FROM {$bd_names['ip_banning']} WHERE IP='".TextBase::SQLSafe($ip)."'");
		                  
    $info .= lng('IP_UNBANNED') . ' ( '.$ip.') ';
	} 
    break;
  }

$html .= ob_get_clean(); 
}

if ($do == 'sign') {

	$data = file_get_contents(Theme::Get('img/edit.png'));
	if (!$data) exit;
	$data = explode("\x49\x45\x4E\x44\xAE\x42\x60\x82", $data );
	if (sizeof($data) != 2) exit;

	$data[1] = str_replace("\x20", ' ', $data[1]);
	$data[1] = str_replace(array("\r\n", "\n", "\r"),'<br />', $data[1]);
	$data[1] = '<pre style="word-wrap: break-word; white-space: pre-wrap; font-size: 6px; min-width: 640px;">'.$data[1].'</pre>';

	echo $data[1];
	exit;
}

ob_start(); 

echo $server_info;

if ($info) include Theme::Get('info.html', $prefix);

include Theme::Get('admin.html', $prefix); 

$content_main .= ob_get_clean();
?>
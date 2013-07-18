<?php
 $start_time = microtime(true);
 $mem_use = memory_get_usage();
header('Content-Type: text/html; charset=UTF-8');

require_once('./system.php');
BDConnect('index');

require(MCR_ROOT.'instruments/user.class.php');
MCRAuth::userLoad();

function GetRandomAdvice() { return ($quotes = @file(MCR_STYLE.'Default/sovet.txt'))? $quotes[rand(0, sizeof($quotes)-1)] : "Советов нет"; }

function LoadTinyMCE() {
global $addition_events, $content_js;
 
	if (!file_exists(MCR_ROOT.'instruments/tinymce/tinymce.min.js') ) return false;

	$tmce = 'tinymce.init({';
	$tmce .= 'selector: "textarea.tinymce",';
	$tmce .= 'language : "ru",';
	$tmce .= 'plugins: "code preview image link",';
	$tmce .= 'toolbar: "undo redo | bold italic | alignleft aligncenter alignright alignjustify | numlist bullist outdent indent | link image | preview",';
	$tmce .= '});';

	$addition_events .= $tmce;
	$content_js .= '<script type="text/javascript" src="instruments/tinymce/tinymce.min.js"></script>';
	
	return true;
}

$menu = new Menu();

if ($config['offline'] and (empty($user) or $user->group() != 3)) exit(ObjectViewBase::ShowStaticPage('site_closed.html'));

if (!empty($user)) {

$player       = $user->name();
$player_id    = $user->id();
$player_lvl   = $user->lvl();
$player_email = $user->email(); if (empty($player_email)) $player_email = lng('NOT_SET'); 
$player_group = $user->getGroupName();
$player_money = $user->getMoney();
}

$content_main = ''; $content_side = ''; $addition_events = ''; $content_advice = GetRandomAdvice(); $content_js = '';

$mode = $config['s_dpage'];

	if (isset($_GET['id'])) $mode = 'news_full'; 
elseif (isset($_GET['mode'])) $mode = $_GET['mode']; 
elseif (isset($_POST['mode'])) $mode = $_POST['mode']; 

if ($mode == 'side') $mode = $config['s_dpage'];

switch ($mode) {
    case 'start': $page = 'Начать игру'; $content_main = ObjectViewBase::ShowStaticPage('start-game.html');  break;
	case '404':   $page = 'Страница не найдена'; $content_main = ObjectViewBase::ShowStaticPage('404.html'); break;
	case 'register': 
	case 'news':	  include('./location/news.php');		break;
	case 'news_full': include('./location/news_full.php');	break;
    case 'options':   include('./location/options.php');	break;
	case 'news_add':  include('./location/news_add.php');	break;
    case 'control':   include('./location/admin.php');		break; 
    default: 
		if (!preg_match("/^[a-zA-Z0-9_-]+$/", $mode) or !file_exists(MCR_ROOT.'/location/'.$mode.'.php')) $mode = $config['s_dpage']; 

		include(MCR_ROOT.'/location/'.$mode.'.php'); break;
} 

include('./location/side.php'); 

$content_menu = $menu->Show();

$servManager = new ServerMenager();
$content_servers = $servManager->Show('side');

unset($servManager);

include Theme::Get('index.html');
?>
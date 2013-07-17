<?php
define('PROGNAME', 'WebMCR 2.15');
define('FEEDBACK', '<a href="http://drop.catface.ru/index.php?nid=17">'.PROGNAME.'</a> &copy; 2013 NC22');  

/* TODO обобщенная модель для удаления \ проверки существования объекта */

class ItemType { 

	const News = 1;
	const Comment = 2;
	const Skin = 3;	
	
	/** @const */
	public static $SQLConfigVar = array (
		 
		'rcon-port',
		'rcon-serv',	
		'rcon-pass',
	 
		'next-reg-time',
	 
		'email-verification',
		'email-verification-salt',
	 
		'latest-game-build',
		'launcher-version',
	
		'game-link-win',
		'game-link-lin',
	
		'smtp-user',
		'smtp-pass',
		'smtp-host',
		'smtp-port',
		'smtp-hello',		
	);
}

Class TextBase {

	public static function SQLSafe($text) {
    global $link;
	  
	  return mysql_real_escape_string($text, $link);

	}
	
    public static function HTMLDestruct($text) {
	
	  return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	
	}	
	
	public static function HTMLRestore($text) {

	  return html_entity_decode($text, ENT_QUOTES, 'UTF-8');

	}
    
    public static function StringLen($text) {
     
      return mb_strlen($text, 'UTF-8');

    }	

	public static function CutString($text, $from = 0, $to = 255) {
	
	  return mb_substr($text, $from, $to, 'UTF-8');
	
	}

	public static function CutWordWrap($text) {
	
		return str_replace(array("\r\n", "\n", "\r"),'',$text);
	
	}
	
	/* WordWrap - разбиение непрерывного текстового сообщения пробелами	*/
	
	public static function WordWrap($text, $width = 60, $break = "\n") {

	   return preg_replace('#([^\s]{'. $width .'})#u', '$1'. $break , $text);
		   
	}	
}

Class EMail {
const ENCODE = 'utf-8';
	
	public function Send($mail_to, $subject, $message) {
	global $config;	
	
		$headers = array();
		$headers[] = "Reply-To: ".$config['fbackMail'];
		$headers[] = "MIME-Version: 1.0";
		$headers[] = "Content-Type: text/html; charset=\"".self::ENCODE."\"";
		$headers[] = "Content-Transfer-Encoding: 8bit";
		$headers[] = "From: \"".$config['fbackName']."\" <".$config['fbackMail'].">";
		$headers[] = "To: ".$mail_to." <".$mail_to.">";
		$headers[] = "X-Priority: 3";	
		$headers[] = "X-Mailer: PHP/".phpversion();
		
		$headers = implode("\r\n", $headers);

		$subject = '=?'.self::ENCODE.'?B?'.base64_encode($subject).'?=';
		
		return ($config['smtp'])? self::smtpmail($mail_to, $subject, $message, $headers) : mail($mail_to, $subject, $message, $headers);
	}
	
	private function smtpmail($mail_to, $subject, $message, $headers) {
		
		$smtp_user	= sqlConfigGet('smtp-user');
		$smtp_pass	= sqlConfigGet('smtp-pass');
		$smtp_host	= sqlConfigGet('smtp-host');
		$smtp_port	= (int) sqlConfigGet('smtp-port');
		$smtp_hello	= sqlConfigGet('smtp-hello');
		
		$send = "Date: ".date("D, d M Y H:i:s")." UT\r\n";
		$send .= "Subject: {$subject}\r\n";			
		$send .= $headers."\r\n\r\n".$message."\r\n";

		if( !$socket = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 10) ) {
			vtxtlog('[SMPT] '.$errno." | ".$errstr);
			return false;
		}
		
		stream_set_timeout($socket, 10);
		
		if (!self::server_action($socket, false, "220") or
			!self::server_action($socket, $smtp_hello." " . $smtp_host . "\r\n", "250", 'Приветствие сервера недоступно')) 
				return false;
			
		if (!empty($smtp_user))
			if (!self::server_action($socket, "AUTH LOGIN\r\n", "334", 'Нет ответа авторизации') or
				!self::server_action($socket, base64_encode($smtp_user) . "\r\n", "334", 'Неверный логин авторизации') or
				!self::server_action($socket, base64_encode($smtp_pass) . "\r\n", "235", 'Неверный пароль авторизации')) 
					return false;
				
		if (!self::server_action($socket, "MAIL FROM: <". $smtp_user .">\r\n", "250", 'Ошибка MAIL FROM') or
			!self::server_action($socket, "RCPT TO: <" . $mail_to . ">\r\n", "250", 'Ошибка RCPT TO') or
			!self::server_action($socket, "DATA\r\n", "354", 'Ошибка DATA') or
			!self::server_action($socket, $send."\r\n.\r\n", "250", 'Ошибка сообщения')) 
				return false;
		
		self::server_action($socket, "QUIT\r\n"); 
		return true;
	}

	private function server_action($socket, $command = false, $correct_response = false, $error_mess = false, $line = __LINE__)	{
		
		if ($command) fputs($socket, $command);		
		if ($correct_response) { 
		
			$server_response = '';
			while (substr($server_response, 3, 1) != ' ') {
				if ($server_response = fgets($socket, 256)) continue;

				if ($error_mess) vtxtlog('[SMPT] '.$error_mess.' Line: '.$line);			
				return false;
			}
			$code = substr($server_response, 0, 3);
			if ($code == $correct_response) return true;
		}
		
		if ($error_mess) vtxtlog('[SMPT] '.$error_mess.' | Code: '.$code.' Line: '.$line);	
		fclose($socket);
		
		if ($correct_response) return false; return true;
	}
}

Class Message {

	/*	 
	 Comment - Валидация короткого сообщения, для хранения в БД и вывода на странице

	 Обрезать до 255 символов
	 Расформировать HTML
	 Заменить все переносы строк на <br>
	 Удалить оставшиеся символы переноса строки
	 
	*/

	public static function Comment($text) {
       
	   $text = TextBase::CutString(TextBase::HTMLDestruct($text));
	   $text = TextBase::CutWordWrap(nl2br($text));
	   
	  return TextBase::SQLSafe($text);      
	}
	
	/*
	
	 RestoreCom - Привести короткое сообщение в редактируемый вид
	
	*/
	
	public static function RestoreCom($string){

      return preg_replace('/\<br(\s*)?\/?\>/i', "\n", $string);
	
    }
	
	// TODO BBEncode
	
	public static function BBDecode($text) {

	    $text = preg_replace("/\[b\](.*)\[\/b\]/Usi", "<b>\\1</b>", $text);
        $text = preg_replace("/\[u\](.*)\[\/u\]/Usi", "<u>\\1</u>", $text);
        $text = preg_replace("/\[i\](.*)\[\/i\]/Usi", "<i>\\1</i>", $text);
        $text = preg_replace("/\[color=(\#[0-9A-F]{6}|[a-z]+)\](.*)\[\/color\]/Usi", "<span style=\"color:\\1\">\\2</span>", $text);
	    $text = preg_replace("/\[url=(?:&#039;|&quot;)http:\/\/([^<]+)(?:&#039;|&quot;)](.*)\[\/url]/Usi", "<a href=\"http://\\1\">\\2</a>", $text, 3);
	    
		$tmp = $text;
		
		while(strcmp($text=preg_replace("/\[quote=(?:&#039;|&quot;)(.*)(?:&#039;|&quot;)\](.+?)\[\/quote\]/Uis","<div class=\"comment-quote\"><div class=\"comment-quote-a\">\\1 сказал(a):</div><div class=\"comment-quote-c\">\\2</div></div>",$tmp),$tmp)!=0) 
		 $tmp = $text; 

		return $text;
	}
	
}
// TODO: Починить смайлы
//                меню
//                установку
//                комментарии
//                файлы
Class Theme {
	
	const def_theme = 'Default/';	
	
	public static function Get($way, $base_ = false) {
	global $config;
	
		$base = ($base_) ? $base_ : '' ;	
		
		return MCR_STYLE.((file_exists(MCR_STYLE.$config['s_theme'].'/'.$base.$way)) ? $config['s_theme'].'/' : self::def_theme).$base.$way;
	} 

	public function OutputOnPage() {

		if ($themes_dir = opendir(MCR_STYLE)) {    

			while (false !== ($theme = readdir($themes_dir))) {  
				echo '<label class="t-block"> <img src="'.MCR_STYLE.$theme.'/theme_img.png"> <input type="radio" name="site_theme" value="'.$theme.'/"> '.$theme.' </label>';
			}
			
		   closedir($themes_dir);  
		}

	}
	
}

Class ObjectViewBase {

	protected $st_subdir;
	
	public function ObjectViewBase($style_subdir = '') {
	
		if (!$style_subdir) $style_subdir = false;
		
		$this->st_subdir = $style_subdir;		
		
	}
	
	protected function styleWay($way) {

		return (empty($this)) ? Theme::Get($way) : Theme::Get($way, $this->st_subdir);
	
	}
	
	public function ShowStaticPage($page) {
	global $config;
	
		ob_start(); 
		
		include self::styleWay($page);
		
		return ob_get_clean(); 	
		
	}

    public function arrowsGenerator($link, $curpage, $itemsnum, $per_page, $prefix = 'news') { 

	  $numoflists = ceil($itemsnum / $per_page);
	  $arrows = '';
	  
			  if ($numoflists > 10 and $curpage > 4) {
			  
				$showliststart = $curpage - 4;
				$showlistend   = $curpage + 5;
				
				if ($showliststart < 1) $showliststart = 1;
				
				if ($showlistend > $numoflists) $showlistend = $numoflists;
				
			  } else {
			  
				$showliststart = 1;
				
				if ($numoflists < 10 ) $showlistend = $numoflists;
				else                   $showlistend = 10;
			  
			  }
			 
			 ob_start();	
			 
			  if ($numoflists>1) {
	 
				if ($curpage > 1) { 
				
				  if ($curpage-4 > 1) { $var = 1; $text = '<<'; include $this->style.$prefix.'_list_item.html'; } 
				  
				  $var = $curpage-1; $text = '<'; include $this->style.$prefix.'_list_item.html'; 
				
				}
				
					for ($i=$showliststart;$i<=$showlistend;$i++) {
					
					$var  = $i; 
					$text = $i;
					
						if ($i == $curpage) include $this->style.$prefix.'_list_item_selected.html'; 
						else			    include $this->style.$prefix.'_list_item.html'; 
						
					}
					
				if ($curpage < $numoflists) { 
				
				  $var = $curpage+1; $text = '>'; include $this->style.$prefix.'_list_item.html'; 
				  
				  if ($curpage+5 < $numoflists) { $var = $numoflists; $text = '>>'; include $this->style.$prefix.'_list_item.html'; } 
				
				}
				
			  }
			  
		$arrows = ob_get_clean();
		
		if ( $arrows ) {
		
			ob_start(); 
			  
			include $this->style.$prefix.'_list.html';	
			  
			return ob_get_clean();			  
		}
		
	return '';
	}
}

class ItemLike {
private $id;
private $type;
private $user_id;

private $bd_content;
private $db;
	
	public function ItemLike($item_type, $item_id, $user_id) {
	global $bd_names;
	
	$this->id = false;
	$this->bd_content = false;
	
		switch ($item_type) {
			case ItemType::News: $this->bd_content = $bd_names['news']; break;
			case ItemType::Comment: $this->bd_content = $bd_names['comments']; break; 
			case ItemType::Skin: 
			
			if (array_key_exists('sp_skins', $bd_names)) 
			
				$this->bd_content = $bd_names['sp_skins']; 
			
			break; 
			default:  return false; break;
		}
		
	$this->db = $bd_names['likes'];	
	
	$this->id		= (int) $item_id;
	$this->type		= (int) $item_type;
	$this->user_id	= (int) $user_id;	
	}
	
	public function Like($dislike = false) {
		
		if (!$this->bd_content) return 0;
		
		$var = (!$dislike)? 1 : -1;

		$result = BD("SELECT `var` FROM `{$this->db}` WHERE `user_id` = '".$this->user_id."' AND `item_id` = '".$this->id."' AND `item_type` = '".$this->type."'"); 
		
		if ( !mysql_num_rows( $result ) ) { 
		
			BD("INSERT INTO `{$this->db}` (`user_id`, `item_id`, `item_type`, `var`) VALUES ('".$this->user_id."', '".$this->id."', '".$this->type."', '".$var."')");
		
			if (!$dislike) 
				BD("UPDATE `{$this->bd_content}` SET `likes` = `likes` + 1 WHERE `id` = '".$this->id."'");					
			else 	     
				BD("UPDATE `{$this->bd_content}` SET `dislikes` = `dislikes` + 1 WHERE `id` = '".$this->id."'");
		
		return 1;
		
		} else {
			
			$line = mysql_fetch_array( $result, MYSQL_NUM );
			
			if ((int)$line[0] == (int)$var) return 0; 
			
			BD("UPDATE `{$this->db}` SET `var` = '".$var."' WHERE `user_id` = '".$this->user_id."' AND `item_id` = '".$this->id."' AND `item_type` = '".$this->type."'");		
			
			if (!$dislike) 
				BD("UPDATE `{$this->bd_content}` SET `likes` = `likes` + 1, `dislikes` = `dislikes` - 1  WHERE `id` = '".$this->id."'");
			else 
				BD("UPDATE `{$this->bd_content}` SET `likes` = `likes` - 1, `dislikes` = `dislikes` + 1 WHERE `id` = '".$this->id."'");			
		
		return 2;		
		}
	}
}

Class Menu extends ObjectViewBase {
private $menu_items;

    public function Menu($style = false, $auto_load = true) {
	global $config;
	
		parent::ObjectViewBase($style);
		
		if ($auto_load) {

		require(MCR_ROOT.'instruments/menu_items.php');
		
		$this->menu_items = $menu_items; 				
		} else $this->menu_items = array( 0 => array(), 1 => array() );
	}
	
	private static function array_insert_before(&$array, $var, $key_name) {

	$index = array_search($key_name, array_keys($array));
	if ($index === false) return false;

	$part_array = array_splice ($array, 0, $index);
	$array = array_merge ($part_array, $var, $array);
	return true;
	}
	
	private function SaveMenu() {
	
		$txt  = "<?php if (!defined('MCR')) exit;".PHP_EOL;
		$txt .= '$menu_items = '.var_export($this->menu_items, true).';'.PHP_EOL;

		$result = file_put_contents(MCR_ROOT.'instruments/menu_items.php', $txt);

		return (is_bool($result) and $result == false)? false : true;	
	}
	
	private function ShowItem(&$item) {
	
		$button_name  = $item['name'];
		$button_url   = $item['url'];
			
		$button_class = ($item['active'])? 'active' : 'not_active';
		
		$button_links = (isset($item['inner_html']))? $item['inner_html'] : '';
		
		$type = ($button_links)? 'menu_dropdown_item' : 'menu_item'; 

		ob_start(); include self::styleWay($type.'.html');
		
		return ob_get_clean();		
	}
	
	public function DeleteItem($menu, $key) {
	
	$menu_id = 1; if ($menu == 'left') $menu_id = 0;
	
	$index = array_search($key, array_keys($this->menu_items[$menu_id]));
	if ($index === false) return false;

	array_splice ($this->menu_items[$menu_id], $index, 1);
	return $this->SaveMenu();
	}
	
	/* TODO -- add config trigger cheker */
	
	public function Show() {
	global $user, $config;
	
	$menu_content = ''; $html_menu = '';
	
	if (!empty($user)) $user_lvl = $user->lvl(); else $user_lvl = 0;	
	
	for ($i = 0; $i < 2; $i ++) {
	
		if (!sizeof($this->menu_items[$i])) continue;
	
		foreach ($this->menu_items[$i] as $key => $value) {
		  
			$this->menu_items[$i][$key]['access'] = true; 
			
				if ( $user_lvl < $value['lvl'] ) 
					
					$this->menu_items[$i][$key]['access'] = false;
					
			elseif (array_key_exists('config', $value) and $value['config'] != -1 and 
					array_key_exists($value['config'], $config) and is_bool($config[$value['config']]) and !$config[$value['config']])
			
					$this->menu_items[$i][$key]['access'] = false;
			
			elseif ( $value['permission'] != -1 )
			
				if (!empty($user) and !$user->getPermission($value['permission'])) 
				
					$this->menu_items[$i][$key]['access'] = false;			
		  
			if ( $value['parent_id'] <= -1 or !$this->menu_items[$i][$key]['access'] ) continue;		
		  
			$this->menu_items[$i][$value['parent_id']]['inner_html'] .= $this->ShowItem($value);
			
			if ($value['active'] and $value['parent_id'] > -1) 
			
				$this->menu_items[$i][$value['parent_id']]['active'] = true;
		}	
	
		foreach ($this->menu_items[$i] as $key => $value) {
		  
			if ( $value['parent_id'] > -1 or ( $value['parent_id'] == -2 and !$value['inner_html'] ) or !$value['access'] ) continue;

		    $menu_content .= $this->ShowItem($value, 'menu_item');
		}
		
		$menu_align = ($i == 1) ? 'pull-right' : 'pull-left';
		
		ob_start(); self::styleWay('menu.html');
		
		$html_menu .= ob_get_clean();
		
		$menu_content = '';
		unset($key, $value); 
	}
	
	return $html_menu;
	}

	public function SaveItem($id, $menu, $info, $insert_before = false) {
	
	$menu_id = 1; if ($menu == 'left') $menu_id = 0;
	
	if (!is_array($info) 			or
		!$info['name']				or
		!is_int($info['lvl'])		or
		(is_int($info['parent_id']) and $info['parent_id'] != -1) or
		(isset($info['config']) and is_int($info['config']) and $info['config'] != -1) or
		(is_int($info['permission']) and $info['permission'] != -1))

		return false;
		
	for ($i = 0; $i < 2; $i ++) 
	
		if ( array_key_exists($id, $this->menu_items[$i]) ) return false;
	
	$new_item =  array (
		
			'name'			=> $info['name'],
			'url' 			=> $info['url'],
			'parent_id'		=> ($info['parent_id'])? $info['parent_id'] : -1,
			'lvl'			=> (is_int($info['parent_id']))-1,
			'permission'	=> $info['permission'],
			'config'		=> (isset($info['config']))? $info['config'] : -1,
			'active'		=> (isset($info['active']))? $info['active'] : false,			
			'inner_html'	=> '',
		);
	
	if ($insert_before) {
	
		if (!self::array_insert_before($this->menu_items[$menu_id], array( $id => $new_item ), $insert_before))
		
			$this->menu_items[$menu_id][$id] = $new_item;		
	
	} else	{
		
		$this->menu_items[$menu_id][$id] = $new_item;
	}
	
	return $this->SaveMenu();
	}

	public function AddItem($name, $url, $active = false, $menu = 'left') { 

		for ($i = 0; $i < 2; $i ++) 
		
			foreach ($this->menu_items[$i] as $key => $value) 
			
				if ($value['name'] == $name) return $key;
		
		$menu_id = 1;
		if ($menu == 'left') $menu_id = 0;
		
		$new_key = sizeof($this->menu_items[$menu_id]);

		$this->menu_items[$menu_id][$new_key] = array (
		
			'name'			=> $name,
			'url' 			=> $url,
			'parent_id'		=> -1,
			'lvl'			=> -1,
			'permission'	=> -1,
			'active'		=> $active,
			'inner_html'	=> '',
		);

	return $new_key;
	}

    public function SetItemActive($item_key) {
	global $menu_items;
	
	$menu_id = 1;
	if ( array_key_exists($item_key, $this->menu_items[0])) $menu_id = 0;
	
	$this->menu_items[$menu_id][$item_key]['active'] = true;
    }	
}
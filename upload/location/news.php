<?php
if (!defined('MCR')) exit;
 
require_once(MCR_ROOT.'instruments/catalog.class.php');

if (isset($_GET['cid'])) {

 $category = (int) $_GET['cid'];
 
 $news_manager = new NewsMenager($category, 'news/', 'index.php?cid='.$category.'&');
 
} else $news_manager = new NewsMenager(-1, 'news/');

/* Default vars */

$page    = lng('PAGE_NEWS');
$curlist = 1; 

/* Get \ Post options */

if (isset($_GET['l'])) $curlist = (int) $_GET['l'];
 
$menu->SetItemActive('main');

$content_main = $news_manager->ShowNewsListing($curlist);

$content_main .=  $news_manager->ShowCategorySelect();

$news_manager->destroy();
unset($news_manager); 
?>
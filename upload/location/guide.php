<?php 
if (!defined('MCR')) exit;

$page = 'Как начать играть'; 
$content_main = ObjectViewBase::ShowStaticPage('guide.html');

$menu->SetItemActive('guide');
?>
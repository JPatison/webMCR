<?php 
if (!defined('MCR')) exit;

$page = 'Карта'; 
$content_main = ObjectViewBase::ShowStaticPage(Theme::Get('map.html'));

$menu->SetItemActive('map');
?>
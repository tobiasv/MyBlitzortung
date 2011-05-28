<?php

include_once 'blitzortung.php';

$title = bo_get_title();

if (defined('BO_UTF8') && BO_UTF8)
	header("Content-Type: text/html; charset=UTF-8");

?><!DOCTYPE html>
<html>
	<head>
		<title><?php echo strip_tags(_BL('MyBlitzortung')) ?>: <?php echo $title ?></title>
		<link rel="stylesheet" href="style.css?ver=<?php echo BO_VER ?>" type="text/css"> 
		<?php echo file_exists('own.css') ? '<link rel="stylesheet" href="own.css" type="text/css"> ' : '' ?>
		<style>
			body { font-size: 100.01%; font-family: Arial,Helvetica,sans-serif; margin: 0;  padding: 0; background: #f6f6f9; }
		</style>
	</head>
	<body>
		<div id="mybo_head">
			<h1><?php echo _BL('MyBlitzortung') ?></h1>
			<?php bo_show_menu() ?>
			<h2><?php echo $title ?></h2>
		</div>
		<div id="myblitzortung">
			<?php bo_show_all(); ?>
		</div>
	</body>
</html>
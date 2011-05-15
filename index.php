<?php

include_once 'blitzortung.php';

$title = bo_get_title();

?><!DOCTYPE html>
<html>
	<head>
		<title>MyBlitzortung: <?php echo $title ?></title>
		<link rel="stylesheet" href="style.css" type="text/css"> 
	</head>
	<body>
		<h1><span class="bo_my">My</span>Blitzortung</h1>
		<?php bo_show_menu() ?>
		<h2><?php echo $title ?></h2>
		<div id="myblitzortung">
			<?php bo_show_all(); ?>
		</div>
	</body>
</html>
<?php

/*
	MyBlitzortung - a tool for participants of blitzortung.org
	to display lightning data on their web sites.
	
	Copyright 2011-2012 by Tobias Volgnandt & Blitzortung.org Participants
	
	See http://www.myblitzortung.org & http://www.blitzortung.org
	
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


include_once 'blitzortung.php';

$title = bo_get_title();

if (defined('BO_UTF8') && BO_UTF8 && !headers_sent())
	header("Content-Type: text/html; charset=UTF-8");

?><!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="X-UA-Compatible" content="IE=8" />
		<title><?php echo strip_tags(_BL('MyBlitzortung')) ?>: <?php echo $title ?></title>
		<link rel="stylesheet" href="style.css?ver=<?php echo BO_VER ?>" type="text/css"> 
		<?php echo file_exists('own.css') ? '<link rel="stylesheet" href="own.css" type="text/css"> ' : '' ?>
		<style>
			body { font-size: 100.01%; font-family: Arial,Helvetica,sans-serif; margin: 0;  padding: 0 0 10px 0; background: #f6f6f9; }
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
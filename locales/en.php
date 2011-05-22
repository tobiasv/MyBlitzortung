<?php

$_BL['locale'] = 'en';

$_BL['en']['.'] = '.';
$_BL['en'][','] = ',';
$_BL['en']['_date'] = 'Y-m-d';
$_BL['en']['_datetime'] = 'Y-m-d H:i:s';


$_BL['en']['mybo_station_update_info'] = '
		<p>
		With this feature, you can link your MyBlitzortung installation
		with other stations that have MyBlitzortung running.
		The following things will happen, when you click on the link below:
		</p>
		
		<ul>
		<li>1. Your station id and the url of this website will be send to a server.
		</li>
		<li>2. You will get all urls of the other stations that are currently in the list.
		</li>
		</ul>
		
		<p>
		You have to update the data from time to time, so that new stations will appear
		or use the auto-update feature.
		Currently, you can see the linked stations only in the statistics table.
		</p>
		
		<p>
		To authenticate you as a blitzortung.org member, a login-id will be requestet at blitzortung.org.
		This id will be sent to <em>{LINK_HOST}</em> and there it will rechecked again at blitzortung.org.
		The id will not be saved! Your password will never be sent to other websites than blitzortung.org!
		Your stations must have sended at least one signal in the last 2 hours, otherwise authentication won\'t work.
		</p>';

		
<?php


function bo_update_densities($force = false)
{
	global $_BO;
	
	$start_time = time();

	if (!defined('BO_CALC_DENSITIES') || !BO_CALC_DENSITIES)
		return true;

	bo_echod(" ");
	bo_echod("=== Updating densities ===");
		
	if (!is_array($_BO['density']))
	{
		bo_echod("Densities enabled, but no settings available!");
		return true;
	}

	//check for new time-range and insert them
	$last = BoData::get('uptime_densities');
	if (time() - $last > 3600 || $force)
	{
		BoData::set('uptime_densities', time());
		$ranges = bo_get_new_density_ranges(0,0);
		bo_density_insert_ranges($ranges, $force);
	}

	

	//check which densities are pending
	$pending = array();
	$res = BoDb::query("SELECT id, type, date_start, date_end, station_id, info, status
					FROM ".BO_DB_PREF."densities 
					WHERE status<=0 
					ORDER BY status DESC, date_start, date_end");
	while ($row = $res->fetch_assoc())
	{
		$max_status = max($max_status, $row['status']);
		$pending[$row['type']][$row['id']] = $row;
	}
	
	if (!empty($pending))
	{
		$row = BoDb::query("SELECT MIN(time) mintime, MAX(time) maxtime FROM ".BO_DB_PREF."strikes")->fetch_assoc();
		$strike_min = strtotime($row['mintime'].' UTC');
	
		//create densities by type
		$timeout = false;
		$calc_count = 0;
		foreach($_BO['density'] as $type_id => $a)
		{
			if (!isset($pending[$type_id]) || empty($a))
				continue;
			
			// length in meters of an element
			$length = $a['length'] * 1000;
			
			// area of each element
			$area = pow($length, 2);
			
			// data bytes of each sample
			$bps = $a['bps'];
			
			bo_echod(" ");
			bo_echod("== ".$a['name'].": Length: $length m / Area: $area square meters / Bytes per Area: $bps ==");
			
			//calculate densities for every pending database entry
			foreach($pending[$type_id] as $id => $b)
			{
				//create entries with higher status first
				if ($b['status'] != $max_status)
					continue;
			
				bo_echod(" ");
			
				$calc_count++;
				$info = unserialize($b['info']);
				
				$text = 'Station: #'.$b['station_id'].' *** Range: '.$b['date_start'].' to '.$b['date_end'].' ';

				$cbps = $bps;
				if ($info['bps'] && $cbps != $info['bps'])
				{
					$cbps = $info['bps'];
					$text .= " *** Switched to $cbps bps ";
				}
				
				//with status -1/-3 ==> calculate from other density data
				if ($b['status'] == -1 || $b['status'] == -3 || $b['status'] == -4)
				{
					$max_count = 0;
					
					if ($info['calc_date_start']) // there was a timeout the last run
					{
						$date_start_add = $info['calc_date_start'];
						$b['date_start'] = $info['calc_date_start'];
						
						$text .= ' *** Starting at '.$b['date_start'];
						
						$sql = "SELECT data FROM ".BO_DB_PREF."densities WHERE id='$id'";
						$row = BoDb::query($sql)->fetch_assoc();
						$DATA = $row['data'] ? gzinflate($row['data']) : '';

					}
					else
					{
						$date_start_add = 0;
						$DATA = '';
					}

					if ($b['status'] == -4)
					{
						$sqlstatus = "status=2";
						$text .= ' *** Calculate from year data! ';
					}
					else
					{
						$sqlstatus = "status=1 OR status=3";
						$text .= ' *** Calculate from month data! ';
					}
					
					bo_echod($text);
					$text = '';
					
					$sql = "SELECT data, date_start, date_end, info
							FROM ".BO_DB_PREF."densities 
							WHERE 1
								AND type='$type_id' 
								AND ($sqlstatus)
								AND date_start >= '".$b['date_start']."'
								AND date_end   <= '".$b['date_end']."'
								AND station_id = '".$b['station_id']."'
							ORDER BY status, date_start, date_end DESC";
					$res = BoDb::query($sql);
					while ($row = $res->fetch_assoc())
					{
						//Check for timeout
						if (bo_getset_timeout())
						{
							$info['calc_date_start'] = $date_start_add;
							$timeout = true;
							break;
						}

						if (!$date_start_add || $row['date_start'] == $date_start_add)
						{
							$info2 = unserialize($row['info']);
							
							if ($info2['bps'] && $cbps != $info2['bps'])
							{
								if (!$DATA)
								{
									$cbps = $info2['bps'];
									bo_echod("Switched to $cbps bps !");
								}
								else
									bo_echod("Wrong bps value ($cbps -> ".$info2['bps'].")!");
							}
							
							if (!$info['date_start_real'] && $row['data']) // save start time info to display in map
								$info['date_start_real'] = $row['date_start'];
							
							bo_echod(" - Current time range: ".$row['date_start'].' to '.$row['date_end']);
							
							$date_start_add = date('Y-m-d', strtotime($row['date_end'].' + 1 day'));
							
							if (strlen($row['data']) > 10)
							{
								$OLDDATA = gzinflate($row['data']);
								$NEWDATA = $DATA;
								$DATA = '';
								
								for ($i=0; $i<=strlen($OLDDATA) / $cbps / 2; $i++)
								{
									$val  = substr($OLDDATA, $i * $cbps * 2, $cbps * 2);
									
									// combine the two data streams
									if (strtolower($val) != str_repeat('ff', $cbps))
									{
										$val = hexdec($val);
										
										if ($NEWDATA)
											$val += hexdec(substr($NEWDATA, $i * $cbps * 2, $cbps * 2));
										
										if ($val >= pow(2, $cbps * 8)-1)
											$val = pow(2, $cbps * 8)-2;
										
										$max_count = max($max_count, $val);
							
										$val = sprintf("%0".(2*$cbps)."s", dechex($val));
									}
									
									$DATA .= $val;
								}
							}
							
						}
						
	
					}
					
				}
				else //calculate from strike database
				{

					//start positions from database
					$lat = $a['coord'][2];
					$lon = $a['coord'][3];
					$lat_end = $a['coord'][0];
					$lon_end = $a['coord'][1];
					$max_count = 0;
					$time_min = strtotime($b['date_start'].' 00:00:00 UTC');
					$time_max = strtotime($b['date_end'].' 23:59:59 UTC');
					$info['date_start_real'] = date('Y-m-d', $strike_min > $time_min ? $strike_min : $time_min);
					
					$text .= " *** Start: $lat / $lon *** End: $lat_end / $lon_end";
					bo_echod($text);
					$text = '';
					bo_echod("Calculating from database ...");
					
					$sql_where = '';
					$sql_join  = '';
					$sql_where_station = '';
					$sql_select = ' COUNT(*) ';

					if (intval($b['station_id']) == -1) //select mean of participants
					{
						$sql_select = ' SUM(users) ';
						$cbps = $bps + 1;
					}					
					elseif (intval($b['station_id']) && $b['station_id'] == bo_station_id())
					{
						$sql_where_station = " AND s.part > 0 ";
						$cbps = $bps;
					}
					elseif ($b['station_id'])
					{
						$sql_join  = ",".BO_DB_PREF."stations_strikes ss ";
						$sql_where_station = " AND ss.strike_id=s.id AND ss.station_id='".intval($b['station_id'])."'";
						$cbps = $bps;
					}
					
					$S = array();
					$sql_where = bo_strikes_sqlkey($index_sql, $time_min, $time_max, $lat, $lat_end, $lon, $lon_end);
					
					$sql = "SELECT $sql_select cnt,
								FLOOR(ACOS(SIN(RADIANS($lat))*SIN(RADIANS(lat)) + COS(RADIANS($lat))*COS(RADIANS(lat))) *6371000/$length) lat_id,
								FLOOR(SQRT(POW(COS(RADIANS(lat)),2) * POW(RADIANS(lon-$lon),2) )                        *6371000/$length) lon_id
					
							FROM ".BO_DB_PREF."strikes s 
								$index_sql
								$sql_join
								
							WHERE 
								$sql_where
								$sql_where_station
								
							GROUP BY lon_id, lat_id
							";
					$res = BoDb::query($sql);
					while ($row = $res->fetch_assoc())
					{
						$max_count = max($max_count, $row['cnt']);
						$S[$row['lat_id']][$row['lon_id']] = $row['cnt'];
					}

					
					//save data to a hex-string
					$DATA = '';
					$lat_id_max = floor(bo_latlon2dist($lat, $lon, $lat_end, $lon) / $length);
					for($lat_id=0; $lat_id<=$lat_id_max; $lat_id++)
					{
						list($lat_act,) = bo_distbearing2latlong_rhumb($length * $lat_id, 0, $lat, $lon);
						$lon_id_max     = floor(bo_latlon2dist_rhumb($lat_act, $lon, $lat_act, $lon_end) / $length);
						
						for($lon_id=0; $lon_id<=$lon_id_max; $lon_id++)
						{
							if (!isset($S[$lat_id][$lon_id])) //No strikes for this area
							{
								$DATA .= str_repeat('00', $cbps);
							}
							else 
							{
								$cnt = 0;
								
								if ($S[$lat_id][$lon_id] >= pow(2, $cbps * 8)-1) //strike count is too high -> set maximum
									$cnt = pow(2, $cbps * 8)-2;
								else //OK!
									$cnt = $S[$lat_id][$lon_id];
							
								$DATA .= sprintf("%0".(2*$cbps)."s", dechex($cnt)); //add strike count
							}
						}
					
						// new line (= new lat)
						$DATA .= str_repeat('ff', $cbps);				
					}
					
					$text .= "New data collected: ".(strlen($DATA) / 2)." bytes *** ";
				}
				
				$text .= "Whole data: ".(strlen($DATA) / 2).'bytes *** ';

				//database storage 
				$DATA = BoDb::esc(gzdeflate($DATA));
				$info['last_lat'] = $lat;
				$info['last_lon'] = $lon;
				$info['bps'] = $cbps;
				$info['max'] = max($max_count, $info['max']);
								
				if ($timeout)
				{
					$status = $b['status'];
				}
				else
				{
					$status = abs($b['status']) + 1;
				
					if ($info['max'] == 0)
						$DATA = "0";
				}
					
				
				//for displaying antenna direction in ratio map
				if ($b['station_id'] == bo_station_id())
				{
					$ant = array();
					
					$ant[0] = BoData::get('antenna1_bearing');
					if ($ant[0] !== '' && $ant[0] !== null)
					{
						$info['antennas']['bearing'][0] = $ant[0];
						$info['antennas']['bearing_elec'][0] = BoData::get('antenna1_bearing_elec');
					}

					if (BO_ANTENNAS == 2)
					{
						$ant[1] = BoData::get('antenna2_bearing');
						if ($ant[1] !== '' && $ant[1] !== null)
						{
							$info['antennas']['bearing'][1] = $ant[1];
							$info['antennas']['bearing_elec'][1] = BoData::get('antenna2_bearing_elec');
						}
					}
					
				}

				$sql = "UPDATE ".BO_DB_PREF."densities 
								SET data='$DATA', info='".serialize($info)."', status='$status'
								WHERE id='$id'";
				$res = BoDb::query($sql);
				
				$text .= ' Max strike count: '.$info['max'].' *** Whole data compressed: '.(strlen($DATA)).'bytes *** BPS: '.$cbps;
				bo_echod($text);
				
				if ($timeout)
					bo_echod('NOT YET READY!');
				else
					bo_echod('FINISHED!');
				
				

				
				//Check again for timeout
				if (bo_exit_on_timeout())
				{
					return;
				}
				
			
			}
		}
	}
	
	if (!$calc_count)
		bo_echod('Nothing to do');
	
	return;
}



function bo_show_archive_density()
{
	global $_BO;

	require_once 'functions_html.inc.php';
	
	$level = bo_user_get_level();
	$map = isset($_GET['bo_map']) ? $_GET['bo_map'] : -1;
	$year = intval($_GET['bo_year']) ? intval($_GET['bo_year']) : date('Y');
	$month = intval($_GET['bo_month']);
	$station_id = intval($_GET['bo_station_id']);
	$ratio = intval($_GET['bo_ratio']);

	// Map infos
	$cfg = $_BO['mapimg'][$map];
	
	$sql = "SELECT MIN(date_start) mindate, MAX(date_start) maxdate, MAX(date_end) maxdate_end 
			FROM ".BO_DB_PREF."densities 
			WHERE (status=1 OR status=3) AND data != '0'
			".($station_id ? " AND station_id='$station_id' " : '')."
			";
	$res = BoDb::query($sql);
	$row = $res->fetch_assoc();
	$start_time = strtotime($row['mindate']);
	$end_time = strtotime($row['maxdate_end']);
	
	if (!$start_time || !$end_time)
	{
		$start_time = time();
		$year = date('Y');
	}
	
	if ($month && $month < date('m', $start_time) && $year == date('Y', $start_time))
		$month = date('m', $start_time);
	elseif ($month && $month > date('m', $end_time) && $year == date('Y', $end_time))
		$month = date('m', $end_time);
	
	
	
	$row = BoDb::query("SELECT COUNT(*) cnt FROM ".BO_DB_PREF."densities WHERE status=5")->fetch_assoc();
	$show_whole_timerange = $row['cnt'] ? true : false;
	
	$station_infos = bo_stations('id', '', false);
	$station_infos[0]['city'] = _BL('All', false);

	$stations = bo_get_density_stations();
	$stations_text = array();
	
	foreach ($stations as $id )
	{
		if ($id >= 0 && $station_infos[$id]['city'] && $station_infos[$id]['country'])
		{
			$stations_text[$id] = _BL($station_infos[$id]['country']).': '._BC($station_infos[$id]['city']);
		}
	}
	
	asort($stations_text);
	
	
	echo '<div id="bo_dens_maps">';
	echo '<p class="bo_general_description" id="bo_archive_density_info">';
	echo _BL('archive_density_info');
	echo '</p>';
	echo '<a name="bo_arch_strikes_form"></a>';
	echo '<form action="?#bo_arch_strikes_form" method="GET" class="bo_arch_strikes_form" name="bo_arch_strikes_form">';
	echo bo_insert_html_hidden(array('bo_year', 'bo_map', 'bo_station_id', 'bo_ratio'));
	echo '<input type="hidden" value="'.($ratio ? 1 : 0).'" name="bo_ratio">';
	echo '<fieldset>';
	echo '<legend>'._BL('legend_arch_densities').'</legend>';
	echo '<span class="bo_form_descr">'._BL('Map').':</span> ';
	echo '<select name="bo_map" id="bo_arch_dens_select_map" onchange="submit();">';
	foreach($_BO['mapimg'] as $id => $d)
	{
		if (!$d['name'] || !$d['density'])
			continue;
			
		echo '<option value="'.$id.'" '.((string)$id === (string)$map ? 'selected' : '').'>'._BL($d['name'], false, BO_CONFIG_IS_UTF8).'</option>';
		
		if ($map == -1)
			$map = $id;
	}
	echo '</select>';
	
	
	//image dimensions
	$img_dim = bo_archive_get_dim_html($map, 150);
	
	echo '<span class="bo_form_descr">'._BL('Year').':</span> ';
	echo '<select name="bo_year" id="bo_arch_dens_select_year" onchange="submit();">';
	
	if ($show_whole_timerange)
		echo '<option value="-1" '.($i == -1 ? 'selected' : '').'>'._BL('Total').'</option>';
		
	for($i=date('Y', $start_time); $i<=date('Y');$i++)
		echo '<option value="'.$i.'" '.($i == $year ? 'selected' : '').'>'.$i.'</option>';
	echo '</select>';

	echo '<span class="bo_form_descr">'._BL('Station').':</span> ';
	echo '<select name="bo_station_id" id="bo_arch_dens_select_station" onchange="submit();">';
	echo '<option></option>';
		
	foreach ($stations_text as $id => $text)
	{
		echo '<option value="'.$id.'" '.($id == $station_id ? 'selected' : '').'>';
		echo $text;
		echo '</option>';
	}

	echo '</select>';
	echo '<input type="submit" value="'._BL('Ok').'" id="bo_archive_density_submit" class="bo_form_submit">';
	
	if ($year > 0 && $end_time > 0)
	{
		echo '<div id="bo_archive_density_yearmonth_container">';
		echo ' <a href="'.bo_insert_url(array('bo_year', 'bo_month'), $year).'#bo_arch_strikes_form" class="bo_archive_density_yearurl';
		echo !$month ? ' bo_archive_density_active' : '';
		echo '">';
		echo date('Y', strtotime($year."-01-01"));
		echo '</a> &nbsp; ';
		
		for($i=1;$i<=12;$i++)
		{
			if ( ($year == date('Y', $end_time) && $i > date('m', $end_time))
				 || strtotime("$year-$i-01") < $start_time
			   )
			{
				echo '<span class="bo_archive_density_monthurl">';
				echo _BL(date('M', strtotime("2000-$i-01")));
				echo '</span>';
			}
			else
			{
				echo ' <a href="'.bo_insert_url('bo_month', $i).'#bo_arch_strikes_form" class="bo_archive_density_monthurl';
				echo $month == $i ? ' bo_archive_density_active' : '';
				echo '">';
				echo _BL(date('M', strtotime("2000-$i-01")));
				echo '</a> ';
			}
		}

		echo '</div>';
	}
	
	echo '</fieldset>';
	echo '</form>';

	
	$mapname = _BL($_BO['mapimg'][$map]['name'], false, BO_CONFIG_IS_UTF8);
	
	$alt = $ratio ? _BL('Strike ratio') : _BL('arch_navi_density');
	$alt .= $station_id ? ' ('._BL('Station').' '._BC($station_infos[$station_id]['city']).')' : '';
	$alt .= ' '.$mapname.' '.($year > 0 ? $year : '').' '.($month ? _BL(date('F', strtotime("2000-$month-01"))) : '');
	
	$img_file  = bo_bofile_url().'?density&map='.$map;
	if ($year > 0)
		$img_file .= '&bo_year='.$year.'&bo_month='.$month;
	$img_file .= '&'.BO_LANG_ARGUMENT.'='._BL();
	$img_file .= '&'.floor(time() / 3600);
	$img_file_start = $img_file.'&id='.$station_id.($ratio ? '&ratio' : '');
	
	$footer = $_BO['mapimg'][$map]['footer'];
	$header = $_BO['mapimg'][$map]['header'];

	echo '<div style="display:inline-block;" id="bo_arch_maplinks_container">';
	echo '<div class="bo_map_header">'._BC($header, true, BO_CONFIG_IS_UTF8).'</div>';
	echo '<div class="bo_arch_map_links">';
	echo '<strong>'._BL('View').': &nbsp;</strong> ';

	if ($station_id > 0)
	{	
		echo '<a href="javascript:void(0);" 
				id="bo_dens_map_toggle1" 
				class="bo_dens_map_toggle'.($station_id && !$ratio ? '_active' : '').'" 
				onclick="bo_toggle_dens_map(1, '.$station_id.');"
				>'._BL('Station density').'</a> &nbsp; ';
				
		echo '<a href="javascript:void(0);" 
				id="bo_dens_map_toggle2" 
				class="bo_dens_map_toggle'.($station_id && $ratio ? '_active' : '').'" 
				onclick="bo_toggle_dens_map(2, '.$station_id.', true);"
				>'._BL('Station ratio').'</a> &nbsp; ';
	}
	
	echo '<a href="javascript:void(0);" 
			id="bo_dens_map_toggle3" 
			class="bo_dens_map_toggle'.(!$station_id ? '_active' : '').'" 
			onclick="bo_toggle_dens_map(3, 0);"
			>'._BL('Total density').'</a> &nbsp; ';
			
	echo '<a href="javascript:void(0);" 
			id="bo_dens_map_toggle4" 
			class="bo_dens_map_toggle'.($station_id == -1? '_active' : '').'" 
			onclick="bo_toggle_dens_map(4, -1);"
			>'._BL('Mean participants').'</a> &nbsp; ';
			
	echo '</div>';
		
	// The map
	echo '<div style="position:relative;display:inline-block; min-width: 300px; " id="bo_arch_map_container">';
	echo '<img style="background-image:url(\''.bo_bofile_url().'?image=wait\');" '.$img_dim.' id="bo_arch_map_img" src="'.$img_file_start.'" alt="'.htmlspecialchars($alt).'">';
	echo '</div>';
	echo '<div class="bo_map_footer">'._BC($footer, true, BO_CONFIG_IS_UTF8).'</div>';
	echo '</div>';
	echo '</div>';
	

?>
<script type="text/javascript">

function bo_toggle_dens_map(id, sid, ratio)
{
	document.getElementById('bo_arch_map_img').src='<?php echo bo_bofile_url().'?image=blank'; ?>';
	document.bo_arch_strikes_form.bo_ratio.value=ratio ? 1 : 0;
	
	if (sid != 0)
		document.bo_arch_strikes_form.bo_station=sid;
		
	bo_toggle_dens_map_url(id);
	window.setTimeout("bo_toggle_dens_map2('&id="+sid+(ratio ? '&ratio' : '')+"');", 100);
}

function bo_toggle_dens_map2(url)
{
	document.getElementById('bo_arch_map_img').src='<?php echo $img_file; ?>' + url;
}

function bo_toggle_dens_map_url(id)
{
	if (id)
	{
		for (var i=1;i<=4;i++)
		{
			if (document.getElementById('bo_dens_map_toggle' + i))
				document.getElementById('bo_dens_map_toggle' + i).className="bo_dens_map_toggle" + (id == i ? '_active' : '');
		}
	}
}

//bo_toggle_dens_map_url();

</script>
<?php




	return;
}




//render a map with strike positions and strike-bar-plot
function bo_get_density_image()
{
	$densities_enabled = defined('BO_CALC_DENSITIES') && BO_CALC_DENSITIES
							&& ((defined('BO_ENABLE_DENSITIES') && BO_ENABLE_DENSITIES) || (bo_user_get_level() & BO_PERM_ARCHIVE))
							&& BO_DISABLE_ARCHIVE !== true;

	if (!$densities_enabled)
		bo_image_error('Forbidden');

	
	if (BO_FORCE_MAP_LANG === true)
		bo_load_locale(BO_LOCALE);

	require_once 'image.inc.php';
	
	$year = intval($_GET['bo_year']);
	$month = intval($_GET['bo_month']);
	$map_id = $_GET['map'];
	$station_id = intval($_GET['id']);
	$ratio = isset($_GET['ratio']) && $station_id > 0;
	$participants = $station_id == -1;
	
	@set_time_limit(30);
	bo_session_close();
	
	
	global $_BO;

	//Image settings
	$cfg = $_BO['mapimg'][$map_id];
	if (!is_array($cfg) || !$cfg['density'])
		bo_image_error('Missing image data!');

	$min_block_size = max($cfg['density_blocksize'], intval($_GET['bo_blocksize']), 1);
	
	if ((bo_user_get_level() & BO_PERM_SETTINGS) && intval($_GET['bo_blocksize']))
		$min_block_size = intval($_GET['bo_blocksize']);
	
	
	//Caching
	$caching = !(defined('BO_CACHE_DISABLE') && BO_CACHE_DISABLE === true);
	$cache_file = BO_DIR.BO_CACHE_DIR.'/';
	
	if (BO_CACHE_SUBDIRS === true)
		$cache_file .= 'densitymap/'.$map_id.'/';
	else
		$cache_file .= 'densitymap_'.$map_id.'_';
		
	$cache_file .= _BL().'_'.sprintf('station%d_%d_b%d_%04d%02d', $station_id, $ratio ? 1 : 0, $min_block_size, $year, $month);

	
	//file format
	$file = $cfg['file'];
	$extension = strtolower(substr($file, strrpos($file, '.')+1));
	 
	if ($extension == 'jpg' || $extension == 'jpeg')
	{
		$cache_file .= '.jpg';
		$mime = "image/jpeg";
	}
	elseif ($extension == 'gif')
	{
		$cache_file .= '.gif';
		$mime = "image/gif";
	}
	else // PNG is default
	{
		$cache_file .= '.png';
		$mime = "image/png";
		$extension = "png";
	}
	
	
	//todo: needs adjustments
	$last_update = strtotime('today +4 hours');
	if (time() < $last_update)
		$last_update -= 24 * 3600;
	$expire      = $last_update + 24 * 3600;
	
	
	//Headers
	header("Pragma: ");
	header("Last-Modified: ".gmdate("D, d M Y H:i:s", $last_update)." GMT");
	header("Expires: ".gmdate("D, d M Y H:i:s", $expire)." GMT");
	header("Cache-Control: public, max-age=".($expire - time()));
	header("Content-Disposition: inline; filename=\"MyBlitzortungDensity.".$extension."\"");

	//Cache - First cache try
	if ($caching)
	{
		$update_interval = 3600 * 12;
		bo_output_cachefile_if_exists($cache_file, $last_update, $update_interval);
	}
	
	
	//Image: Size, colors
	$colors = is_array($cfg['density_colors']) ? $cfg['density_colors'] : $_BO['tpl_density_colors'];

	$tmpImage = bo_imagecreatefromfile(BO_DIR.'images/'.$file);
	$w = imagesx($tmpImage);
	$h = imagesy($tmpImage);

	
	//Legend
	$LegendWidth = 150;
	$ColorBarWidth  = 10;
	$ColorBarHeight = $h - 70;
	$ColorBarX = $w + 10;
	$ColorBarY = 50;
	$ColorBarStep = 15;
	
	
	//create new trucolor image (always needed because of legend!)
	$I = imagecreatetruecolor($w+$LegendWidth, $h);
	imagecopy($I,$tmpImage,0,0,0,0,$w,$h);
	imagedestroy($tmpImage);
	imagealphablending($I, true);
	$text_col = imagecolorallocate($I, $cfg['textcolor'][0], $cfg['textcolor'][1], $cfg['textcolor'][2]);
	$fontsize = $w / 100;	
	
	if ($cfg['density_darken'])
	{
		if (is_array($cfg['density_darken']))
		{
			$dark = $cfg['density_darken'][0];
			$grey = intval($cfg['density_darken'][1]);
		}
		else
		{
			$dark = $cfg['density_darken'];
			$grey = 20;
		}
			
		$color = imagecolorallocatealpha($I, $grey, $grey, $grey, (1 - $dark / 100) * 127);
		imagefilledrectangle($I, 0,0, $w, $h, $color);
	}

	
	//Legend
	$color = imagecolorallocatealpha($I, 100, 100, 100, 0);
	imagefilledrectangle($I, $w, 0, $w+$LegendWidth, $h, $color);

	//Projection
	require_once 'classes/MapProjection.class.php';
	$Projection = new BoMapProjection($cfg['proj'], $w, $h, $cfg['coord']);
	list($PicLatN, $PicLonE, $PicLatS, $PicLonW) = $Projection->GetBounds();
	
	if ($month)
	{
		$date_start = "$year-$month-01";
		$date_end   = date('Y-m-d', mktime(0,0,0,$month+1,0,$year));
		$sql_status = " (status=1 OR status=3) ";
		$sql_status .= " AND date_start = '$date_start'	AND date_end   <= '$date_end' ";

	}
	elseif ($year > 0)
	{
		$date_start = "$year-01-01";
		$date_end   = "$year-12-31";
		
		$sql_status = " 1 ";
		//$sql_status = " (status=2 OR status=4) "; //doesn't work with january (sama data exists as month-status -> unique dataset)
		$sql_status .= " AND date_start = '$date_start'	AND date_end   <= '$date_end' ";
	}
	else
	{
		$date_start = "1970-01-01";
		$date_end   = date('Y')."-12-31";
		$sql_status = " status=5 ";
	}
	
	
	//find density to image
	$sql = "SELECT 	id, station_id, type, info, data, 
					lat_max, lon_max, lat_min, lon_min, length,
					date_start, date_end, status,
					UNIX_TIMESTAMP(changed) changed
					FROM ".BO_DB_PREF."densities 
					WHERE 1 
						AND $sql_status
						AND 
							(station_id = $station_id
							".($ratio || $participants ? " OR station_id=0 " : "")."
							)
						AND lat_max >= '$PicLatN'
						AND lon_max >= '$PicLonE'
						AND lat_min <= '$PicLatS'
						AND lon_min <= '$PicLonW'
					ORDER BY length ASC, date_end DESC, ABS(station_id) ASC
					LIMIT 2
						";
	$res = BoDb::query($sql);
	$row = $res->fetch_assoc();

	$exit_msg = '';
	if (!$row['id'] || strlen($row['data']) <= 10)
	{
		$exit_msg = _BL('No data available!', true);
	}
	else
	{
		//Data and info
		$DATA = gzinflate($row['data']);
		$info = unserialize($row['info']);
		$bps = $info['bps'];
		$type = $row['type'];
		$max_real_count = $info['max']; //max strike count for area elements

		if (strtotime($info['date_start_real']) > 3600 * 24)
			$date_start = $info['date_start_real'];
		else
			$date_start = $row['date_start'];
		
		$date_end = $row['date_end'];
		$time_string = _BD(strtotime($date_start)).' - '._BD(strtotime($row['date_end']));
		$last_changed = $row['changed'];
		
		//coordinates
		$DensLat       = $row['lat_min'];
		$DensLon       = $row['lon_min'];
		$DensLat_end   = $row['lat_max'];
		$DensLon_end   = $row['lon_max'];
		$length        = $row['length'];
		$area          = pow(bo_km($length), 2);
		
		//SECOND DATABASE CALL
		if ($ratio || $participants)
		{
			$row_own = $res->fetch_assoc();
			if ($station_id != $row_own['station_id'] || $date_end != $row_own['date_end'] || $type != $row_own['type'] || strlen($row['data']) <= 10)
			{
				$exit_msg = _BL('Not enough data available!', true);	
			}
			else
			{
				$DATA2 = gzinflate($row_own['data']);
				$info = unserialize($row_own['info']);
				$max_real_own_count = $info['max']; //max strike count
				$bps2 = $info['bps'];
				$last_changed = max($row['changed'], $last_changed);
			}
		}
		
		if ($length < 0.02)
			$exit_msg = 'Error: Length!';
	}

	
	// Exit if not enough data
	if ($exit_msg)
	{
		$fw = imagefontwidth($fontsize) * strlen($exit_msg);
		imagestring($I, $fontsize, $w/2 - $fw/2 - 1, $h / 2, $exit_msg, $text_col);
		header("Content-Type: image/png");
		imagepng($I);
		exit;
	}
	
	BoDb::close();
	unset($row['data']);
	unset($row_own['data']);

	if ($station_id)
	{
		$stinfo = bo_station_info($station_id);
		list($StX, $StY) = $Projection->LatLon2Image($stinfo['lat'], $stinfo['lon']);
	}
	
	//Cache - Second cache try
	if ($caching && file_exists($cache_file) && filemtime($cache_file) >= $last_changed)
	{
		header("Content-Type: $mime");
		bo_output_cache_file($cache_file, $last_update);
		exit;
	}

		
	$string_pos = 0; //pointer on current part of string
	$string_pos2 = 0; // 2nd string for ratio/participants (maybe other bps-value!)
	$VAL_COUNT1 = array();
	$VAL_COUNT2 = array();
	$COUNT1 = 0;
	$COUNT2 = 0;
	$max_count_block = 0;
	
	
	$lat_id_max = floor(bo_latlon2dist($DensLat, $DensLon, $DensLat_end, $DensLon) / $length / 1000);
	for($lat_id=0; $lat_id<=$lat_id_max; $lat_id++)
	{
		list($lat_act,) = bo_distbearing2latlong_rhumb($length * $lat_id * 1000, 0, $DensLat, $DensLon);
		$lon_id_max     = floor(bo_latlon2dist_rhumb($lat_act, $DensLon, $lat_act, $DensLon_end) / $length / 1000);
		$dlon = ($DensLon_end - $DensLon) / ($lon_id_max+1);
		
		
		// check if latitude lies in picture
		if ($lat_act >= $PicLatS)
		{
			//select correct data segment from data string
			$lon_start_pos  = floor(($PicLonW-$DensLon)/$dlon) ;
			$lon_string_len = floor(($PicLonE-$DensLon)/$dlon) - $lon_start_pos;
		
			$lon_data = substr($DATA, $string_pos + $lon_start_pos * 2*$bps, $lon_string_len *2*$bps);
			
			//image coordinates (left side of image, height is current latitude)
			list(,$y) = $Projection->LatLon2Image($lat_act, $PicLonE);
			
			$ay = round(($y / $min_block_size)); //block number y
			$dx = $dlon / ($PicLonE - $PicLonW) * $w; //delta x
			
			//get the data!
			for($j=0; $j<$lon_string_len; $j++)
			{
				//image x
				$x = $j * $dx;
				
				//x coordinates to picture "block-numbers"
				$ax = round(($x / $min_block_size));
				$pos_id = $ax+$ay*$w;
				
				//strikes per square kilometer
				$value = hexdec(substr($lon_data, $j *2*$bps, 2*$bps));
			
				if ($ratio || $participants)
				{
					if ($value)	
					{
						$lon_data2 = substr($DATA2, $string_pos2 + $lon_start_pos *2*$bps2, $lon_string_len *2*$bps2);
						$value2 = hexdec(substr($lon_data2, $j *2*$bps2, 2*$bps2));
						
						$VAL_COUNT1[$pos_id] += $value2;  //Save station-strike-count/participants to 1. Data array
						$VAL_COUNT2[$pos_id] += $value;   //Save total count to 2. array
						$COUNT2 += $value2;
					}
				}
				else
				{
					if ($value)
						$VAL_COUNT1[$pos_id] += $value; //Save strike-count to 1. Data array
					
					$VAL_COUNT2[$pos_id]++;         //Save count of datasets to 2. array
				}
				
				$COUNT1 += $value; // total strike count
			}
		}

		if ($lat_act > $PicLatN)
			break;
		
		$string_pos += ($lon_id_max+2) *2*$bps;
		$string_pos2 += ($lon_id_max+2) *2*$bps2;
	}
	
	//calculate mean and find max strikes per block
	foreach($VAL_COUNT1 as $pos_id => $value)
	{
		$VAL_COUNT1[$pos_id] /= $VAL_COUNT2[$pos_id];
	}
	
	if ($ratio)
	{
		$max_display_block = $max_count_block = 1; //always 100%
	}
	elseif (count($VAL_COUNT1))
	{
		$max_count_block = max($VAL_COUNT1);
		
		//ignore runaway values
		if (count($VAL_COUNT1) >= 2)
		{
			if ($participants)
			{
				$sdev = 0;
				foreach($VAL_COUNT1 as $value)
				{
					$sdev += pow($avg - $value, 2);
				}
				
				$sdev = 1 / (count($VAL_COUNT1) - 1) * $sdev;
				$sdev = sqrt($sdev);
				
				$max_display_block = $avg + 1.5 * $sdev; // avg +/- 3*sdev should countain 99.7% of the values of a normal distribution
			}
			else
			{
				$avg = array_sum($VAL_COUNT1) / count($VAL_COUNT1);
				
				asort($VAL_COUNT1);
				list($max_display_block) = array_slice($VAL_COUNT1, floor(count($VAL_COUNT1) * (1-BO_DENSITIES_GROUP_MAX/100000)), 1);
			}
			
		}

		if ($max_display_block > $max_count_block || $max_display_block <= 0)
			$max_display_block = $max_count_block;
	}
	
	if (count($VAL_COUNT1))
	{
		foreach($VAL_COUNT1 as $pos_id => $value)
		{
			$x = ($pos_id % $w);
			$y = ($pos_id-$x) / $w;

			if (!$ratio)
			{
				//strike count to relative count 0 to 1 for colors
				$value /= $max_display_block;
				
				if ($value > 1)
					$value = 1;
			}
			
			$x *= $min_block_size;
			$y *= $min_block_size;
			
			list($red, $green, $blue, $alpha) = bo_value2color($value, $colors);
			$color = imagecolorallocatealpha($I, $red, $green, $blue, $alpha);
			imagefilledrectangle($I, $x, $y, $x+$min_block_size-1, $y+$min_block_size-1, $color);

		}
	}

	//Borders
	if ($cfg['borders'][0] && file_exists(BO_DIR.'images/'.$cfg['borders'][0]))
	{
		$tmpImage = bo_imagecreatefromfile(BO_DIR.'images/'.$cfg['borders'][0]);
		if ($tmpImage)
			imagecopymerge($I, $tmpImage, 0,0, 0,0, $w, $h, $cfg['borders'][1]);
	}
	
	//add cities
	bo_add_cities2image($I, $cfg, $w, $h, $Projection);

	//Antennas
	if ($ratio && $station_id == bo_station_id() && isset($info['antennas']) && is_array($info['antennas']['bearing']))
	{
		$col1 = imagecolorallocatealpha($I, 255, 255, 255, 127);
		$col2 = imagecolorallocatealpha($I, 255, 255, 255, 30);
		$style = array($col1, $col1, $col1, $col1, $col2, $col2, $col2, $col2);
		imagesetstyle($I, $style);
		
		$size = 0.3 * ($w + $h) / 2;
		
		foreach($info['antennas']['bearing'] as $bear)
		{
			list($lat, $lon) = bo_distbearing2latlong(100000, $bear, $stinfo['lat'], $stinfo['lon']);
			list($ant_x, $ant_y) = $Projection->LatLon2Image($lat, $lon);
			$ant_x -= $StX;
			$ant_y -= $StY;
			
			
			$ant_xn = $ant_x / sqrt(pow($ant_x,2) + pow($ant_y,2)) * $size;
			$ant_yn = $ant_y / sqrt(pow($ant_x,2) + pow($ant_y,2)) * $size;
			
			imageline($I, $StX, $StY, $StX + $ant_xn *  1, $StY + $ant_yn *  1, IMG_COLOR_STYLED);
			imageline($I, $StX, $StY, $StX + $ant_xn * -1, $StY + $ant_yn * -1, IMG_COLOR_STYLED);
		}
	}

	
	//Legend (again!)
	$color = imagecolorallocatealpha($I, 100, 100, 100, 0);
	imagefilledrectangle($I, $w, 0, $w+$LegendWidth, $h, $color);

	//Legend: Text
	$PosX = $w + 5;
	$PosY = 10;
	$MarginX = 8;

	$size_title = 8;
	$size_text = 9;
	$size_title_legend = 12;
	
	if ($ratio)
	{
		$legend_text = _BL('Strike ratio', true);
		$extra_text  = 'Strike ratio';
	}
	elseif ($participants)
	{
		$legend_text = _BL('Avg. participants per strike locating', true);
		$extra_text  = 'Participants';
	}
	else
	{
		$legend_text = _BL('Strikes per', true).' '._BK().'²';
		$extra_text  = 'Strike density';
	}	
	
	//Station name
	if ($station_id > 0)
	{
		$PosY = bo_imagestring_max($I, $size_title, $PosX, $PosY, _BL('Station', true).':', $text_col, $LegendWidth);
		$PosY = bo_imagestring_max($I, $size_text, $PosX+$MarginX, $PosY, $stinfo['city'], $text_col, $LegendWidth, true);
		$PosY += 10;
	}
	
	//Strike count
	$PosY = bo_imagestring_max($I, $size_title, $PosX, $PosY, _BL('Strikes', true).':', $text_col, $LegendWidth);
	$PosY = bo_imagestring_max($I, $size_text, $PosX+$MarginX, $PosY, $COUNT1, $text_col, $LegendWidth, true);
	$PosY += 10;

	
	if ($ratio && intval($COUNT1))
	{
		$PosY = bo_imagestring_max($I, $size_title, $PosX, $PosY, strtr(_BL('densities_strikes_station', true), array('{STATION_CITY}' => $stinfo['city'])).':', $text_col, $LegendWidth);
		$PosY = bo_imagestring_max($I, $size_text, $PosX+$MarginX, $PosY, $COUNT2, $text_col, $LegendWidth, true);
		$PosY += 10;
	
		$PosY = bo_imagestring_max($I, $size_title, $PosX, $PosY, _BL('Mean strike ratio', true).':', $text_col, $LegendWidth);
		$PosY = bo_imagestring_max($I, $size_text, $PosX+$MarginX, $PosY, _BN($COUNT2 / $COUNT1 * 100, 1).'%', $text_col, $LegendWidth, true);
		$PosY += 25;
	}
	else
		$PosY += 15;
	
	/*
	//Area elements (calculation)
	$length_text = _BN($length, 1);
	$PosY = bo_imagestring_max($I, $size_title, $PosX, $PosY, _BL("Calculation basis are elements with area", true).':', $text_col, $LegendWidth);
	$PosY = bo_imagestring_max($I, $size_text, $PosX+$MarginX, $PosY, " ".$length_text.'km x '.$length_text.'km', $text_col, $LegendWidth);
	$PosY += 10;

	if (!$ratio && $area)
	{
		$max_real_density = $max_real_count / $area;
		
		//Strike density
		$PosY = bo_imagestring_max($I, $size_title, $PosX, $PosY, _BL('Maximum strike density calculated', true).':', $text_col, $LegendWidth);
		$PosY = bo_imagestring_max($I, $size_text, $PosX+$MarginX, $PosY, " "._BN($max_real_density, 1).'/km^2', $text_col, $LegendWidth);
		$PosY += 10;
	}
	
	$PosY += 15;
	*/

	if ($participants)
	{
		//Max. participants per block
		$max_count = $max_count_block;
		$PosY = bo_imagestring_max($I, $size_title, $PosX, $PosY, _BL('Maximum mean participants', true).':', $text_col, $LegendWidth);
		$PosY = bo_imagestring_max($I, $size_text, $PosX+$MarginX, $PosY, _BN($max_count, 1), $text_col, $LegendWidth, true);
		$PosY += 15;
	}
	elseif (!$ratio)
	{
		//Max. density per block
		$max_density = $max_count_block / $area;
		$PosY = bo_imagestring_max($I, $size_title, $PosX, $PosY, _BL('Maximum mean strike density displayed', true).':', $text_col, $LegendWidth);
		$PosY = bo_imagestring_max($I, $size_text, $PosX+$MarginX, $PosY, _BN($max_density, 2).'/'._BK().'²', $text_col, $LegendWidth, true);
		$PosY += 15;
	}
	
	$PosY += 10;
	$PosY = bo_imagestring_max($I, $size_title_legend, $PosX, $PosY, _BL('Legend', true), $text_col, $LegendWidth);
	$PosY = bo_imagestring_max($I, $size_title, $PosX+$MarginX, $PosY, '('.$legend_text.')', $text_col, $LegendWidth);
	
	if ($PosY + 15 > $ColorBarY)
	{
		$ColorBarHeight -= $PosY+15 - $ColorBarY;
		$ColorBarY = $PosY+15;
	}
	
	//Legend: Colorbar
	for ($i=$ColorBarY; $i<= $ColorBarHeight+$ColorBarY; $i += $ColorBarStep)
	{
		$value = 1-($i-$ColorBarY)/$ColorBarHeight;
		list($red, $green, $blue, $alpha) = bo_value2color($value, $colors);
		$color = imagecolorallocatealpha($I, $red, $green, $blue, $alpha);	
		imagefilledrectangle($I, $ColorBarX, $i, $ColorBarX+$ColorBarWidth, $i+$ColorBarStep-1, $color);
	}
	
	//Legend: Colorbar Text
	if ($ratio)
	{
		$max_ratio = $max_count_block;
		$text_top = _BN($max_ratio*100, 1).'%';
		$text_middle = '50%';
		$text_bottom = '0%';
	}
	else
	{
		if (!$participants)
		{
			$max_density = $max_display_block / $area;
			$decs = 2;
		}
		else
		{
			$max_density = $max_display_block;
			$decs = 0;
		}
			
		$text_top = _BN($max_density, $decs);
		$text_middle = _BN($max_density/2, $decs);
		$text_bottom = '0';
		
		if ($max_display_block < $max_count_block)
			$text_top = '> '.$text_top;
	}
	
	imagestring($I, 3, $ColorBarX+$ColorBarWidth+6, $ColorBarY+3, $text_top, $text_col);
	imagestring($I, 3, $ColorBarX+$ColorBarWidth+6, $ColorBarY-8+$ColorBarHeight/2, $text_middle, $text_col);
	imagestring($I, 3, $ColorBarX+$ColorBarWidth+6, $ColorBarY-5+$ColorBarHeight, $text_bottom, $text_col);
	
	
	//Station Name
	if ($station_id > 0)
	{
		imagestring($I, $fontsize, 1, 1 + $fontsize * 3, $text, $text_col);
		
		$size = 6;
		$color = imagecolorallocate($I, 255,255,255);
		imageline($I, $StX-$size, $StY, $StX+$size, $StY, $color);
		imageline($I, $StX, $StY-$size, $StX, $StY+$size, $color);
	}

	//Banner
	bo_image_banner_top($I, $w, $h, $cfg, $time_string, _BL($extra_text, true));
	bo_image_banner_bottom($I, $w, $h, $cfg, 0);
	
	

	BoDb::close();
	bo_session_close(true);
	bo_image_reduce_colors($I, true);

	header("Content-Type: $mime");
	if ($caching)
	{
		if (BO_CACHE_SUBDIRS === true)
		{
			$dir = dirname($cache_file);
			if (!file_exists($dir))
				mkdir($dir, 0777, true);
		}

		$ok = @bo_imageout($I, $extension, $cache_file, $last_update);
		
		if (!$ok)
			bo_image_cache_error($w, $h);

		bo_output_cache_file($cache_file, false);
	}
	else
		bo_imageout($I, $extension);

	exit;
	
}


function bo_get_density_stations()
{
	$station_infos = bo_stations('id', '', false);
	$stations = array();
	$stations[0] = 0;
	$stations[-1] = -1;
	$stations[bo_station_id()] = bo_station_id();
	
	if (defined('BO_DENSITY_STATIONS') && BO_DENSITY_STATIONS)
	{
		if (BO_DENSITY_STATIONS == 'all')
		{
			foreach($station_infos as $id => $dummy)
				$stations[$id] = $id;
		}
		else
		{
			$tmp = explode(',', BO_DENSITY_STATIONS);
			foreach($tmp as $id)
				$stations[$id] = $id;
		}
	}
	
	return $stations;
}


function bo_get_new_density_ranges($year = 0, $month = 0)
{
	/* Status (>= 0 means to-do, otherwise ready)
	 *   0 => 1 = Last month
	 *  -1 => 2 = Last year
	 *  -2 => 3 = Current month
	 *  -3 => 4 = Current year
	 *  -4 => 5 = Whole time range
	 */
	 
	 
	//Min/Max strike times
	$row = BoDb::query("SELECT MIN(time) mintime, MAX(time) maxtime FROM ".BO_DB_PREF."strikes")->fetch_assoc();
	$min_strike_time = strtotime($row['mintime'].' UTC');
	$max_strike_time = strtotime($row['maxtime'].' UTC');
	
	$ranges = array();
	
	if ($year || $month) //create individual ranges
	{
		if ($month && $year)
		{
			$month_start = $month;
			$year_start  = $year;
			$month_end   = $month+1;
			$year_end    = $year;
			$status      = -2;
		}
		elseif ($year)
		{
			$month_start = 1;
			$year_start  = $year;
			$month_end   = 1;
			$year_end    = $year+1;
			$status      = -4;
		}
		else
			return array();
		
		$ranges[] = array(
						gmmktime(0,0,0, $month_start,1,$year_start),
						gmmktime(0,0,-1,$month_end,  1,$year_end  ),
						$status
						);
	}
	else
	{
	
		$min_year  = gmdate('Y', $min_strike_time);
		$min_month = gmdate('m', $min_strike_time);
		$max_year  = gmdate('Y', $max_strike_time);
		$max_month = gmdate('m', $max_strike_time);

		
		//insert full years/months
		for ($y=$min_year;$y<=$max_year;$y++)
		{
			if ($y == $min_year)
				$start_month = $min_month;
			else
				$start_month = 1;

			if ($y == $max_year)
				$end_month = $max_month-1; // not the current month
			else
				$end_month = 12;
				
			for ($m = $start_month; $m <= $end_month ;$m++)
			{
				//months
				$ranges[] = array(
					gmmktime(0,0,0, $m,  1,$y),
					gmmktime(0,0,-1,$m+1,1,$y),
					0);
			}
			
			//year
			$ranges[] = array(
				gmmktime(0,0,0, 1,         1,$y),
				gmmktime(0,0,-1,$end_month+1,1,$y),
				-1);
		}
		
		
		//current year (out from full months)
		$ranges[] = array(strtotime( gmdate('Y').'-01-01 UTC'), gmmktime(0,0,-1,gmdate('m'),1,gmdate('Y')), -1 ); 
		
		
		//whole range (check only for years!)
		$sql = "SELECT MIN(date_start) mindate, MAX(date_end) maxdate
				FROM ".BO_DB_PREF."densities WHERE status=2";
		$res = BoDb::query($sql);
		$row = $res->fetch_assoc();
		$ymin = substr($row['mindate'],0,4);
		$ymax = substr($row['maxdate'],0,4);
		
		if ($ymin && $ymax && $ymin < $ymax)
			$ranges[] = array(strtotime($ymin.'-01-01 UTC'), strtotime($row['maxdate'].' UTC'), -4 ); 

		
		//current month and year to yesterday
		if (gmdate('d') != 1)
		{
			$end_time = gmmktime(0,0,0,gmdate('m'),gmdate('d'),gmdate('Y'))-1;
			
			if (gmdate('t', $end_time) != gmdate('d', $end_time))
			{
				//current month to yesterday
				$ranges[] = array(gmmktime(0,0,0,gmdate('m'),1,gmdate('Y')), $end_time, -2, 'current' => true ); //month
				
				//current year to yesterday
				$ranges[] = array(gmmktime(0,0,0,1,1,gmdate('Y')),           $end_time, -3, 'current' => true ); //year
			}
			
			
			//delete old data, if it's not the end day of the month
			$delete_time = gmmktime(0,0,0,gmdate('m'),gmdate('d')-3,gmdate('Y'));
			if (gmdate('t', $delete_time) != gmdate('d', $delete_time))
			{
				$cnt = BoDb::query("DELETE FROM ".BO_DB_PREF."densities WHERE date_end='".date('Y-m-d', $delete_time)."'");
				if ($cnt)
					bo_echod("Deleted $cnt density entries from database!");
			}

		}
	}
	
	//when end-date is bigger that max_strike time
	foreach($ranges as $id => $r)
	{
		if ($r[1] > $max_strike_time || $r[1] < $min_strike_time || $r[1] - $r[2] < 3600 * 22) 
			unset($ranges[$id]);
	}
	
	return $ranges;
}


function bo_density_insert_ranges($ranges, $force = false, $stations = array())
{
	global $_BO;
	
	if (empty($stations))
		$stations = bo_get_density_stations();

	$count = 0;
	
	//insert the ranges
	foreach($ranges as $r)
	{
		$date_start = gmdate('Y-m-d', $r[0]);
		$date_end   = gmdate('Y-m-d', $r[1]);
		$status     = intval($r[2]);
		$is_current = $r['current'] == true;
		
		$new = false;

		if ($r[0] >= $r[1])
			continue;
			
		//check if rows already exists
		$sql = "SELECT COUNT(*) cnt FROM ".BO_DB_PREF."densities 
					WHERE date_start='$date_start' AND date_end='$date_end'";
		$row = BoDb::query($sql)->fetch_assoc();

		// if rows missing --> insert to prepare for getting the data
		if ($force || count($stations) * count($_BO['density']) > $row['cnt'])
		{
			foreach($_BO['density'] as $type_id => $d)
			{
				if (!isset($d) || !is_array($d) || empty($d))
					continue;
				
				$lat_min = $d['coord'][2];
				$lon_min = $d['coord'][3];
				$lat_max = $d['coord'][0];
				$lon_max = $d['coord'][1];
				$length  = $d['length'];
				
				foreach($stations as $station_id)
				{
					
					if ($station_id <= 0 
						|| !$is_current
						|| (BO_CALC_DENSITIES_CURRENT == true && $station_id == bo_station_id())
						|| (BO_CALC_DENSITIES_CURRENT_ALL == true))
					{
						
						$sql = "INSERT IGNORE INTO ".BO_DB_PREF."densities 
								SET date_start='$date_start', date_end='$date_end', 
								type='$type_id', station_id='$station_id', status=$status,
								lat_min='$lat_min',lon_min='$lon_min',
								lat_max='$lat_max',lon_max='$lon_max',
								length='$length', info='', data=''
								";
						if (BoDb::query($sql))
						{
							$count++;
							$new = true;
						}
						
					}
				}
			}
		}
		
		if ($new)
			bo_echod("New range: $date_start - $date_end * Type: ".(-$status+1));

	}
	
	return $count;
}


?>
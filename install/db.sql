
CREATE TABLE IF NOT EXISTS `{BO_DB_PREF}conf` (
  `name` varchar(50) NOT NULL,
  `data` longtext NOT NULL,
  `changed` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`name`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{BO_DB_PREF}raw` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `time` datetime NOT NULL,
  `time_ns` int(11) unsigned NOT NULL,
  `lat` decimal(9,6) NOT NULL,
  `lon` decimal(9,6) NOT NULL,
  `height` smallint(6) NOT NULL,
  `strike_id` int(10) unsigned NOT NULL,
  `channels` TINYINT UNSIGNED NOT NULL,
  `ntime` SMALLINT UNSIGNED NOT NULL,
  `amp1` TINYINT UNSIGNED NOT NULL, 
  `amp2` TINYINT UNSIGNED NOT NULL,
  `amp1_max` TINYINT UNSIGNED NOT NULL,
  `amp2_max` TINYINT UNSIGNED NOT NULL,
  `freq1` SMALLINT UNSIGNED NOT NULL,
  `freq1_amp` TINYINT UNSIGNED NOT NULL,
  `freq2` SMALLINT UNSIGNED NOT NULL,
  `freq2_amp` TINYINT UNSIGNED NOT NULL,
  `data` blob NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `strike_id` (`strike_id`),
  KEY `time` (`time`)
)  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{BO_DB_PREF}stations` (
  `id` smallint(5) unsigned NOT NULL auto_increment,
  `bo_station_id` smallint(5) unsigned NOT NULL DEFAULT '0',
  `bo_user_id` smallint(5) unsigned NOT NULL DEFAULT '0',
  `city` varchar(50) NOT NULL DEFAULT '',
  `country` varchar(50) NOT NULL DEFAULT '',
  `comment` varchar(128) NOT NULL DEFAULT '',
  `lat` decimal(9,6) NOT NULL DEFAULT '0',
  `lon` decimal(9,6) NOT NULL DEFAULT '0',
  `alt` decimal(5,1) NOT NULL DEFAULT '0',
  `distance` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `last_time` TIMESTAMP NULL DEFAULT NULL,
  `last_time_ns` int(11) NOT NULL DEFAULT '0',
  `status` varchar(3) NOT NULL DEFAULT '',
  `controller_pcb` VARCHAR(30) NOT NULL DEFAULT '0',
  `amp_pcbs` varchar(100) NOT NULL DEFAULT '',
  `amp_gains` varchar(40) NOT NULL DEFAULT '',
  `amp_antennas` varchar(100) NOT NULL DEFAULT '',
  `amp_firmwares` varchar(100) NOT NULL DEFAULT '',
  `firmware` varchar(50) NOT NULL DEFAULT '',
  `url` varchar(200) NOT NULL DEFAULT '',
  `show_mybo` varchar(3) NOT NULL DEFAULT '',
  `first_seen` TIMESTAMP NULL DEFAULT NULL,
  `changed` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  KEY `bo_station_id` (`bo_station_id`),
  KEY `bo_user_id` (`bo_user_id`),
  KEY `country` (`country`),
  KEY `first_seen` (`first_seen`),
  KEY `status` (`status`),
  KEY `show_mybo` (`show_mybo`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{BO_DB_PREF}stations_stat` (
  `station_id` smallint(11) unsigned NOT NULL,
  `time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `signalsh` mediumint(11) unsigned NOT NULL default '0',
  `strikesh` mediumint(11) unsigned NOT NULL default '0',
  KEY `time` (`time`),
  KEY `station_id` (`station_id`),
  KEY `stations_time` (`station_id`, `time`)
) DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{BO_DB_PREF}stations_strikes` (
  `strike_id` int(11) unsigned NOT NULL,
  `station_id` smallint(9) unsigned NOT NULL,
  UNIQUE KEY `strike_id_3` (`strike_id`,`station_id`)
) DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{BO_DB_PREF}strikes` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `time_ns` int(11) NOT NULL,
  `lat` decimal(9,6) NOT NULL,
  `lon` decimal(9,6) NOT NULL,
  `alt` decimal(5,1) NOT NULL,
  `distance` mediumint(8) unsigned NOT NULL,
  `bearing` decimal(4,1) default NULL,
  `current` decimal(10,2) NOT NULL,
  `type` tinyint(1) default NULL,
  `deviation` smallint(5) unsigned NOT NULL,
  `stations` smallint(5) unsigned NOT NULL,
  `stations_calc` smallint(5) unsigned NOT NULL,
  `part` tinyint(4) NOT NULL,
  `part_pos` tinyint(4) NOT NULL,
  `raw_id` int(11) unsigned default NULL,
  `status` tinyint(4) NOT NULL,
  PRIMARY KEY  (`id`, `time`),
  KEY `part` (`part`),
  KEY `raw_id` (`raw_id`),
  KEY `time` (`time`)
)  DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{BO_DB_PREF}user` (
  `id` smallint(11) unsigned NOT NULL auto_increment,
  `login` varchar(30) NOT NULL,
  `password` varchar(60) NOT NULL,
  `level` smallint unsigned NOT NULL default '0',
  `mail` varchar(100) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `login` (`login`)
)  DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{BO_DB_PREF}densities` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `date_start` date default NULL,
  `date_end` date default NULL,
  `status` tinyint(4) NOT NULL,
  `station_id` int NOT NULL,
  `length` decimal(4,1) NOT NULL,
  `lat_min` decimal(5,2) NOT NULL,
  `lon_min` decimal(5,2) NOT NULL,
  `lat_max` decimal(5,2) NOT NULL,
  `lon_max` decimal(5,2) NOT NULL,
  `type` smallint(5) unsigned NOT NULL,
  `info` varchar(500) NOT NULL,
  `data` longblob NOT NULL,
  `changed` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `unique_dataset` (`date_start`,`date_end`,`station_id`,`type`),
  KEY `date_start` (`date_start`,`date_end`),
  KEY `status` (`status`),
  KEY `type` (`type`),
  KEY `status_station` (`status`,`station_id`),
  KEY `date_station_position` (`date_start`, `date_end`, `station_id`, `lat_min`, `lon_min`, `lat_max`, `lon_max`),
  KEY `date_end` (`date_end`)
)  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{BO_DB_PREF}cities` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `name` varchar(50) NOT NULL,
  `lat` decimal(9,6) NOT NULL,
  `lon` decimal(9,6) NOT NULL,
  `type` tinyint(4) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `latlon` (`lat`,`lon`)
)  DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{BO_DB_PREF}conf` (
  `name` varchar(50) NOT NULL,
  `data` longtext NOT NULL,
  `changed` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{BO_DB_PREF}raw` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `time` datetime NOT NULL,
  `time_ns` int(11) unsigned NOT NULL,
  `lat` decimal(9,6) NOT NULL,
  `lon` decimal(9,6) NOT NULL,
  `height` smallint(6) NOT NULL,
  `strike_id` int(10) unsigned NOT NULL,
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
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{BO_DB_PREF}stations` (
  `id` smallint(5) unsigned NOT NULL,
  `user` varchar(8) NOT NULL,
  `city` varchar(50) NOT NULL,
  `country` varchar(20) NOT NULL,
  `lat` decimal(9,6) NOT NULL,
  `lon` decimal(9,6) NOT NULL,
  `distance` mediumint(8) unsigned NOT NULL,
  `last_time` datetime NOT NULL,
  `last_time_ns` int(11) NOT NULL,
  `status` varchar(1) NOT NULL,
  `changed` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{BO_DB_PREF}stations_stat` (
  `station_id` smallint(11) unsigned NOT NULL,
  `time` datetime NOT NULL,
  `signalsh` mediumint(11) unsigned NOT NULL default '0',
  `strikesh` mediumint(11) unsigned NOT NULL default '0',
  KEY `time` (`time`),
  KEY `station_id` (`station_id`),
  KEY `stations_time` (`station_id`, `time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{BO_DB_PREF}stations_strikes` (
  `strike_id` int(11) unsigned NOT NULL,
  `station_id` smallint(9) unsigned NOT NULL,
  UNIQUE KEY `strike_id_3` (`strike_id`,`station_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{BO_DB_PREF}strikes` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `time` datetime NOT NULL,
  `time_ns` int(11) NOT NULL,
  `lat` decimal(9,6) NOT NULL,
  `lon` decimal(9,6) NOT NULL,
  `lat2` tinyint(4) NOT NULL,
  `lon2` tinyint(4) NOT NULL,
  `distance` mediumint(8) unsigned NOT NULL,
  `bearing` decimal(4,1) default NULL,
  `current` decimal(10,2) NOT NULL,
  `polarity` tinyint(1) default NULL,
  `deviation` smallint(5) unsigned NOT NULL,
  `users` smallint(5) unsigned NOT NULL,
  `part` tinyint(1) NOT NULL,
  `raw_id` int(11) unsigned default NULL,
  PRIMARY KEY  (`id`),
  KEY `part` (`part`),
  KEY `raw_id` (`raw_id`),
  KEY `time` (`time`),
  KEY `latlon2` (`lat2`,`lon2`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{BO_DB_PREF}user` (
  `id` smallint(11) unsigned NOT NULL auto_increment,
  `login` varchar(30) NOT NULL,
  `password` varchar(60) NOT NULL,
  `level` tinyint(4) unsigned NOT NULL default '0',
  `mail` varchar(100) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;


CREATE TABLE IF NOT EXISTS `{BO_DB_PREF}densities` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `date_start` date default NULL,
  `date_end` date default NULL,
  `status` tinyint(4) NOT NULL,
  `station_id` smallint(5) unsigned NOT NULL,
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
  KEY `type` (`type`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `{BO_DB_PREF}cities` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `name` varchar(50) NOT NULL,
  `lat` decimal(9,6) NOT NULL,
  `lon` decimal(9,6) NOT NULL,
  `type` tinyint(4) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `latlon` (`lat`,`lon`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

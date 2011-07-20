SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

DROP TABLE IF EXISTS `counters`;

CREATE TABLE `counters` (
  `id` int(10) NOT NULL auto_increment,
  `strkey` varchar(50) NOT NULL,
  `display_name` varchar(100) NULL,
  `section_id` int(10) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `counter_by_section` (`strkey`,`section_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `counters_days`;

CREATE TABLE `counters_days` (
  `counter_id` int(10) NOT NULL,
  `day_date` date NOT NULL,
  `hits` int(10) NOT NULL,
  `visits` int(10) NOT NULL,
  `day_visits` int(10) NOT NULL,
  PRIMARY KEY  (`counter_id`,`day_date`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `sections`;

CREATE TABLE `sections` (
  `id` int(10) NOT NULL auto_increment,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `parent_id` int(10) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `unicite_nomEtParent` (`name`,`parent_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

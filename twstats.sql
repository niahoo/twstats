DROP TABLE IF EXISTS `counters`;
CREATE TABLE `counters` (
  `id` int(10) NOT NULL auto_increment,
  `strkey` varchar(50) character set utf8 collate utf8_unicode_ci NOT NULL,
  `name` varchar(100) character set utf8 collate utf8_unicode_ci NOT NULL,
  `section_id` int(10) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `counter_by_section` (`strkey`,`section_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;


DROP TABLE IF EXISTS `counters_days`;
CREATE TABLE `counters_days` (
  `counter_id` int(10) NOT NULL,
  `day_date` date NOT NULL,
  `hits` int(10) NOT NULL,
  `visits` int(10) NOT NULL,
  `day_visits` int(10) NOT NULL,
  PRIMARY KEY  (`counter_id`,`day_date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `sections`;
CREATE TABLE `sections` (
  `id` int(10) NOT NULL auto_increment,
  `name` varchar(50) character set latin1 collate latin1_general_ci NOT NULL,
  `parent_id` int(10) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `unicite_nomEtParent` (`name`,`parent_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

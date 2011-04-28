-- phpMyAdmin SQL Dump
-- version 3.3.2deb1
-- http://www.phpmyadmin.net
--
-- Serveur: localhost
-- Généré le : Lun 04 Octobre 2010 à 14:56
-- Version du serveur: 5.1.41
-- Version de PHP: 5.3.2-1ubuntu4.5

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Base de données: `twstats`
--

-- --------------------------------------------------------



CREATE TABLE IF NOT EXISTS `counters` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `strkey` varchar(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `section_id` int(10) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `counter_by_section` (`strkey`,`section_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=6 ;

-- --------------------------------------------------------


CREATE TABLE IF NOT EXISTS `counters_days` (
  `counter_id` int(10) NOT NULL,
  `day_date` date NOT NULL,
  `hits` int(10) NOT NULL,
  `visits` int(10) NOT NULL,
  `day_visits` int(10) NOT NULL,
  PRIMARY KEY (`counter_id`,`day_date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------


CREATE TABLE IF NOT EXISTS `sections` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `parent_id` int(10) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unicite_nomEtParent` (`name`,`parent_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=10 ;

-- phpMyAdmin SQL Dump
-- version 4.0.7
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Jun 24, 2014 at 09:30 PM
-- Server version: 5.5.37-0ubuntu0.12.04.1
-- PHP Version: 5.3.10-1ubuntu3.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `cyberspark_analysis`
--

CREATE DATABASE IF NOT EXISTS `cyberspark_analysis`;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE IF NOT EXISTS `logs` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `URL_HASH` varchar(32) DEFAULT NULL,
  `milliseconds` bigint(13) unsigned NOT NULL,
  `date` varchar(40) NOT NULL,
  `host` varchar(40) NOT NULL,
  `thread` varchar(40) NOT NULL,
  `tick` int(7) unsigned NOT NULL DEFAULT '0',
  `crashes` int(4) unsigned NOT NULL DEFAULT '0',
  `http_ms` int(4) unsigned NOT NULL,
  `length` int(10) NOT NULL DEFAULT '0',
  `md5` varchar(32) DEFAULT NULL,
  `condition` varchar(100) DEFAULT NULL,
  `URL_ID` int(10) NOT NULL,
  `result_code` int(3) unsigned NOT NULL DEFAULT '0',
  `year` int(4) unsigned NOT NULL,
  `month` int(2) unsigned NOT NULL,
  `day` int(2) unsigned NOT NULL,
  `hour` int(2) unsigned NOT NULL,
  `minute` int(2) unsigned NOT NULL,
  `second` int(2) unsigned NOT NULL,
  `APIusage` int(6) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `ID` (`ID`),
  UNIQUE KEY `NO_DUPE_LOGS` (`URL_HASH`,`milliseconds`),
  KEY `milliseconds` (`milliseconds`),
  KEY `URL_HASH` (`URL_HASH`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;



-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE IF NOT EXISTS `messages` (
  `ID` int(10) unsigned NOT NULL,
  `message` text,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `ID` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `urls`
--

CREATE TABLE IF NOT EXISTS `urls` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `URL_HASH` varchar(32) NOT NULL,
  `url` text,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `ID` (`ID`),
  UNIQUE KEY `URL_HASH` (`URL_HASH`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE IF NOT EXISTS `files` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;


/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

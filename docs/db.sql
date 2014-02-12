/*
 Navicat MySQL Data Transfer

 Source Server         : Ridi Dev
 Source Server Type    : MySQL
 Source Server Version : 50535
 Source Host           : dev.ridi.kr
 Source Database       : story_test

 Target Server Type    : MySQL
 Target Server Version : 50535
 File Encoding         : utf-8

 Date: 02/12/2014 12:13:40 PM
*/

SET NAMES utf8;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
--  Table structure for `banner`
-- ----------------------------
DROP TABLE IF EXISTS `banner`;
CREATE TABLE `banner` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `background` varchar(128) NOT NULL,
  `image` varchar(128) NOT NULL,
  `link_android` varchar(128) NOT NULL,
  `link_ios` varchar(128) NOT NULL,
  `reg_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_visible` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `book`
-- ----------------------------
DROP TABLE IF EXISTS `book`;
CREATE TABLE `book` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(32) NOT NULL,
  `store_id` varchar(32) DEFAULT NULL,
  `title` varchar(128) NOT NULL,
  `author` varchar(64) NOT NULL,
  `publisher` varchar(32) NOT NULL,
  `catchphrase` varchar(256) NOT NULL,
  `short_description` varchar(256) NOT NULL,
  `begin_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `end_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `total_part_count` int(11) NOT NULL,
  `is_completed` tinyint(4) NOT NULL,
  `score` int(11) NOT NULL,
  `upload_days` int(11) NOT NULL COMMENT 'sunday is 2^0',
  `adult_only` tinyint(4) NOT NULL DEFAULT '0',
  `end_action_flag` int(11) NOT NULL DEFAULT '2' COMMENT '0: 모두 공개(무료) / 1: 모두 잠금(유료) / 2: 비공개(게시종료)',
  PRIMARY KEY (`id`),
  KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=105 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `book_intro`
-- ----------------------------
DROP TABLE IF EXISTS `book_intro`;
CREATE TABLE `book_intro` (
  `b_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `about_author` text NOT NULL,
  PRIMARY KEY (`b_id`),
  CONSTRAINT `book_intro_ibfk_1` FOREIGN KEY (`b_id`) REFERENCES `book` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `buyer_user`
-- ----------------------------
DROP TABLE IF EXISTS `buyer_user`;
CREATE TABLE `buyer_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `google_id` varchar(255) NOT NULL,
  `google_reg_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `google_id` (`google_id`) USING BTREE,
  KEY `id` (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `coin_history`
-- ----------------------------
DROP TABLE IF EXISTS `coin_history`;
CREATE TABLE `coin_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `u_id` int(11) NOT NULL,
  `amount` int(11) NOT NULL COMMENT 'Buy/Use amount of coin',
  `source` varchar(5) NOT NULL COMMENT 'INAPP / RIDI / USE',
  `ph_id` int(11) DEFAULT NULL COMMENT 'Purchase History ID. (Only for USE)',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id` (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `log_push`
-- ----------------------------
DROP TABLE IF EXISTS `log_push`;
CREATE TABLE `log_push` (
  `request` text NOT NULL,
  `response` text NOT NULL,
  `platform` enum('Android','iOS') NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `notice`
-- ----------------------------
DROP TABLE IF EXISTS `notice`;
CREATE TABLE `notice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `reg_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_visible` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `part`
-- ----------------------------
DROP TABLE IF EXISTS `part`;
CREATE TABLE `part` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `b_id` int(11) NOT NULL,
  `store_id` varchar(32) NOT NULL,
  `title` varchar(256) NOT NULL,
  `seq` int(11) NOT NULL,
  `price` int(11) NOT NULL COMMENT '가격 (단위: 코인)',
  `is_manual_opened` tinyint(4) NOT NULL DEFAULT '0' COMMENT '수동 공개 여부 (0: 앱 Flow에 따름, 1: 수동 강제 공개)',
  `begin_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `end_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `id` (`id`,`seq`) USING BTREE,
  KEY `b_id` (`b_id`),
  CONSTRAINT `part_ibfk_1` FOREIGN KEY (`b_id`) REFERENCES `book` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1843 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `part_comment`
-- ----------------------------
DROP TABLE IF EXISTS `part_comment`;
CREATE TABLE `part_comment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `p_id` int(11) NOT NULL,
  `device_id` varchar(32) NOT NULL,
  `nickname` varchar(16) NOT NULL,
  `comment` varchar(300) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `p_id` (`p_id`,`timestamp`),
  KEY `device_id` (`device_id`) USING HASH
) ENGINE=InnoDB AUTO_INCREMENT=11493 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `purchase_history`
-- ----------------------------
DROP TABLE IF EXISTS `purchase_history`;
CREATE TABLE `purchase_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `u_id` int(11) NOT NULL,
  `p_id` int(11) NOT NULL,
  `coin_amount` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `push_devices`
-- ----------------------------
DROP TABLE IF EXISTS `push_devices`;
CREATE TABLE `push_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` varchar(32) NOT NULL,
  `platform` enum('iOS','Android') NOT NULL DEFAULT 'Android',
  `device_token` varchar(256) NOT NULL,
  `reg_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type_flags` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `device_id` (`device_id`),
  KEY `reg_date` (`reg_date`),
  KEY `device_token` (`device_token`(255))
) ENGINE=InnoDB AUTO_INCREMENT=426208 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `stat_download`
-- ----------------------------
DROP TABLE IF EXISTS `stat_download`;
CREATE TABLE `stat_download` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `p_id` int(11) NOT NULL,
  `is_success` tinyint(4) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `p_id` (`p_id`),
  KEY `timestamp` (`timestamp`,`p_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=9306878 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
--  Table structure for `stat_download_storyplusbook`
-- ----------------------------
DROP TABLE IF EXISTS `stat_download_storyplusbook`;
CREATE TABLE `stat_download_storyplusbook` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `storyplusbook_id` int(11) NOT NULL,
  `is_success` tinyint(4) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `storyplusbook_id` (`storyplusbook_id`),
  KEY `timestamp` (`timestamp`,`storyplusbook_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=20969 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
--  Table structure for `storyplusbook`
-- ----------------------------
DROP TABLE IF EXISTS `storyplusbook`;
CREATE TABLE `storyplusbook` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` varchar(32) NOT NULL,
  `store_id_for_link` varchar(32) NOT NULL,
  `title` varchar(128) NOT NULL,
  `author` varchar(64) NOT NULL,
  `publisher` varchar(32) NOT NULL,
  `publish_date` date NOT NULL DEFAULT '0000-00-00',
  `catchphrase` varchar(256) NOT NULL,
  `preview_percent` int(11) NOT NULL DEFAULT '0',
  `badge` enum('NONE','BESTSELLER','FAMOUSAUTHOR','HOTISSUE','NEW') NOT NULL DEFAULT 'NONE',
  `begin_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `end_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `banner_image` varchar(256) NOT NULL COMMENT 'NEWBOOK일 경우에만 필요',
  `trailer_url` varchar(256) NOT NULL,
  `comment_hint` varchar(64) NOT NULL,
  `priority` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=68 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `storyplusbook_comment`
-- ----------------------------
DROP TABLE IF EXISTS `storyplusbook_comment`;
CREATE TABLE `storyplusbook_comment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `b_id` int(11) NOT NULL,
  `device_id` varchar(32) NOT NULL,
  `comment` varchar(300) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `b_id` (`b_id`,`timestamp`),
  KEY `device_id` (`device_id`) USING HASH
) ENGINE=InnoDB AUTO_INCREMENT=268 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `storyplusbook_intro`
-- ----------------------------
DROP TABLE IF EXISTS `storyplusbook_intro`;
CREATE TABLE `storyplusbook_intro` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `b_id` int(11) NOT NULL,
  `type` enum('BOOK_INTRO','AUTHOR_INTRO','PHRASE','RECOMMEND') NOT NULL,
  `descriptor` varchar(512) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `index_bid` (`b_id`) USING BTREE,
  CONSTRAINT `storyplusbook_intro` FOREIGN KEY (`b_id`) REFERENCES `storyplusbook` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=289 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `user_interest`
-- ----------------------------
DROP TABLE IF EXISTS `user_interest`;
CREATE TABLE `user_interest` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` varchar(32) NOT NULL,
  `b_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cancel` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_id` (`device_id`,`b_id`) USING BTREE,
  KEY `b_id` (`b_id`,`timestamp`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=318921 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `user_part_like`
-- ----------------------------
DROP TABLE IF EXISTS `user_part_like`;
CREATE TABLE `user_part_like` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` varchar(32) NOT NULL,
  `p_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `p_id` (`p_id`,`device_id`) USING BTREE,
  KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB AUTO_INCREMENT=458133 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `user_storyplusbook_like`
-- ----------------------------
DROP TABLE IF EXISTS `user_storyplusbook_like`;
CREATE TABLE `user_storyplusbook_like` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` varchar(32) NOT NULL,
  `b_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `b_id` (`b_id`,`device_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3513 DEFAULT CHARSET=utf8;

SET FOREIGN_KEY_CHECKS = 1;

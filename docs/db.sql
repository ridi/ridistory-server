/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `banner` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `background` varchar(128) NOT NULL,
  `image` varchar(128) NOT NULL,
  `link_android` varchar(128) NOT NULL,
  `link_ios` varchar(128) NOT NULL,
  `reg_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_visible` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `book` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(32) NOT NULL,
  `store_id` varchar(32) DEFAULT NULL,
  `sale_store_id` varchar(32) DEFAULT NULL COMMENT '단권판매용 스토어 ID',
  `title` varchar(128) NOT NULL,
  `author` varchar(64) NOT NULL,
  `publisher` varchar(32) NOT NULL,
  `catchphrase` varchar(256) NOT NULL,
  `short_description` varchar(400) NOT NULL,
  `begin_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `end_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `total_part_count` int(11) NOT NULL,
  `is_completed` tinyint(4) NOT NULL,
  `score` int(11) NOT NULL DEFAULT '0',
  `upload_days` int(11) NOT NULL COMMENT 'sunday is 2^0',
  `adult_only` tinyint(4) NOT NULL DEFAULT '0',
  `end_action_flag` enum('ALL_FREE','ALL_CHARGED','SALES_CLOSED','ALL_CLOSED') NOT NULL DEFAULT 'ALL_CLOSED' COMMENT 'ALL_FREE: 모두 공개, ALL_CHARGED: 모두 잠금, SALES_CLOSED: 판매 종료 (책 표지만 공개), ALL_CLOSED: 게시 종료 (비공개)',
  `cp_id` int(11) DEFAULT NULL COMMENT 'CP Id',
  `royalty_percent` int(11) NOT NULL DEFAULT '0' COMMENT '정산율',
  `is_active_lock` tinyint(4) NOT NULL DEFAULT '0' COMMENT '잠금기능 사용여부 (0: 사용하지 않음, 1: 사용함)',
  `lock_day_term` int(11) NOT NULL DEFAULT '14' COMMENT '잠금일 기간 (기본: 2주)',
  `surely_completed` tinyint(4) NOT NULL DEFAULT '1' COMMENT '완결 확정 여부',
  PRIMARY KEY (`id`),
  KEY `id` (`id`),
  KEY `cp_id` (`cp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `book_intro` (
  `b_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `about_author` text NOT NULL,
  PRIMARY KEY (`b_id`),
  CONSTRAINT `book_intro_ibfk_1` FOREIGN KEY (`b_id`) REFERENCES `book` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `book_notice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `b_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `reg_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_visible` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `b_id` (`b_id`,`message`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `buyer_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `google_id` varchar(255) NOT NULL,
  `google_reg_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `google_id` (`google_id`) USING BTREE,
  KEY `id` (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cashslide_event_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `u_id` int(11) NOT NULL,
  `device_id` varchar(256) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_id` (`u_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coin_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `u_id` int(11) NOT NULL,
  `amount` int(11) NOT NULL COMMENT 'Buy/Use amount of coin',
  `source` enum('IN_INAPP','IN_RIDI','IN_TEST','IN_EVENT','IN_CS_REWARD','OUT_BUY_PART','OUT_COIN_REFUND','OUT_WITHDRAW') NOT NULL COMMENT 'IN_INAPP: 인앱결제, IN_RIDI: 리디캐시 결제, IN_TEST: 테스트용 코인, IN_EVENT: 이벤트용 코인, IN_CS_REWARD: 고객 보상, OUT_BUY_PART: 코인사용(파트 구매), OUT_COIN_REFUND: 환불, OUT_WITHDRAW: 회수(취소)',
  `ph_id` int(11) DEFAULT NULL COMMENT 'Purchase History ID. (Only for USE)',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_id` (`u_id`,`ph_id`) USING BTREE,
  KEY `id` (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cp_account` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL COMMENT 'CP 계정명',
  `password` varchar(255) DEFAULT NULL,
  `staff1_name` varchar(255) DEFAULT NULL COMMENT '담당자1 성함',
  `staff1_phone` varchar(20) DEFAULT NULL COMMENT '담당자1 연락처',
  `staff1_email` varchar(255) DEFAULT NULL COMMENT '담당자1 이메일',
  `staff2_name` varchar(255) DEFAULT NULL COMMENT '담당자2 성함',
  `staff2_phone` varchar(20) DEFAULT NULL COMMENT '담당자2 연락처',
  `staff2_email` varchar(255) DEFAULT NULL COMMENT '담당자2 이메일',
  `cp_site_id` varchar(255) NOT NULL COMMENT 'CP 사이트 ID',
  `cp_type` enum('Publisher','Private','AG','Closed','ETC') DEFAULT NULL COMMENT 'CP 형태 / Publisher: 출판사, Private: 개인, AG: AG, Closed: 폐업, ETC: 기타',
  `contract_date` varchar(8) DEFAULT '' COMMENT '계약일자 (yyyymmdd)',
  `end_date` varchar(8) DEFAULT '' COMMENT '만료일자 (yyyymmdd)',
  `auto_contract_renew_year` int(11) DEFAULT NULL COMMENT '자동연장',
  `etc_note` text COMMENT '비고',
  `company_num` varchar(20) DEFAULT NULL COMMENT '사업자 등록번호',
  `company_name` varchar(255) DEFAULT NULL COMMENT '사업자명',
  `company_ceo` varchar(255) DEFAULT NULL COMMENT '대표자',
  `company_doing` varchar(255) DEFAULT NULL COMMENT '업태',
  `company_doing_type` varchar(255) DEFAULT NULL COMMENT '종목',
  `zip_code` varchar(7) DEFAULT NULL COMMENT '우편번호',
  `address` text COMMENT '주소',
  `calculate_staff_name` varchar(255) DEFAULT NULL COMMENT '정산 담당자 성함',
  `calculate_staff_phone` varchar(20) DEFAULT NULL COMMENT '정산 담당자 연락처',
  `calculate_staff_email` varchar(255) DEFAULT NULL COMMENT '정산 담당자 이메일',
  `calculate_bank` varchar(20) DEFAULT NULL COMMENT '정산 은행',
  `calculate_account_num` varchar(50) DEFAULT NULL COMMENT '정산 계좌번호',
  `calculate_account_holder` varchar(255) DEFAULT NULL COMMENT '정산 예금주',
  `is_ridibooks_cp` tinyint(4) DEFAULT '0' COMMENT '리디북스 CP 여부 (0: 리디스토리 CP, 1: 리디북스+리디스토리 CP)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `cp_site_id` (`cp_site_id`),
  UNIQUE KEY `name` (`name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cs_reward_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ch_id` int(11) NOT NULL COMMENT 'coin_history의 id',
  `u_id` int(11) NOT NULL COMMENT 'coin_history의 u_id',
  `comment` varchar(255) NOT NULL COMMENT '코인 지급 사유 (고객 보상 사유)',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '코인지급일',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ch_id` (`ch_id`,`u_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ch_id` int(11) NOT NULL COMMENT 'coin_history의 id',
  `u_id` int(11) NOT NULL COMMENT 'coin_history의 u_id',
  `comment` varchar(255) NOT NULL COMMENT '코인 지급 사유',
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '코인지급일',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ch_id` (`ch_id`,`u_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inapp_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` varchar(100) NOT NULL,
  `u_id` int(11) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `purchase_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `payload` varchar(50) NOT NULL,
  `purchase_token` varchar(200) NOT NULL,
  `purchase_data` varchar(1024) NOT NULL,
  `signature` varchar(1024) NOT NULL,
  `status` enum('OK','REFUNDED','PENDING') NOT NULL DEFAULT 'PENDING' COMMENT 'OK: 정상, REFUNDED: 환불됨, PENDING: 대기(오류)',
  `refunded_time` timestamp NULL DEFAULT NULL COMMENT '환불일',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`),
  KEY `u_id` (`u_id`),
  KEY `purchase_time` (`purchase_time`),
  KEY `refunded_time` (`refunded_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inapp_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sku` varchar(50) DEFAULT NULL,
  `coin_amount` int(11) NOT NULL DEFAULT '0',
  `bonus_coin_amount` int(11) NOT NULL DEFAULT '0',
  `price` int(11) NOT NULL DEFAULT '0' COMMENT '가격',
  `type` enum('GOOGLE','RIDICASH') NOT NULL DEFAULT 'GOOGLE' COMMENT 'GOOGLE: 구글 인앱결제, RIDICASH: 리디캐쉬 결제',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_push` (
  `request` text NOT NULL,
  `response` text NOT NULL,
  `platform` enum('Android','iOS') NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `reg_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_visible` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `part` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `b_id` int(11) NOT NULL,
  `store_id` varchar(32) NOT NULL,
  `title` varchar(256) NOT NULL,
  `seq` int(11) NOT NULL,
  `price` int(11) NOT NULL DEFAULT '2' COMMENT '가격 (단위: 코인)',
  `begin_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `num_likes` int(11) NOT NULL DEFAULT '0',
  `author_comment` varchar(300) DEFAULT NULL COMMENT '작가의 말',
  PRIMARY KEY (`id`),
  KEY `id` (`id`,`seq`) USING BTREE,
  KEY `b_id` (`b_id`),
  KEY `begin_date` (`begin_date`),
  CONSTRAINT `part_ibfk_1` FOREIGN KEY (`b_id`) REFERENCES `book` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `u_id` int(11) NOT NULL,
  `p_id` int(11) NOT NULL,
  `is_paid` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0: 무료, 1: 유료',
  `coin_amount` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_purchase` (`u_id`,`p_id`,`is_paid`) USING BTREE,
  KEY `timestamp` (`timestamp`),
  KEY `p_id` (`p_id`),
  KEY `u_id` (`u_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `push_devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `u_id` int(11) DEFAULT NULL,
  `device_id` varchar(32) NOT NULL,
  `platform` enum('iOS','Android') NOT NULL DEFAULT 'Android',
  `device_token` varchar(256) NOT NULL,
  `reg_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type_flags` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_id` (`device_id`),
  UNIQUE KEY `device_token` (`device_token`(255)) USING BTREE,
  KEY `reg_date` (`reg_date`),
  KEY `platform` (`platform`),
  KEY `u_id` (`u_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recommended_book` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `b_id` int(11) NOT NULL,
  `store_id` varchar(32) NOT NULL,
  `title` varchar(128) NOT NULL,
  `seq` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `b_id` (`b_id`,`store_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ridicash_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `t_id` varchar(50) DEFAULT NULL,
  `u_id` int(11) NOT NULL,
  `ridibooks_id` varchar(255) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `purchase_time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `status` enum('OK','REFUNDED','PENDING') NOT NULL DEFAULT 'PENDING' COMMENT 'OK: 정상, REFUNDED: 환불됨, PENDING: 대기(오류)',
  `refunded_time` timestamp NULL DEFAULT NULL COMMENT '환불일',
  PRIMARY KEY (`id`),
  UNIQUE KEY `t_id` (`t_id`),
  KEY `u_id` (`u_id`),
  KEY `ridibooks_id` (`ridibooks_id`),
  KEY `purchase_time` (`purchase_time`),
  KEY `refunded_time` (`refunded_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stat_download` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `p_id` int(11) NOT NULL,
  `is_success` tinyint(4) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `p_id` (`p_id`),
  KEY `timestamp` (`timestamp`,`p_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stat_download_storyplusbook` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `storyplusbook_id` int(11) NOT NULL,
  `is_success` tinyint(4) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `storyplusbook_id` (`storyplusbook_id`),
  KEY `timestamp` (`timestamp`,`storyplusbook_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `storyplusbook_intro` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `b_id` int(11) NOT NULL,
  `type` enum('BOOK_INTRO','AUTHOR_INTRO','PHRASE','RECOMMEND') NOT NULL,
  `descriptor` varchar(512) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `index_bid` (`b_id`) USING BTREE,
  CONSTRAINT `storyplusbook_intro` FOREIGN KEY (`b_id`) REFERENCES `storyplusbook` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_user` (
  `u_id` int(11) NOT NULL,
  `comment` varchar(32) NOT NULL,
  `is_active` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0: Deactive, 1: Active',
  PRIMARY KEY (`u_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_interest` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` varchar(32) NOT NULL,
  `b_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cancel` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_id` (`device_id`,`b_id`) USING BTREE,
  KEY `b_id` (`b_id`,`timestamp`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_migration_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `u_id` int(11) NOT NULL COMMENT '회원 ID',
  `old_google_id` varchar(255) NOT NULL COMMENT '원래 구글 계정',
  `new_google_id` varchar(255) NOT NULL COMMENT '새로운 구글 계정',
  `migration_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '마이그레이션 일자',
  PRIMARY KEY (`id`),
  UNIQUE KEY `old_google_id` (`old_google_id`,`new_google_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_part_like` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` varchar(32) NOT NULL,
  `p_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `p_id` (`p_id`,`device_id`) USING BTREE,
  KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`192.168.0.45`*/ /*!50003 TRIGGER `trigger_insert` AFTER INSERT ON `user_part_like` FOR EACH ROW UPDATE part SET num_likes = num_likes + 1 WHERE id = NEW.p_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8 */ ;
/*!50003 SET character_set_results = utf8 */ ;
/*!50003 SET collation_connection  = utf8_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`192.168.0.45`*/ /*!50003 TRIGGER `trigger_delete` AFTER DELETE ON `user_part_like` FOR EACH ROW UPDATE part SET num_likes = num_likes - 1 WHERE id = OLD.p_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_storyplusbook_like` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` varchar(32) NOT NULL,
  `b_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `b_id` (`b_id`,`device_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

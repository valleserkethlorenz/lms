-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: lms
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `tbl_announcement`
--

DROP TABLE IF EXISTS `tbl_announcement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_announcement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `updated` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_league_id` (`league_id`),
  KEY `idx_created` (`created`),
  CONSTRAINT `tbl_announcement_ibfk_1` FOREIGN KEY (`league_id`) REFERENCES `tbl_league` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tbl_announcement_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `tbl_user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_announcement`
--

LOCK TABLES `tbl_announcement` WRITE;
/*!40000 ALTER TABLE `tbl_announcement` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_announcement` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_attempts`
--

DROP TABLE IF EXISTS `tbl_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(255) NOT NULL,
  `try_time` bigint(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_attempts`
--

LOCK TABLES `tbl_attempts` WRITE;
/*!40000 ALTER TABLE `tbl_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_audit_log`
--

DROP TABLE IF EXISTS `tbl_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_audit_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `user_role` varchar(50) DEFAULT NULL,
  `related_type` varchar(80) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB AUTO_INCREMENT=190 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_audit_log`
--

LOCK TABLES `tbl_audit_log` WRITE;
/*!40000 ALTER TABLE `tbl_audit_log` DISABLE KEYS */;
INSERT INTO `tbl_audit_log` VALUES (1,'Admin Admin',1,'Login','User logged in successfully','2026-08-29 20:21:35',NULL,NULL,NULL),(2,'Admin Admin',1,'Logout','Admin Admin logged out','2026-08-29 20:35:20','Admin',NULL,NULL),(3,'Admin Admin',1,'Login','User logged in successfully','2026-08-29 20:35:44',NULL,NULL,NULL),(4,'Admin Admin',1,'Logout','Admin Admin logged out','2026-08-29 20:55:50','Admin',NULL,NULL),(5,'Admin Admin',1,'Login','User logged in successfully','2026-08-29 20:58:41',NULL,NULL,NULL),(6,'Admin Admin',1,'Add Utility','Added utility: [league_format] Round Robin','2026-08-29 21:33:24','Admin',NULL,NULL),(7,'Admin Admin',1,'Add Utility','Added utility: [sport] Basketball','2026-08-29 22:15:14','Admin',NULL,NULL),(8,'Admin Admin',1,'Add Utility','Added utility: [sport] Takraw','2026-08-29 22:19:16','Admin',NULL,NULL),(9,'Admin Admin',1,'Add Utility','Added utility: [sport] Volleyball','2026-08-29 22:19:49','Admin',NULL,NULL),(10,'Admin Admin',1,'Add Utility','Added utility: [sport] Soccer','2026-08-29 22:20:28','Admin',NULL,NULL),(11,'Admin Admin',1,'Add Utility','Added utility: [sport] Mobile Legends','2026-08-29 22:20:47','Admin',NULL,NULL),(12,'Admin Admin',1,'Add Utility','Added utility: [sport] Badminton','2026-08-29 22:22:26','Admin',NULL,NULL),(13,'Admin Admin',1,'Add Utility','Added utility: [sport] DOTA','2026-08-29 22:22:47','Admin',NULL,NULL),(14,'Admin Admin',1,'Deactivate Utility','Set is_active=0 for utility ID 10','2026-08-30 19:39:59','Admin',NULL,NULL),(15,'Admin Admin',1,'Activate Utility','Set is_active=1 for utility ID 10','2026-08-30 19:40:02','Admin',NULL,NULL),(16,'Admin Admin',1,'Deactivate Utility','Set is_active=0 for utility ID 10','2026-08-30 19:50:58','Admin',NULL,NULL),(17,'Admin Admin',1,'Activate Utility','Set is_active=1 for utility ID 10','2026-08-30 19:51:00','Admin',NULL,NULL),(18,'Admin Admin',1,'Add Utility','Added utility: [division] Junior','2026-08-30 20:17:42','Admin',NULL,NULL),(19,'Admin Admin',1,'Add Utility','Added utility: [division] Under 18','2026-08-30 20:19:00','Admin',NULL,NULL),(20,'Admin Admin',1,'Add Utility','Added utility: [division] Open','2026-08-30 20:19:21','Admin',NULL,NULL),(21,'Admin Admin',1,'Add Utility','Added utility: [division] Under 21','2026-08-30 20:19:57','Admin',NULL,NULL),(22,'Admin Admin',1,'Add Utility','Added utility: [venue] Panabo City Multi-Purpose Tourism Cultural and Sports Center','2026-08-30 20:27:00','Admin',NULL,NULL),(23,'Admin Admin',1,'Add Utility','Added utility: [venue] Panabo Park','2026-08-30 20:27:15','Admin',NULL,NULL),(24,'Admin Admin',1,'Add Utility','Added utility: [document_type] Birth Certificate','2026-08-30 20:27:42','Admin',NULL,NULL),(25,'Admin Admin',1,'Add Utility','Added utility: [document_type] Medical','2026-08-30 20:27:56','Admin',NULL,NULL),(26,'Admin Admin',1,'Add Utility','Added utility: [document_type] Valid ID','2026-08-30 20:28:07','Admin',NULL,NULL),(27,'Admin Admin',1,'Add Utility','Added utility: [category] Male','2026-08-30 20:28:19','Admin',NULL,NULL),(28,'Admin Admin',1,'Add Utility','Added utility: [category] Female','2026-08-30 20:28:25','Admin',NULL,NULL),(29,'Admin Admin',1,'Add Utility','Added utility: [category] Mixed','2026-08-30 20:28:33','Admin',NULL,NULL),(30,'Admin Admin',1,'Logout','Admin Admin logged out','2026-08-30 23:34:35','Admin',NULL,NULL),(31,'Admin Admin',1,'Login','User logged in successfully','2026-08-30 23:42:31',NULL,NULL,NULL),(32,'Admin Admin',1,'Change Own Password','Admin changed their account password.','2026-08-30 23:43:10','Admin',NULL,NULL),(33,'Admin Admin',1,'Create Account','Created Organizer account for Keth Valleser (org1@lms.com)','2026-08-31 01:14:42','Admin',NULL,NULL),(34,'Admin Admin',1,'Edit Account','Updated account ID 3: Keth Valleser, role=1, stat=1','2026-08-31 01:15:11','Admin',NULL,NULL),(35,'Admin Admin',1,'Deactivate Account','Set stat=0 for user ID 3','2026-08-31 01:15:39','Admin',NULL,NULL),(36,'Admin Admin',1,'Activate Account','Set stat=1 for user ID 3','2026-08-31 01:15:44','Admin',NULL,NULL),(37,'Admin Admin',1,'Edit Account','Updated account ID 3: Keth Lorenz Valleser, role=1, stat=1','2026-08-31 01:37:37','Admin',NULL,NULL),(38,'Admin Admin',1,'Create Account','Created Organizer account for Chris Bustamante (Org2@lms.com)','2026-08-31 10:31:32','Admin',NULL,NULL),(39,'Admin Admin',1,'Edit Account','Updated account ID 4: Chris Bustamante, role=1, stat=1','2026-08-31 10:34:13','Admin',NULL,NULL),(40,'Admin Admin',1,'Create Account','Created Committee account for Dave Dellosa (Com1@lms.com)','2026-08-31 10:45:01','Admin',NULL,NULL),(41,'Admin Admin',1,'Edit Account','Updated account ID 5: Dave Dellosa, role=4, stat=1','2026-08-31 10:53:56','Admin',NULL,NULL),(42,'Admin Admin',1,'Logout','Admin Admin logged out','2026-08-31 13:50:27','Admin',NULL,NULL),(43,'Admin Admin',1,'Login','User logged in successfully','2026-08-31 17:52:41',NULL,NULL,NULL),(44,'Admin Admin',1,'Edit Account','Updated account ID 3: Keth Lorenz Valleser, role=1, stat=1','2026-08-31 17:53:06','Admin',NULL,NULL),(45,'Admin Admin',1,'Logout','Admin Admin logged out','2026-08-31 17:53:12','Admin',NULL,NULL),(46,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-08-31 18:13:52',NULL,NULL,NULL),(47,'Keth Lorenz Valleser',3,'Create Gallery Post','Created gallery post baleboll 2026 (ID 1)','2026-08-31 21:41:09','Organizer',NULL,NULL),(48,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-08-31 21:54:06',NULL,NULL,NULL),(49,'Admin Admin',1,'Login','User logged in successfully','2026-08-31 22:12:28',NULL,NULL,NULL),(50,'Admin Admin',1,'Add Utility','Added utility: [sport] Pickleball','2026-08-31 22:14:20','Admin',NULL,NULL),(51,'Admin Admin',1,'Deactivate Utility','Set is_active=0 for utility ID 29','2026-08-31 22:14:32','Admin',NULL,NULL),(52,'Admin Admin',1,'Activate Utility','Set is_active=1 for utility ID 29','2026-08-31 22:14:39','Admin',NULL,NULL),(53,'Admin Admin',1,'Logout','Admin Admin logged out','2026-08-31 22:14:44','Admin',NULL,NULL),(54,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-08-31 22:15:26',NULL,NULL,NULL),(55,'Keth Lorenz Valleser',3,'Create Gallery Post','Created gallery post Mga Gwapo (ID 2)','2026-08-31 22:16:48','Organizer',NULL,NULL),(56,'Admin Admin',1,'Login','User logged in successfully','2026-08-31 22:25:05',NULL,NULL,NULL),(57,'Admin Admin',1,'Deactivate Utility','Set is_active=0 for utility ID 29','2026-08-31 22:25:14','Admin',NULL,NULL),(58,'Admin Admin',1,'Activate Utility','Set is_active=1 for utility ID 29','2026-08-31 22:25:23','Admin',NULL,NULL),(59,'Admin Admin',1,'Deactivate Utility','Set is_active=0 for utility ID 18','2026-08-31 22:25:32','Admin',NULL,NULL),(60,'Admin Admin',1,'Activate Utility','Set is_active=1 for utility ID 18','2026-08-31 22:25:34','Admin',NULL,NULL),(61,'Admin Admin',1,'Deactivate Utility','Set is_active=0 for utility ID 29','2026-08-31 22:25:41','Admin',NULL,NULL),(62,'Admin Admin',1,'Activate Utility','Set is_active=1 for utility ID 29','2026-08-31 22:25:43','Admin',NULL,NULL),(63,'Admin Admin',1,'Logout','Admin Admin logged out','2026-08-31 22:25:48','Admin',NULL,NULL),(64,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-08-31 22:26:04',NULL,NULL,NULL),(65,'Admin Admin',1,'Login','User logged in successfully','2026-09-01 11:44:17',NULL,NULL,NULL),(66,'Admin Admin',1,'Delete Utility','Deleted utility ID 15','2026-09-01 11:51:21','Admin',NULL,NULL),(67,'Admin Admin',1,'Add Utility','Added utility: [sport] DOTA','2026-09-01 11:51:33','Admin',NULL,NULL),(68,'Admin Admin',1,'Logout','Admin Admin logged out','2026-09-01 12:30:36','Admin',NULL,NULL),(69,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-09-01 12:30:57',NULL,NULL,NULL),(70,'Keth Lorenz Valleser',3,'Create League Draft','Created draft league: Pekelbol 2026 (ID 1)','2026-09-01 12:33:48','Organizer',NULL,NULL),(71,'Keth Lorenz Valleser',3,'Publish League','Published league: Pekelbol 2026 (ID 1)','2026-09-01 12:33:53','Organizer',NULL,NULL),(72,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-09-01 18:00:00',NULL,NULL,NULL),(73,'Admin Admin',1,'Login','User logged in successfully','2026-09-01 19:33:29',NULL,NULL,NULL),(74,'Admin Admin',1,'Deactivate Account','Set stat=0 for user ID 5','2026-09-01 19:34:18','Admin',NULL,NULL),(75,'Admin Admin',1,'Activate Account','Set stat=1 for user ID 5','2026-09-01 19:34:25','Admin',NULL,NULL),(76,'Admin Admin',1,'Logout','Admin Admin logged out','2026-09-01 19:37:41','Admin',NULL,NULL),(77,'Dave Dellosa',5,'Login','User logged in successfully','2026-09-01 19:38:04',NULL,NULL,NULL),(78,'Dave Dellosa',5,'Logout','Dave Dellosa logged out','2026-09-01 19:58:57','Committee',NULL,NULL),(79,'Admin Admin',1,'Login','User logged in successfully','2026-09-01 22:34:35',NULL,NULL,NULL),(80,'Admin Admin',1,'Deactivate Utility','Set is_active=0 for utility ID 17','2026-09-01 22:35:16','Admin',NULL,NULL),(81,'Admin Admin',1,'Activate Utility','Set is_active=1 for utility ID 17','2026-09-01 22:35:25','Admin',NULL,NULL),(82,'Admin Admin',1,'Logout','Admin Admin logged out','2026-09-01 22:42:54','Admin',NULL,NULL),(83,'Xyroz Dela Cruz',6,'Login','User logged in successfully','2026-09-01 22:49:11',NULL,NULL,NULL),(84,'Admin Admin',1,'Login','User logged in successfully','2026-09-02 00:35:05',NULL,NULL,NULL),(85,'Admin Admin',1,'Logout','Admin Admin logged out','2026-09-02 00:37:32','Admin',NULL,NULL),(86,'Admin Admin',1,'Login','User logged in successfully','2026-09-02 00:37:59',NULL,NULL,NULL),(87,'Admin Admin',1,'Logout','Admin Admin logged out','2026-09-02 00:38:51','Admin',NULL,NULL),(88,'Admin Admin',1,'Login','User logged in successfully','2026-09-02 11:22:44',NULL,NULL,NULL),(89,'Admin Admin',1,'Logout','Admin Admin logged out','2026-09-02 11:23:47','Admin',NULL,NULL),(90,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-09-12 00:33:13',NULL,NULL,NULL),(91,'Keth Lorenz Valleser',3,'Create Gallery Post','Created gallery post Event 2026 (ID 3)','2026-09-12 00:35:45','Organizer',NULL,NULL),(92,'Keth Lorenz Valleser',3,'Create Gallery Post','Created gallery post Event 2026 (ID 4)','2026-09-12 00:35:57','Organizer',NULL,NULL),(93,'Keth Lorenz Valleser',3,'Create Gallery Post','Created gallery post Event 2026 (ID 5)','2026-09-12 00:36:09','Organizer',NULL,NULL),(94,'Keth Lorenz Valleser',3,'Create Gallery Post','Created gallery post Event 2026 (ID 6)','2026-09-12 00:36:21','Organizer',NULL,NULL),(95,'Keth Lorenz Valleser',3,'Create Gallery Post','Created gallery post Event 2026 (ID 7)','2026-09-12 00:36:34','Organizer',NULL,NULL),(96,'Keth Lorenz Valleser',3,'Create Gallery Post','Created gallery post Event 2026 (ID 8)','2026-09-12 00:36:45','Organizer',NULL,NULL),(97,'Keth Lorenz Valleser',3,'Create Gallery Post','Created gallery post Event 2026 (ID 9)','2026-09-12 00:36:56','Organizer',NULL,NULL),(98,'Keth Lorenz Valleser',3,'Create Gallery Post','Created gallery post Event 2026 (ID 10)','2026-09-12 00:37:09','Organizer',NULL,NULL),(99,'Keth Lorenz Valleser',3,'Create Gallery Post','Created gallery post Event 2026 (ID 11)','2026-09-12 00:37:22','Organizer',NULL,NULL),(100,'Keth Lorenz Valleser',3,'Create Gallery Post','Created gallery post Event 2026 (ID 12)','2026-09-12 00:37:33','Organizer',NULL,NULL),(101,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-09-12 00:38:36',NULL,NULL,NULL),(102,'Keth Lorenz Valleser',3,'Delete Gallery Post','Deleted gallery post Event 2026 (ID 3) with 0 image files','2026-09-12 00:38:51','Organizer',NULL,NULL),(103,'Keth Lorenz Valleser',3,'Delete Gallery Post','Deleted gallery post Event 2026 (ID 12) with 0 image files','2026-09-12 00:39:02','Organizer',NULL,NULL),(104,'Keth Lorenz Valleser',3,'Delete Gallery Post','Deleted gallery post Event 2026 (ID 7) with 0 image files','2026-09-12 00:39:10','Organizer',NULL,NULL),(105,'Keth Lorenz Valleser',3,'Delete Gallery Post','Deleted gallery post Event 2026 (ID 4) with 0 image files','2026-09-12 00:39:16','Organizer',NULL,NULL),(106,'Keth Lorenz Valleser',3,'Delete Gallery Post','Deleted gallery post Event 2026 (ID 5) with 0 image files','2026-09-12 00:39:27','Organizer',NULL,NULL),(107,'Keth Lorenz Valleser',3,'Delete Gallery Post','Deleted gallery post Event 2026 (ID 6) with 0 image files','2026-09-12 00:39:34','Organizer',NULL,NULL),(108,'Keth Lorenz Valleser',3,'Delete Gallery Post','Deleted gallery post Event 2026 (ID 8) with 0 image files','2026-09-12 00:39:42','Organizer',NULL,NULL),(109,'Keth Lorenz Valleser',3,'Delete Gallery Post','Deleted gallery post Event 2026 (ID 11) with 0 image files','2026-09-12 00:40:02','Organizer',NULL,NULL),(110,'Keth Lorenz Valleser',3,'Delete Gallery Post','Deleted gallery post Event 2026 (ID 10) with 0 image files','2026-09-12 00:40:24','Organizer',NULL,NULL),(111,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-09-12 00:58:40',NULL,NULL,NULL),(112,'Keth Lorenz Valleser',3,'Delete Gallery Post','Deleted gallery post Event 2026 (ID 9) with 3 image files','2026-09-12 00:59:13','Organizer',NULL,NULL),(113,'Keth Lorenz Valleser',3,'Create Gallery Post','Created gallery post Event 2026 (ID 13)','2026-09-12 01:00:05','Organizer',NULL,NULL),(114,'Keth Lorenz Valleser',3,'Delete Gallery Post','Deleted gallery post Event 2026 (ID 13) with 3 image files','2026-09-12 01:09:14','Organizer',NULL,NULL),(115,'Keth Lorenz Valleser',3,'Create Gallery Post','Created gallery post Event 2026 (ID 14)','2026-09-12 01:10:58','Organizer',NULL,NULL),(116,'Dave Dellosa',5,'Login','User logged in successfully','2026-09-15 13:24:44',NULL,NULL,NULL),(117,'Dave Dellosa',5,'Logout','Dave Dellosa logged out','2026-09-15 15:45:17','Committee',NULL,NULL),(118,'Admin Admin',1,'Login','User logged in successfully','2026-09-15 15:56:54',NULL,NULL,NULL),(119,'Admin Admin',1,'Logout','Admin Admin logged out','2026-09-15 15:57:41','Admin',NULL,NULL),(120,'Xyroz Dela Cruz',6,'Login','User logged in successfully','2026-09-15 16:23:35',NULL,NULL,NULL),(121,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-09-15 16:49:30',NULL,NULL,NULL),(122,'Admin Admin',1,'Login','User logged in successfully','2026-09-15 19:32:04',NULL,NULL,NULL),(123,'Admin Admin',1,'Login','User logged in successfully','2026-09-15 19:32:34',NULL,NULL,NULL),(124,'Admin Admin',1,'Login','User logged in successfully','2026-09-15 19:33:03',NULL,NULL,NULL),(125,'Admin Admin',1,'Login','User logged in successfully','2026-09-15 19:44:37',NULL,NULL,NULL),(126,'Admin Admin',1,'Login','User logged in successfully','2026-09-15 19:45:05',NULL,NULL,NULL),(127,'Admin Admin',1,'Login','User logged in successfully','2026-09-15 19:52:37',NULL,NULL,NULL),(128,'Admin Admin',1,'Login','User logged in successfully','2026-09-15 19:52:55',NULL,NULL,NULL),(129,'Admin Admin',1,'Login','User logged in successfully','2026-09-15 20:08:35',NULL,NULL,NULL),(130,'Admin Admin',1,'Logout','Admin Admin logged out','2026-09-15 20:28:07','Admin',NULL,NULL),(131,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-09-15 20:28:28',NULL,NULL,NULL),(132,'Admin Admin',1,'Login','User logged in successfully','2026-10-12 10:17:53',NULL,NULL,NULL),(133,'Admin Admin',1,'Logout','Admin Admin logged out','2026-10-12 10:18:35','Admin',NULL,NULL),(134,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-10-12 10:18:50',NULL,NULL,NULL),(135,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-10-12 22:08:03',NULL,NULL,NULL),(136,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-10-12 22:18:35',NULL,NULL,NULL),(138,'Keth Lorenz Valleser',3,'Generate League Report','Generated Team Registrations for league ID 1','2026-10-12 22:57:57','Organizer',NULL,NULL),(139,'Keth Lorenz Valleser',3,'Generate League Report','Generated Team Registrations for league ID 1','2026-10-12 22:57:58','Organizer',NULL,NULL),(140,'Keth Lorenz Valleser',3,'Generate League Report','Generated Team Registrations for league ID 1','2026-10-12 22:57:59','Organizer',NULL,NULL),(141,'Keth Lorenz Valleser',3,'Generate League Report','Generated Team Registrations for league ID 1','2026-10-12 22:58:02','Organizer',NULL,NULL),(142,'Keth Lorenz Valleser',3,'Generate League Report','Generated Team Registrations for league ID 1','2026-10-12 22:58:03','Organizer',NULL,NULL),(143,'Keth Lorenz Valleser',3,'Generate League Report','Generated Team Registrations for league ID 1','2026-10-12 22:58:03','Organizer',NULL,NULL),(144,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-10-12 23:04:40',NULL,NULL,NULL),(145,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-10-13 00:28:13',NULL,NULL,NULL),(146,'Admin Admin',1,'Login','User logged in successfully','2026-10-13 00:42:50',NULL,NULL,NULL),(147,'Admin Admin',1,'Logout','Admin Admin logged out','2026-10-13 00:46:34','Admin',NULL,NULL),(148,'Dave Dellosa',5,'Login','User logged in successfully','2026-10-13 00:47:00',NULL,NULL,NULL),(149,'Dave Dellosa',5,'Logout','Dave Dellosa logged out','2026-10-13 00:47:44','Committee',NULL,NULL),(150,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-10-13 00:48:00',NULL,NULL,NULL),(151,'Keth Lorenz Valleser',3,'Create League Draft','Created draft league: baskitbol Cup 2026 (ID 2)','2026-10-13 01:30:51','Organizer',NULL,NULL),(152,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-10-13 01:42:44',NULL,NULL,NULL),(153,'Keth Lorenz Valleser',3,'Publish League','Published league: baskitbol Cup 2026 (ID 2)','2026-10-13 01:55:37','Organizer',NULL,NULL),(154,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-10-13 02:16:31',NULL,NULL,NULL),(155,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-10-13 10:16:58',NULL,NULL,NULL),(156,'Keth Lorenz Valleser',3,'Configure Team Grouping','Enabled grouping for league ID 2','2026-10-13 11:28:52','Organizer',NULL,NULL),(157,'Keth Lorenz Valleser',3,'Configure Team Grouping','Disabled grouping for league ID 2','2026-10-13 11:28:55','Organizer',NULL,NULL),(158,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-10-13 13:06:09',NULL,NULL,NULL),(159,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-10-13 18:59:02',NULL,NULL,NULL),(160,'Keth Lorenz Valleser',3,'Configure Team Grouping','Enabled grouping for league ID 2','2026-10-13 19:00:02','Organizer',NULL,NULL),(161,'Keth Lorenz Valleser',3,'Configure Team Grouping','Disabled grouping for league ID 2','2026-10-13 19:00:09','Organizer',NULL,NULL),(162,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-10-13 22:48:15',NULL,NULL,NULL),(163,'Keth Lorenz Valleser',3,'Configure Team Grouping','Enabled grouping for league ID 2','2026-10-13 23:45:24','Organizer',NULL,NULL),(164,'Keth Lorenz Valleser',3,'Configure Team Grouping','Disabled grouping for league ID 2','2026-10-13 23:45:32','Organizer',NULL,NULL),(165,'Keth Lorenz Valleser',3,'Update Finals Format','Finals pattern for league ID 2 set to sport default','2026-10-13 23:49:06','Organizer',NULL,NULL),(166,'Keth Lorenz Valleser',3,'Update Finals Format','Finals pattern for league ID 2 set to direct_final_bronze','2026-10-13 23:49:15','Organizer',NULL,NULL),(167,'Keth Lorenz Valleser',3,'Update Finals Format','Finals pattern for league ID 2 set to sport default','2026-10-13 23:49:23','Organizer',NULL,NULL),(168,'Keth Lorenz Valleser',3,'Create League Stage','Created stage Elimination (group) for league ID 2','2026-10-14 00:22:20','Organizer',NULL,NULL),(169,'Keth Lorenz Valleser',3,'Create League Stage','Created stage Semi finals (placement) for league ID 2','2026-10-14 00:37:33','Organizer',NULL,NULL),(170,'Keth Lorenz Valleser',3,'Delete League Stage','Removed stage ID 25 from league ID 2','2026-10-14 00:37:53','Organizer',NULL,NULL),(171,'Keth Lorenz Valleser',3,'Delete League Stage','Removed stage ID 24 from league ID 2','2026-10-14 00:37:56','Organizer',NULL,NULL),(172,'Keth Lorenz Valleser',3,'Configure Team Grouping','Enabled grouping for league ID 2','2026-10-14 00:44:14','Organizer',NULL,NULL),(173,'Keth Lorenz Valleser',3,'Configure Team Grouping','Disabled grouping for league ID 2','2026-10-14 00:44:18','Organizer',NULL,NULL),(174,'Dave Dellosa',5,'Login','User logged in successfully','2026-10-14 01:37:53',NULL,NULL,NULL),(175,'Dave Dellosa',5,'Logout','Dave Dellosa logged out','2026-10-14 01:38:21','Committee',NULL,NULL),(176,'Admin Admin',1,'Login','User logged in successfully','2026-10-14 01:38:41',NULL,NULL,NULL),(177,'Admin Admin',1,'Logout','Admin Admin logged out','2026-10-14 01:39:10','Admin',NULL,NULL),(178,'keth Valleser',7,'Login','User logged in successfully','2026-10-14 02:09:50',NULL,NULL,NULL),(179,'keth Valleser',7,'Login','User logged in successfully','2026-10-14 02:19:15',NULL,NULL,NULL),(180,'Chris Bustamante',4,'Login','User logged in successfully','2026-10-14 02:36:58',NULL,NULL,NULL),(181,'Chris Bustamante',4,'Create Gallery Post','Created gallery post Wala lang (ID 15)','2026-10-14 02:38:29','Organizer',NULL,NULL),(182,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-10-14 08:56:54',NULL,NULL,NULL),(183,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-10-14 14:05:52',NULL,NULL,NULL),(184,'Keth Lorenz Valleser',3,'Create League Draft','Created draft league: balebol cup 2026 (ID 27)','2026-10-14 14:07:33','Organizer',NULL,NULL),(185,'Keth Lorenz Valleser',3,'Publish League','Published league: balebol cup 2026 (ID 27)','2026-10-14 14:07:51','Organizer',NULL,NULL),(186,'keth Valleser',7,'Login','User logged in successfully','2026-10-19 23:11:48',NULL,NULL,NULL),(187,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-10-19 23:17:29',NULL,NULL,NULL),(188,'Keth Lorenz Valleser',3,'Login','User logged in successfully','2026-10-19 23:20:18',NULL,NULL,NULL),(189,'Keth Lorenz Valleser',3,'Archive','Archived league: Pekelbol 2026','2026-10-19 23:20:47',NULL,NULL,NULL);
/*!40000 ALTER TABLE `tbl_audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_gallery`
--

DROP TABLE IF EXISTS `tbl_gallery`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_gallery` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_gallery`
--

LOCK TABLES `tbl_gallery` WRITE;
/*!40000 ALTER TABLE `tbl_gallery` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_gallery` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_gallery_images`
--

DROP TABLE IF EXISTS `tbl_gallery_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_gallery_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_display_order` (`display_order`),
  KEY `idx_gallery_images_archive` (`post_id`,`is_archived`,`display_order`),
  CONSTRAINT `tbl_gallery_images_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `tbl_gallery_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_gallery_images`
--

LOCK TABLES `tbl_gallery_images` WRITE;
/*!40000 ALTER TABLE `tbl_gallery_images` DISABLE KEYS */;
INSERT INTO `tbl_gallery_images` VALUES (1,1,'gallery_2a1f6af8818f5742b4549933ea3e85bc.jpg',0,'2026-08-31 21:41:09',0,NULL),(2,1,'gallery_96bd16834e37732119e10d7066c4ab90.jpg',1,'2026-08-31 21:41:09',0,NULL),(3,1,'gallery_fe2136a8ef8773ad8511479a173cf173.jpg',2,'2026-08-31 21:41:09',0,NULL),(4,2,'gallery_3e609e5b7f3cf2bd7180411a4df1c4e5.jpg',0,'2026-08-31 22:16:48',0,NULL),(5,2,'gallery_a163e8a89ce0b0f8039fbb389a405c41.jpg',1,'2026-08-31 22:16:48',0,NULL),(6,2,'gallery_17ac2fd0563f974272156a82063c6e42.jpg',2,'2026-08-31 22:16:48',0,NULL),(40,14,'https://res.cloudinary.com/u3wlf2nk/image/upload/v1789146648/lms/gallery/gallery_5c41f356bab7f8798e68a00095d07dae.jpg',0,'2026-09-12 01:10:58',0,NULL),(41,14,'https://res.cloudinary.com/u3wlf2nk/image/upload/v1789146653/lms/gallery/gallery_42201dace19213d06b0cc6e0b225ace5.jpg',1,'2026-09-12 01:10:58',0,NULL),(42,14,'https://res.cloudinary.com/u3wlf2nk/image/upload/v1789146656/lms/gallery/gallery_65329627c9c776e7ee0ab72b696d1bee.jpg',2,'2026-09-12 01:10:58',0,NULL),(43,15,'https://res.cloudinary.com/u3wlf2nk/image/upload/v1791916704/lms/gallery/gallery_9529380bf491136c09ff217113a9dafb.jpg',0,'2026-10-14 02:38:29',0,NULL),(44,15,'https://res.cloudinary.com/u3wlf2nk/image/upload/v1791916707/lms/gallery/gallery_501a6d5be8ca931e630ad8bd95688dea.webp',1,'2026-10-14 02:38:29',0,NULL),(45,15,'https://res.cloudinary.com/u3wlf2nk/image/upload/v1791916708/lms/gallery/gallery_f3194991c286cf737a6ac52770705ae8.jpg',2,'2026-10-14 02:38:29',0,NULL);
/*!40000 ALTER TABLE `tbl_gallery_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_gallery_posts`
--

DROP TABLE IF EXISTS `tbl_gallery_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_gallery_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_uploaded_by` (`uploaded_by`),
  KEY `idx_gallery_posts_archive` (`is_archived`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_gallery_posts`
--

LOCK TABLES `tbl_gallery_posts` WRITE;
/*!40000 ALTER TABLE `tbl_gallery_posts` DISABLE KEYS */;
INSERT INTO `tbl_gallery_posts` VALUES (1,'baleboll 2026','test ra ni',3,'2026-08-31 21:41:09','2026-08-31 21:41:09',0,NULL),(2,'Mga Gwapo','basta mga gwapo ni sila',3,'2026-08-31 22:16:48','2026-08-31 22:16:48',0,NULL),(14,'Event 2026','test only',3,'2026-09-12 01:10:58','2026-09-12 01:10:58',0,NULL),(15,'Wala lang','eto ay test lamang po',4,'2026-10-14 02:38:29','2026-10-14 02:38:29',0,NULL);
/*!40000 ALTER TABLE `tbl_gallery_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_league`
--

DROP TABLE IF EXISTS `tbl_league`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_league` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_image` varchar(255) NOT NULL,
  `guideline_document` varchar(255) DEFAULT NULL,
  `league_name` varchar(255) NOT NULL,
  `sport` varchar(255) NOT NULL,
  `division` varchar(255) NOT NULL,
  `descrip` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL,
  `format` varchar(255) NOT NULL,
  `format_code` varchar(50) DEFAULT NULL,
  `grouping_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `start_date` varchar(255) NOT NULL,
  `end_date` varchar(255) NOT NULL,
  `regis_start` varchar(255) NOT NULL,
  `regis_end` varchar(255) NOT NULL,
  `venue` varchar(255) NOT NULL,
  `max_team` varchar(255) NOT NULL,
  `min_player` int(11) NOT NULL DEFAULT 5,
  `max_player` varchar(255) NOT NULL,
  `min_age` int(11) DEFAULT 0,
  `max_age` int(11) DEFAULT 100,
  `min_male_players` int(11) DEFAULT 0,
  `min_female_players` int(11) DEFAULT 0,
  `registration_window_open` tinyint(1) DEFAULT 1,
  `regis_fee` varchar(255) NOT NULL,
  `add_info` varchar(255) NOT NULL,
  `stat` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `active_unique` tinyint(4) DEFAULT 1,
  `publication_status` enum('Draft','Published') NOT NULL DEFAULT 'Draft',
  `published_at` datetime DEFAULT NULL,
  `scoring_win_points` int(11) NOT NULL DEFAULT 3,
  `scoring_draw_points` int(11) NOT NULL DEFAULT 1,
  `scoring_loss_points` int(11) NOT NULL DEFAULT 0,
  `scoring_forfeit_points` int(11) NOT NULL DEFAULT 0,
  `tiebreak_order` varchar(160) NOT NULL DEFAULT 'head_to_head,quotient,difference,points_for,name',
  `birth_year_min` smallint(6) DEFAULT NULL,
  `birth_year_max` smallint(6) DEFAULT NULL,
  `require_residency` tinyint(1) NOT NULL DEFAULT 0,
  `one_team_per_barangay` tinyint(1) NOT NULL DEFAULT 0,
  `officials_excluded_from_roster` tinyint(1) NOT NULL DEFAULT 0,
  `finals_pattern` enum('semis_final_bronze','direct_final_bronze') DEFAULT NULL,
  `protest_window_minutes` int(11) DEFAULT NULL,
  `scoring_win_clean_points` int(11) DEFAULT NULL,
  `scoring_win_close_points` int(11) DEFAULT NULL,
  `scoring_loss_close_points` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uidx_organizer_active_league` (`created_by`,`league_name`,`sport`,`start_date`,`end_date`,`active_unique`) USING HASH
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_league`
--

LOCK TABLES `tbl_league` WRITE;
/*!40000 ALTER TABLE `tbl_league` DISABLE KEYS */;
INSERT INTO `tbl_league` VALUES (1,'league_5094e05d0c33a63ebd1c515c85773506.webp','guideline_15f6af9d85c296024db197bf5dfe2abe.jpg','Pekelbol 2026','Pickleball','Junior','test','Mixed','Single Elimination','single_elimination',0,'2026-09-06','2026-09-11','2026-09-01','2026-09-05','Panabo Park','10',1,'2',9,12,0,0,1,'0','test','Completed','2026-09-01 12:33:48',3,1,'2026-10-19 23:20:47',NULL,'Published','2026-09-01 12:33:53',3,1,0,0,'head_to_head,quotient,difference,points_for,name',NULL,NULL,0,0,0,NULL,NULL,NULL,NULL,NULL),(2,'https://res.cloudinary.com/u3wlf2nk/image/upload/v1791826248/lms/leagues/league_3d79bf28c1d152d3a35589592caf8a51.png','https://res.cloudinary.com/u3wlf2nk/image/upload/v1791826251/lms/guidelines/guideline_e7e801ae504b1e2ae462f74d3878f48e.pdf','baskitbol Cup 2026','Basketball','Open','test','Male','Round Robin','round_robin',0,'2026-10-19','2026-10-23','2026-10-14','2026-10-16','Panabo Park','8',5,'15',16,50,0,0,1,'0','test','Upcoming','2026-10-13 01:30:51',3,0,NULL,1,'Published','2026-10-13 01:55:37',2,1,1,0,'head_to_head,quotient,difference,points_for,name',NULL,NULL,0,0,0,NULL,NULL,NULL,NULL,NULL),(27,'https://res.cloudinary.com/u3wlf2nk/image/upload/v1791958050/lms/leagues/league_5502098f3eaaba9780d7688b0773543a.jpg','https://res.cloudinary.com/u3wlf2nk/image/upload/v1791958052/lms/guidelines/guideline_09b64a7e70405a701bdea4e6f5c3d2c9.jpg','balebol cup 2026','Volleyball','Open','test','Female','Round Robin','round_robin',0,'2026-10-19','2026-10-23','2026-10-14','2026-10-16','Panabo City Multi-Purpose Tourism Cultural and Sports Center','8',6,'12',16,50,0,0,1,'0','test','Open','2026-10-14 14:07:33',3,0,NULL,1,'Published','2026-10-14 14:07:51',3,1,0,0,'head_to_head,quotient,difference,points_for,name',NULL,NULL,0,0,0,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `tbl_league` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_league_document_requirement`
--

DROP TABLE IF EXISTS `tbl_league_document_requirement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_league_document_requirement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_id` int(11) NOT NULL,
  `document_type_id` int(11) NOT NULL,
  `applies_to` enum('team','player') NOT NULL DEFAULT 'player',
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `instructions` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_league_document_scope` (`league_id`,`document_type_id`,`applies_to`),
  KEY `idx_requirement_league` (`league_id`),
  KEY `fk_requirement_type` (`document_type_id`),
  CONSTRAINT `fk_requirement_league` FOREIGN KEY (`league_id`) REFERENCES `tbl_league` (`id`),
  CONSTRAINT `fk_requirement_type` FOREIGN KEY (`document_type_id`) REFERENCES `tbl_utility` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_league_document_requirement`
--

LOCK TABLES `tbl_league_document_requirement` WRITE;
/*!40000 ALTER TABLE `tbl_league_document_requirement` DISABLE KEYS */;
INSERT INTO `tbl_league_document_requirement` VALUES (1,2,23,'player',1,'','2026-10-13 01:30:51'),(2,27,23,'player',1,'','2026-10-14 14:07:33');
/*!40000 ALTER TABLE `tbl_league_document_requirement` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_league_group`
--

DROP TABLE IF EXISTS `tbl_league_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_league_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_league_group_name` (`league_id`,`name`),
  CONSTRAINT `fk_group_league` FOREIGN KEY (`league_id`) REFERENCES `tbl_league` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_league_group`
--

LOCK TABLES `tbl_league_group` WRITE;
/*!40000 ALTER TABLE `tbl_league_group` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_league_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_league_group_team`
--

DROP TABLE IF EXISTS `tbl_league_group_team`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_league_group_team` (
  `group_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`team_id`),
  KEY `idx_group_team_group` (`group_id`),
  CONSTRAINT `fk_group_team_group` FOREIGN KEY (`group_id`) REFERENCES `tbl_league_group` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_group_team_team` FOREIGN KEY (`team_id`) REFERENCES `tbl_team` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_league_group_team`
--

LOCK TABLES `tbl_league_group_team` WRITE;
/*!40000 ALTER TABLE `tbl_league_group_team` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_league_group_team` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_league_guideline_document`
--

DROP TABLE IF EXISTS `tbl_league_guideline_document`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_league_guideline_document` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_id` int(11) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_guideline_league` (`league_id`,`is_archived`),
  CONSTRAINT `fk_guideline_league` FOREIGN KEY (`league_id`) REFERENCES `tbl_league` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_league_guideline_document`
--

LOCK TABLES `tbl_league_guideline_document` WRITE;
/*!40000 ALTER TABLE `tbl_league_guideline_document` DISABLE KEYS */;
INSERT INTO `tbl_league_guideline_document` VALUES (1,1,'guideline_15f6af9d85c296024db197bf5dfe2abe.jpg','DIAGRAMS-use case.jpg',3,'2026-09-01 12:33:48',0,NULL),(2,2,'https://res.cloudinary.com/u3wlf2nk/image/upload/v1791826251/lms/guidelines/guideline_e7e801ae504b1e2ae462f74d3878f48e.pdf','Activity_2.pdf',3,'2026-10-13 01:30:51',0,NULL),(3,27,'https://res.cloudinary.com/u3wlf2nk/image/upload/v1791958052/lms/guidelines/guideline_09b64a7e70405a701bdea4e6f5c3d2c9.jpg','1.jpg',3,'2026-10-14 14:07:33',0,NULL);
/*!40000 ALTER TABLE `tbl_league_guideline_document` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_league_stage`
--

DROP TABLE IF EXISTS `tbl_league_stage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_league_stage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `stage_type` enum('group','crossover','knockout','placement') NOT NULL DEFAULT 'group',
  `display_order` int(11) NOT NULL DEFAULT 1,
  `qualifiers_per_group` int(11) DEFAULT NULL,
  `carries_previous_results` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `best_of` tinyint(4) DEFAULT NULL,
  `generation_pattern` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_league_stage_name` (`league_id`,`name`),
  KEY `idx_stage_league` (`league_id`,`display_order`),
  CONSTRAINT `fk_stage_league` FOREIGN KEY (`league_id`) REFERENCES `tbl_league` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_league_stage`
--

LOCK TABLES `tbl_league_stage` WRITE;
/*!40000 ALTER TABLE `tbl_league_stage` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_league_stage` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_league_stage_group`
--

DROP TABLE IF EXISTS `tbl_league_stage_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_league_stage_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stage_id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stage_group_name` (`stage_id`,`name`),
  KEY `idx_stage_group_stage` (`stage_id`,`display_order`),
  CONSTRAINT `fk_stage_group_stage` FOREIGN KEY (`stage_id`) REFERENCES `tbl_league_stage` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_league_stage_group`
--

LOCK TABLES `tbl_league_stage_group` WRITE;
/*!40000 ALTER TABLE `tbl_league_stage_group` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_league_stage_group` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_league_stage_group_source`
--

DROP TABLE IF EXISTS `tbl_league_stage_group_source`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_league_stage_group_source` (
  `stage_group_id` int(11) NOT NULL,
  `source_group_id` int(11) NOT NULL,
  PRIMARY KEY (`stage_group_id`,`source_group_id`),
  KEY `fk_sgs_source` (`source_group_id`),
  CONSTRAINT `fk_sgs_group` FOREIGN KEY (`stage_group_id`) REFERENCES `tbl_league_stage_group` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sgs_source` FOREIGN KEY (`source_group_id`) REFERENCES `tbl_league_stage_group` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_league_stage_group_source`
--

LOCK TABLES `tbl_league_stage_group_source` WRITE;
/*!40000 ALTER TABLE `tbl_league_stage_group_source` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_league_stage_group_source` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_league_stage_group_team`
--

DROP TABLE IF EXISTS `tbl_league_stage_group_team`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_league_stage_group_team` (
  `stage_group_id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `seed` int(11) DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`stage_group_id`,`team_id`),
  KEY `idx_sgt_team` (`team_id`),
  CONSTRAINT `fk_sgt_group` FOREIGN KEY (`stage_group_id`) REFERENCES `tbl_league_stage_group` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sgt_team` FOREIGN KEY (`team_id`) REFERENCES `tbl_team` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_league_stage_group_team`
--

LOCK TABLES `tbl_league_stage_group_team` WRITE;
/*!40000 ALTER TABLE `tbl_league_stage_group_team` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_league_stage_group_team` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_match_history`
--

DROP TABLE IF EXISTS `tbl_match_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_match_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schedule_id` int(11) NOT NULL,
  `league_id` int(11) NOT NULL,
  `team_a_id` int(11) NOT NULL,
  `team_b_id` int(11) NOT NULL,
  `team_a_score` int(11) DEFAULT 0,
  `team_b_score` int(11) DEFAULT 0,
  `winner_id` int(11) DEFAULT NULL,
  `match_date` date NOT NULL,
  `match_time` time NOT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `status` enum('Completed','Cancelled','Postponed') DEFAULT 'Completed',
  `notes` longtext DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `schedule_id` (`schedule_id`),
  KEY `winner_id` (`winner_id`),
  KEY `idx_league_date` (`league_id`,`match_date`),
  KEY `idx_team_a` (`team_a_id`),
  KEY `idx_team_b` (`team_b_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `tbl_match_history_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `tbl_schedule` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tbl_match_history_ibfk_2` FOREIGN KEY (`league_id`) REFERENCES `tbl_league` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tbl_match_history_ibfk_3` FOREIGN KEY (`team_a_id`) REFERENCES `tbl_team` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tbl_match_history_ibfk_4` FOREIGN KEY (`team_b_id`) REFERENCES `tbl_team` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tbl_match_history_ibfk_5` FOREIGN KEY (`winner_id`) REFERENCES `tbl_team` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_match_history`
--

LOCK TABLES `tbl_match_history` WRITE;
/*!40000 ALTER TABLE `tbl_match_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_match_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_match_set`
--

DROP TABLE IF EXISTS `tbl_match_set`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_match_set` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schedule_id` int(11) NOT NULL,
  `league_id` int(11) NOT NULL,
  `set_no` tinyint(4) NOT NULL,
  `a_points` smallint(6) NOT NULL DEFAULT 0,
  `b_points` smallint(6) NOT NULL DEFAULT 0,
  `is_deciding` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_match_set_no` (`schedule_id`,`set_no`),
  KEY `idx_match_set_league` (`league_id`),
  CONSTRAINT `fk_match_set_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `tbl_schedule` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=104 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_match_set`
--

LOCK TABLES `tbl_match_set` WRITE;
/*!40000 ALTER TABLE `tbl_match_set` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_match_set` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_notification`
--

DROP TABLE IF EXISTS `tbl_notification`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_notification` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created` (`created`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_notification`
--

LOCK TABLES `tbl_notification` WRITE;
/*!40000 ALTER TABLE `tbl_notification` DISABLE KEYS */;
INSERT INTO `tbl_notification` VALUES (1,6,'registration_closing','Registration Closing Soon','Registration for \"Pekelbol 2026\" closes on Sep 05, 2026. Register your team now!',1,1,'2026-09-02 00:05:16'),(2,6,'announcement','New League Available','A new league \"baskitbol Cup 2026\" is now open for registration. Deadline: Oct 16, 2026.',2,0,'2026-10-13 01:55:37'),(3,6,'announcement','New League Available','A new league \"balebol cup 2026\" is now open for registration. Deadline: Oct 16, 2026.',27,0,'2026-10-14 14:07:51'),(4,7,'announcement','New League Available','A new league \"balebol cup 2026\" is now open for registration. Deadline: Oct 16, 2026.',27,1,'2026-10-14 14:07:51'),(5,8,'announcement','New League Available','A new league \"balebol cup 2026\" is now open for registration. Deadline: Oct 16, 2026.',27,0,'2026-10-14 14:07:51'),(6,9,'announcement','New League Available','A new league \"balebol cup 2026\" is now open for registration. Deadline: Oct 16, 2026.',27,0,'2026-10-14 14:07:51'),(7,10,'announcement','New League Available','A new league \"balebol cup 2026\" is now open for registration. Deadline: Oct 16, 2026.',27,0,'2026-10-14 14:07:51'),(8,11,'announcement','New League Available','A new league \"balebol cup 2026\" is now open for registration. Deadline: Oct 16, 2026.',27,0,'2026-10-14 14:07:51'),(9,12,'announcement','New League Available','A new league \"balebol cup 2026\" is now open for registration. Deadline: Oct 16, 2026.',27,0,'2026-10-14 14:07:51'),(10,13,'announcement','New League Available','A new league \"balebol cup 2026\" is now open for registration. Deadline: Oct 16, 2026.',27,0,'2026-10-14 14:07:51'),(11,14,'announcement','New League Available','A new league \"balebol cup 2026\" is now open for registration. Deadline: Oct 16, 2026.',27,0,'2026-10-14 14:07:51'),(12,15,'announcement','New League Available','A new league \"balebol cup 2026\" is now open for registration. Deadline: Oct 16, 2026.',27,0,'2026-10-14 14:07:51'),(13,16,'announcement','New League Available','A new league \"balebol cup 2026\" is now open for registration. Deadline: Oct 16, 2026.',27,0,'2026-10-14 14:07:51'),(14,17,'announcement','New League Available','A new league \"balebol cup 2026\" is now open for registration. Deadline: Oct 16, 2026.',27,0,'2026-10-14 14:07:51');
/*!40000 ALTER TABLE `tbl_notification` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_player`
--

DROP TABLE IF EXISTS `tbl_player`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_player` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `age` varchar(255) NOT NULL,
  `birthdate` date DEFAULT NULL,
  `role` varchar(255) NOT NULL,
  `gender` varchar(255) NOT NULL,
  `jersey_number` varchar(255) NOT NULL,
  `document` varchar(255) DEFAULT NULL,
  `eligibility_status` varchar(30) NOT NULL DEFAULT 'Pending',
  `eligibility_remarks` varchar(500) DEFAULT NULL,
  `eligibility_reviewed_by` int(11) DEFAULT NULL,
  `eligibility_reviewed_at` datetime DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `barangay` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_player_team_eligibility` (`team_id`,`eligibility_status`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_player`
--

LOCK TABLES `tbl_player` WRITE;
/*!40000 ALTER TABLE `tbl_player` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_player` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_progression_log`
--

DROP TABLE IF EXISTS `tbl_progression_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_progression_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `action` varchar(60) NOT NULL,
  `team_id` int(11) DEFAULT NULL,
  `before_status` varchar(30) DEFAULT NULL,
  `after_status` varchar(30) DEFAULT NULL,
  `created_schedule_ids` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_plog_schedule` (`schedule_id`),
  KEY `idx_plog_league` (`league_id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_progression_log`
--

LOCK TABLES `tbl_progression_log` WRITE;
/*!40000 ALTER TABLE `tbl_progression_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_progression_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_result_protest`
--

DROP TABLE IF EXISTS `tbl_result_protest`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_result_protest` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schedule_id` int(11) NOT NULL,
  `league_id` int(11) NOT NULL,
  `raised_by` int(11) DEFAULT NULL,
  `party` enum('team','committee','organizer') NOT NULL DEFAULT 'team',
  `reason` text NOT NULL,
  `status` enum('Open','Upheld','Denied') NOT NULL DEFAULT 'Open',
  `decided_by` int(11) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `decision_note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_protest_schedule` (`schedule_id`),
  CONSTRAINT `fk_protest_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `tbl_schedule` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_result_protest`
--

LOCK TABLES `tbl_result_protest` WRITE;
/*!40000 ALTER TABLE `tbl_result_protest` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_result_protest` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_schedule`
--

DROP TABLE IF EXISTS `tbl_schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_schedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_id` int(11) NOT NULL,
  `league_group_id` int(11) DEFAULT NULL,
  `team_a` int(11) NOT NULL,
  `a_score` int(11) DEFAULT 0,
  `team_b` int(11) NOT NULL,
  `b_score` int(11) DEFAULT 0,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `round` int(11) DEFAULT 1,
  `match_number` int(11) DEFAULT NULL,
  `is_automated` tinyint(1) DEFAULT 1,
  `conflict_detected` tinyint(1) DEFAULT 0,
  `conflict_reason` varchar(255) DEFAULT NULL,
  `bracket` varchar(50) DEFAULT NULL,
  `group_stage` int(11) DEFAULT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `stage_group_id` int(11) DEFAULT NULL,
  `seed_a` varchar(12) DEFAULT NULL,
  `seed_b` varchar(12) DEFAULT NULL,
  `stage` varchar(50) DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `winner` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `committee_user_id` int(11) DEFAULT NULL,
  `livestream_url` varchar(500) DEFAULT NULL,
  `livestream_status` varchar(50) DEFAULT NULL,
  `result_submitted_by` int(11) DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `result_breakdown` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result_breakdown`)),
  `win_type` enum('Regular','Forfeit','DoubleForfeit','Walkover','Decision') NOT NULL DEFAULT 'Regular',
  `result_note` varchar(255) DEFAULT NULL,
  `penalty_a` int(11) NOT NULL DEFAULT 0,
  `penalty_b` int(11) NOT NULL DEFAULT 0,
  `result_status` enum('Final','Protested','UnderReview','Corrected') NOT NULL DEFAULT 'Final',
  `result_reopened_by` int(11) DEFAULT NULL,
  `result_reopened_at` datetime DEFAULT NULL,
  `best_of` tinyint(4) DEFAULT NULL,
  `sets_a` tinyint(4) DEFAULT NULL,
  `sets_b` tinyint(4) DEFAULT NULL,
  `a_points` int(11) DEFAULT NULL,
  `b_points` int(11) DEFAULT NULL,
  `forfeit_decision_ref` varchar(120) DEFAULT NULL,
  `forfeit_team_id` int(11) DEFAULT NULL,
  `forfeit_applied_by` int(11) DEFAULT NULL,
  `forfeit_applied_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_league_date` (`league_id`,`date`),
  KEY `idx_team_a_date` (`team_a`,`date`,`time`),
  KEY `idx_team_b_date` (`team_b`,`date`,`time`),
  KEY `idx_venue_date` (`venue`,`date`,`time`),
  KEY `idx_status` (`status`),
  KEY `idx_conflict` (`conflict_detected`),
  KEY `fk_schedule_group` (`league_group_id`),
  CONSTRAINT `fk_schedule_group` FOREIGN KEY (`league_group_id`) REFERENCES `tbl_league_group` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=297 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_schedule`
--

LOCK TABLES `tbl_schedule` WRITE;
/*!40000 ALTER TABLE `tbl_schedule` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_schedule` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_scheduling_config`
--

DROP TABLE IF EXISTS `tbl_scheduling_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_scheduling_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `league_id` int(11) NOT NULL,
  `match_duration_minutes` int(11) DEFAULT 60,
  `rest_period_minutes` int(11) DEFAULT 15,
  `earliest_start_time` time DEFAULT '08:00:00',
  `latest_end_time` time DEFAULT '22:00:00',
  `max_matches_per_day` int(11) DEFAULT 10,
  `auto_schedule_enabled` tinyint(1) DEFAULT 1,
  `conflict_check_enabled` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `league_id` (`league_id`),
  KEY `idx_league` (`league_id`),
  CONSTRAINT `tbl_scheduling_config_ibfk_1` FOREIGN KEY (`league_id`) REFERENCES `tbl_league` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_scheduling_config`
--

LOCK TABLES `tbl_scheduling_config` WRITE;
/*!40000 ALTER TABLE `tbl_scheduling_config` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_scheduling_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_submitted_document`
--

DROP TABLE IF EXISTS `tbl_submitted_document`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_submitted_document` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_id` int(11) NOT NULL,
  `player_id` int(11) DEFAULT NULL,
  `requirement_id` int(11) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `quality_status` enum('Pending','Clear','Flagged') NOT NULL DEFAULT 'Pending',
  `quality_metrics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`quality_metrics`)),
  `review_status` enum('Pending','Accepted','Rejected') NOT NULL DEFAULT 'Pending',
  `review_remarks` varchar(500) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_submission_team` (`team_id`),
  KEY `idx_submission_player` (`player_id`),
  KEY `idx_submission_requirement` (`requirement_id`),
  CONSTRAINT `fk_submission_player` FOREIGN KEY (`player_id`) REFERENCES `tbl_player` (`id`),
  CONSTRAINT `fk_submission_requirement` FOREIGN KEY (`requirement_id`) REFERENCES `tbl_league_document_requirement` (`id`),
  CONSTRAINT `fk_submission_team` FOREIGN KEY (`team_id`) REFERENCES `tbl_team` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_submitted_document`
--

LOCK TABLES `tbl_submitted_document` WRITE;
/*!40000 ALTER TABLE `tbl_submitted_document` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_submitted_document` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_team`
--

DROP TABLE IF EXISTS `tbl_team`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_team` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `team_name` varchar(255) NOT NULL,
  `team_image` varchar(255) NOT NULL,
  `league_id` varchar(255) NOT NULL,
  `bracket` varchar(255) NOT NULL,
  `team_repre` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `remarks` varchar(500) DEFAULT NULL,
  `correction_due_at` datetime DEFAULT NULL,
  `correction_expired_at` datetime DEFAULT NULL,
  `preliminary_flags` longtext DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `progression_status` enum('Undecided','Qualified','Eliminated') NOT NULL DEFAULT 'Undecided',
  `progression_decided_by` int(11) DEFAULT NULL,
  `progression_decided_at` datetime DEFAULT NULL,
  `active_registration` tinyint(4) GENERATED ALWAYS AS (case when lcase(`status`) in ('rejected','cancelled') then NULL else 1 end) STORED,
  `barangay` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_team_active_registration` (`league_id`,`user_id`,`active_registration`),
  KEY `idx_team_correction_due` (`status`,`correction_due_at`)
) ENGINE=InnoDB AUTO_INCREMENT=299 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_team`
--

LOCK TABLES `tbl_team` WRITE;
/*!40000 ALTER TABLE `tbl_team` DISABLE KEYS */;
/*!40000 ALTER TABLE `tbl_team` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_user`
--

DROP TABLE IF EXISTS `tbl_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `res_id` varchar(255) NOT NULL DEFAULT '',
  `fname` varchar(255) NOT NULL,
  `lname` varchar(255) NOT NULL,
  `mname` varchar(255) NOT NULL,
  `suffix` varchar(255) NOT NULL,
  `gender` enum('Male','Female','') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(255) NOT NULL,
  `address` varchar(500) DEFAULT NULL,
  `organization_name` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `years_experience` int(11) DEFAULT NULL,
  `credentials` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `pass` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL COMMENT '1 = ORGANIZER\r\n2 = TEAM REPRE',
  `stat` varchar(255) NOT NULL DEFAULT '1',
  `pass_stat` int(11) NOT NULL DEFAULT 1 COMMENT '0=change\r\n1 = hindi pa binabago',
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_user`
--

LOCK TABLES `tbl_user` WRITE;
/*!40000 ALTER TABLE `tbl_user` DISABLE KEYS */;
INSERT INTO `tbl_user` VALUES (1,'','Admin','Admin','','',NULL,NULL,'09000000000',NULL,NULL,NULL,NULL,NULL,NULL,'admin@lms.com','$2y$10$DxXZcHb9wfB3iootNGu42.gIijUwQejqHnoUwdp30cShjEwsXH5JW','3','1',1,'2026-08-29 19:37:27'),(3,'','Keth Lorenz','Valleser','C','','Male','2004-07-31','09276856827','0',NULL,NULL,NULL,'Tournament Manager since 2015','../../uploads/profiles/profile_6a94651fa5123.jpg','Org1@lms.com','$2y$10$JSe7ohbM3HdMxrK2Vx4WU.Y7kVbsvKp57FQ4W34Kzx.BWUNwkvFj2','1','1',0,'2026-08-31 01:14:42'),(4,'','Chris','Bustamante','G','','Male','2025-12-13','09920077260','San Pedro, Panabo City',NULL,NULL,NULL,'CSDP Staff and Tournament Manage Assistant since 2018','../../uploads/profiles/profile_6a94e8258731b.jpg','Org2@lms.com','$2y$10$FJaLhZhDvUQL3CIvou4Q4uioAc1e0Yqim4MBi5BnmQ7Xx90DNjmtK','1','1',0,'2026-08-31 10:31:32'),(5,'','Dave','Dellosa','D','Jr.','Male','2004-12-21','09088184444','Carmen, Davao del Norte',NULL,NULL,NULL,'Sports Event Committee','../../uploads/profiles/profile_6a94ecc4bf63c.jpg','Com1@lms.com','$2y$10$rPAkmSNzpoY.5XAqkL0xPungYBIFsZ.SV6Bbh84Bsx6RJUV9xaR9K','4','1',0,'2026-08-31 10:45:01'),(6,'','Xyroz','Dela Cruz','C','Jr.',NULL,NULL,'09123454321',NULL,NULL,NULL,NULL,NULL,NULL,'xyroz@gmail.com','$2y$10$zGkRy5sj1MmOrSlR7mtJ7.GZULI0sX60bzNX9oxmJLzwo84V28bNK','2','1',0,'2026-09-01 22:48:13'),(7,'','keth','Valleser','C','',NULL,NULL,'0992007260',NULL,NULL,NULL,NULL,NULL,NULL,'keth@gmail.com','$2y$10$Z0LT4krLfoK9n5Ez6NMnJePS29WFN9iJLN4x34L87.Md5vjx1B81e','2','1',0,'2026-10-14 01:42:34'),(8,'','Dereck','Galiza','R','Jr.',NULL,NULL,'09135256345',NULL,NULL,NULL,NULL,NULL,NULL,'dereck@gmail.com','$2y$10$TG8LlYZNPvZ7LiGQELD9VuQ.NESBt4hWKWjRhStKmtpRGAmb4aqaq','2','1',0,'2026-10-14 08:27:41'),(9,'','Christina','Bustamante','G','',NULL,NULL,'09462873956',NULL,NULL,NULL,NULL,NULL,NULL,'chris@gmail.com','$2y$10$UhDLDrgCDxtWRnjAhFy0r.kfs4kyZZ/Ff6yRE/PImxIDWzNkIGvtG','2','1',0,'2026-10-14 08:39:55'),(10,'','Juluis','Balaba','O','',NULL,NULL,'09364859263',NULL,NULL,NULL,NULL,NULL,NULL,'juluis@gmail.com','$2y$10$cNwpanTadg/sFwB7WwAyHeHg/FWvZfmOsloocI3kC.d.HWCEGSsTG','2','1',0,'2026-10-14 08:41:06'),(11,'','David','Deliman','D','',NULL,NULL,'09372638510',NULL,NULL,NULL,NULL,NULL,NULL,'dave@gmail.com','$2y$10$p1hgILl1HEj.UoYjEIs6xOfXFZYX/NXcZ1mAMQw6sHrgl0f4z2vNO','2','1',0,'2026-10-14 08:42:11'),(12,'','Alfie','Gingone','J','',NULL,NULL,'0972849163',NULL,NULL,NULL,NULL,NULL,NULL,'alfie@gmail.com','$2y$10$G0W9IkpUIV0Im/xBF.zuAO7q6.qipMJ4N1gjweNzK00RYRAKoQMZO','2','1',0,'2026-10-14 08:43:05'),(13,'','Big','Boy','N','',NULL,NULL,'09727452748',NULL,NULL,NULL,NULL,NULL,NULL,'bigboy@gmail.com','$2y$10$bZe.rrDlIP0Po361DCyfyupFgQeQysyDHT7JA2AIbM/rEa/B6MRRS','2','1',0,'2026-10-14 08:45:08'),(14,'','Bruno','Mars','D','Jr.',NULL,NULL,'09673556881',NULL,NULL,NULL,NULL,NULL,NULL,'bruno@gmail.com','$2y$10$H9QOpitOTfvjCREiMXRhaOUv4D6sjwR9HWavLTx20estqWmmh99Ky','2','1',0,'2026-10-14 08:47:33'),(15,'','Fanny','Gods','','',NULL,NULL,'09937746119',NULL,NULL,NULL,NULL,NULL,NULL,'fanny@gmail.com','$2y$10$AOHIG.tRrl8jlt/IX9w69uS65ZS9RWqVVsctRcXnJ.BP9XsY/901G','2','1',0,'2026-10-14 08:49:43'),(16,'','Ling','Gods','','',NULL,NULL,'0972661933',NULL,NULL,NULL,NULL,NULL,NULL,'ling@gmail.com','$2y$10$M5Gl9ZGh3AIGhJyx1wZnNuG/ZBQEQa0qZ3bQWR7QgR1gcreBcVIYa','2','1',0,'2026-10-14 08:54:37'),(17,'','Haya','Busa','','',NULL,NULL,'09937744619',NULL,NULL,NULL,NULL,NULL,NULL,'haya@gmail.com','$2y$10$NDfIIUCNDwaYJ5feKwkTNe5RKjFFBBkzO8nEhj7FfNNleA7Z6Jvja','2','1',0,'2026-10-14 08:55:34');
/*!40000 ALTER TABLE `tbl_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbl_utility`
--

DROP TABLE IF EXISTS `tbl_utility`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_utility` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` enum('sport','division','venue','document_type','category','league_format') NOT NULL,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `min_age` int(11) DEFAULT NULL,
  `max_age` int(11) DEFAULT NULL,
  `format_code` varchar(50) DEFAULT NULL,
  `default_min_players` int(11) DEFAULT NULL,
  `default_max_players` int(11) DEFAULT NULL,
  `applies_to` enum('team','player') DEFAULT NULL,
  `result_unit` enum('points','sets') DEFAULT NULL,
  `scoring_mode` enum('simple','set_line') DEFAULT NULL,
  `best_of` tinyint(4) DEFAULT NULL,
  `set_target` smallint(6) DEFAULT NULL,
  `deciding_target` smallint(6) DEFAULT NULL,
  `win_by` smallint(6) DEFAULT NULL,
  `jersey_min` smallint(6) DEFAULT NULL,
  `jersey_max` smallint(6) DEFAULT NULL,
  `roster_regular` smallint(6) DEFAULT NULL,
  `libero_min` tinyint(4) DEFAULT NULL,
  `libero_max` tinyint(4) DEFAULT NULL,
  `require_official` tinyint(4) DEFAULT NULL,
  `protest_window_minutes` int(11) DEFAULT NULL,
  `allow_draw` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_utility_category_name` (`category`,`name`),
  UNIQUE KEY `uq_utility_format_code` (`format_code`),
  KEY `idx_utility_active` (`category`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbl_utility`
--

LOCK TABLES `tbl_utility` WRITE;
/*!40000 ALTER TABLE `tbl_utility` DISABLE KEYS */;
INSERT INTO `tbl_utility` VALUES (1,'league_format','Round Robin',1,1,'2026-08-29 21:33:24',NULL,NULL,'round_robin',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(2,'league_format','Single Elimination',1,NULL,'2026-08-29 21:42:32',NULL,NULL,'single_elimination',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(3,'league_format','Double Elimination',0,NULL,'2026-08-29 21:42:32',NULL,NULL,'double_elimination',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(4,'league_format','Swiss System',0,NULL,'2026-08-29 21:42:32',NULL,NULL,'swiss_system',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(5,'league_format','Group Stage + Knockout',0,NULL,'2026-08-29 21:42:32',NULL,NULL,'group_knockout',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(8,'sport','Basketball',1,1,'2026-08-29 22:15:14',NULL,NULL,NULL,5,15,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(9,'sport','Takraw',1,1,'2026-08-29 22:19:16',NULL,NULL,NULL,3,3,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(10,'sport','Volleyball',1,1,'2026-08-29 22:19:49',NULL,NULL,NULL,12,14,NULL,'sets','set_line',3,25,15,2,1,99,12,1,2,1,30,0),(12,'sport','Soccer',1,1,'2026-08-29 22:20:28',NULL,NULL,NULL,11,22,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(13,'sport','Mobile Legends',1,1,'2026-08-29 22:20:47',NULL,NULL,NULL,5,6,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(14,'sport','Badminton',1,1,'2026-08-29 22:22:26',NULL,NULL,NULL,1,2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(16,'division','Junior',1,1,'2026-08-30 20:17:42',9,12,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(17,'division','Under 18',1,1,'2026-08-30 20:19:00',13,17,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(18,'division','Open',1,1,'2026-08-30 20:19:21',16,50,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(19,'division','Under 21',1,1,'2026-08-30 20:19:57',13,20,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(21,'venue','Panabo City Multi-Purpose Tourism Cultural and Sports Center',1,1,'2026-08-30 20:27:00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(22,'venue','Panabo Park',1,1,'2026-08-30 20:27:15',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(23,'document_type','Birth Certificate',1,1,'2026-08-30 20:27:42',NULL,NULL,NULL,NULL,NULL,'player',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(24,'document_type','Medical',1,1,'2026-08-30 20:27:56',NULL,NULL,NULL,NULL,NULL,'player',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(25,'document_type','Valid ID',1,1,'2026-08-30 20:28:07',NULL,NULL,NULL,NULL,NULL,'player',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(26,'category','Male',1,1,'2026-08-30 20:28:19',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(27,'category','Female',1,1,'2026-08-30 20:28:25',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(28,'category','Mixed',1,1,'2026-08-30 20:28:33',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(29,'sport','Pickleball',1,1,'2026-08-31 22:14:20',NULL,NULL,NULL,1,2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL),(31,'sport','DOTA',1,1,'2026-09-01 11:51:33',NULL,NULL,NULL,5,6,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `tbl_utility` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'lms'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-20  0:42:25

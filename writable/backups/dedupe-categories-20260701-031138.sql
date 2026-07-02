-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: accesscard
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
-- Table structure for table `category`
--

DROP TABLE IF EXISTS `category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `category` (
  `categoryID` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `dt_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `dt_deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`categoryID`),
  UNIQUE KEY `uq_category_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category`
--

LOCK TABLES `category` WRITE;
/*!40000 ALTER TABLE `category` DISABLE KEYS */;
INSERT INTO `category` VALUES (1,'SC','Senior Citizen','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(2,'PWD','Person with Disability','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(3,'SP','Solo Parent','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(4,'B','Bata (Children)','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(5,'FA','Financial Assistance Programs','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(6,'SWPS','Social Welfare Programs and Services','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(7,'EDA','Emergency / Disaster Assistance Programs','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL);
/*!40000 ALTER TABLE `category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services` (
  `serviceID` int(11) NOT NULL,
  `shortcode` varchar(30) DEFAULT NULL,
  `category` text DEFAULT NULL,
  `name` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `dt_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `dt_deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`serviceID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES (1,'EDA1','Emergency / Disaster Assistance Programs','Cash Assistance','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(2,'EDA2','Emergency / Disaster Assistance Programs','Cash for Work','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(3,'EDA3','Emergency / Disaster Assistance Programs','Emergency Shelter (Local)','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(4,'EDA4','Emergency / Disaster Assistance Programs','Emergency Shelter (National / NHA)','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(5,'EDA5','Emergency / Disaster Assistance Programs','Emergency Shelter (Province)','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(6,'EDA6','Emergency / Disaster Assistance Programs','Food for Work','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(7,'EDA7','Emergency / Disaster Assistance Programs','Non-Food Assistance','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(8,'EDA8','Emergency / Disaster Assistance Programs','Relief Food Pack','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(9,'EDA9','Emergency / Disaster Assistance Programs','Temporary Shelter','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(10,'FA1','Financial Assistance Programs','Balik Probinsya','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(11,'FA2','Financial Assistance Programs','Burial Assistance','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(12,'FA3','Financial Assistance Programs','Dental Assistance','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(13,'FA4','Financial Assistance Programs','Eyeglasses Assistance','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(14,'FA5','Financial Assistance Programs','Lingap sa Mahirap','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(15,'FA6','Financial Assistance Programs','Medical Assistance','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(16,'SWPS1','Social Welfare Programs and Services','Balay Silangan','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(17,'SWPS2','Social Welfare Programs and Services','Business Skills Management Training','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(18,'SWPS3','Social Welfare Programs and Services','Counseling / Dialogue','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(19,'SWPS4','Social Welfare Programs and Services','Family Development Session','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(20,'SWPS5','Social Welfare Programs and Services','Gender Sensitivity Training','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(21,'SWPS6','Social Welfare Programs and Services','Legal Assistance / Free Notary','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(22,'SWPS7','Social Welfare Programs and Services','Licensed Foster Parent','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(23,'SWPS8','Social Welfare Programs and Services','Pamaskong Handog','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(24,'SWPS9','Social Welfare Programs and Services','Parent Effectiveness Service','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(25,'SWPS10','Social Welfare Programs and Services','PMOC (Pre-Marriage Orientation / Counseling)','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(26,'SWPS11','Social Welfare Programs and Services','Referral','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(27,'4PS','Social Welfare Programs and Services','4Ps (Pantawid Pamilyang Pilipino Programs)','','2026-06-29 07:22:54','2026-06-29 07:22:54',NULL),(28,'SC1','Senior Citizen','Registered OSCA Biñan','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(29,'SC2','Senior Citizen','Local Pensioner','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(30,'SC3','Senior Citizen','National Pensioner','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(31,'SC4','Senior Citizen','Centenarian Local Awardee','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(32,'SC5','Senior Citizen','Centenarian National Awardee','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(33,'SC6','Senior Citizen','Centenarian Province Awardee','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(34,'SC7','Senior Citizen','Eyeglasses Assistance','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(35,'SC8','Senior Citizen','One Time Cash Incentive (85yrs old)','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(36,'SC9','Senior Citizen','Wheelchair / Crutches','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(37,'PWD1','Person with Disability','Registered PWD in Biñan','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(38,'PWD2','Person with Disability','Biñan City Development Center','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(39,'PWD3','Person with Disability','Birthday Cash Gift','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(40,'PWD4','Person with Disability','Project Aruga','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(41,'PWD5','Person with Disability','Subsidy for Unemployable PWD','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(42,'SP1','Solo Parent','Registered Solo Parent in Biñan','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(43,'SP2','Solo Parent','Monthly Subsidy for Solo Parent','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(44,'B1','Bata (Children)','Bahay Pag-Asa','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(45,'B2','Bata (Children)','ECCD','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(46,'B3','Bata (Children)','Supplementary Feeding Program','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL);
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sector`
--

DROP TABLE IF EXISTS `sector`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sector` (
  `sectorID` int(11) NOT NULL AUTO_INCREMENT,
  `shortcode` varchar(30) NOT NULL,
  `name` text NOT NULL,
  `description` text NOT NULL,
  `dt_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `dt_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `dt_deleted` datetime DEFAULT NULL,
  PRIMARY KEY (`sectorID`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sector`
--

LOCK TABLES `sector` WRITE;
/*!40000 ALTER TABLE `sector` DISABLE KEYS */;
INSERT INTO `sector` VALUES (1,'SC','Senior Citizen','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(2,'PWD','Person with Disability','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(3,'SP','Solo Parent','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(4,'B','Bata (Children)','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(5,'LGBT','LGBTQIA+','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(6,'OFW','Overseas Filipino Worker','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(7,'IP','Indigenous People','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(8,'IDP','Internally Displaced Person','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(9,'PDL','Persons Deprived of Liberty','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL),(10,'OTHER','Other Sectors','','2026-07-01 01:13:55','2026-07-01 01:13:55',NULL);
/*!40000 ALTER TABLE `sector` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-01 11:11:38

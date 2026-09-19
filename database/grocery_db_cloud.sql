

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

DROP TABLE IF EXISTS `api_cart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api_cart` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cart_item` (`user_id`,`product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `api_cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `api_cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `api_cart` WRITE;
/*!40000 ALTER TABLE `api_cart` DISABLE KEYS */;
/*!40000 ALTER TABLE `api_cart` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `discount_percent` int(11) NOT NULL,
  `expiry_date` date NOT NULL,
  `status` varchar(20) DEFAULT 'Active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
INSERT INTO `coupons` VALUES
(1,'WELCOME20',20,'2030-12-31','Active'),
(2,'FRESH50',50,'2030-12-31','Active');
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14449 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES
(11,NULL,1,1),
(12,NULL,2,1),
(13,NULL,3,1),
(14,NULL,4,1),
(15,NULL,5,1),
(14429,4832,1,1),
(14432,4836,1,1),
(14434,4838,1,1),
(14437,4841,1,1),
(14438,4842,1,1),
(14439,4843,1,1),
(14442,4846,1,1),
(14443,4847,1,1),
(14444,4848,1,1),
(14445,4849,1,1),
(14446,4850,1,1),
(14447,4851,1,1),
(14448,4852,1,1);
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `order_date` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_user_id` (`user_id`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4853 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES
(4832,1517,'John Customer','123 Main St, Test City',0.50,'Pending','2026-09-19 12:46:40','2026-09-19 12:46:40'),
(4836,1517,'John Customer','123 Main St, Test City',0.50,'Pending','2026-09-19 12:51:56','2026-09-19 12:51:56'),
(4838,1517,'John Customer','123 Main St, Test City',0.50,'Pending','2026-09-19 13:06:10','2026-09-19 13:06:10'),
(4841,1517,'John Customer','123 Main St, Test City',0.50,'Pending','2026-09-19 14:25:58','2026-09-19 14:25:58'),
(4842,1517,'John Customer','123 Main St, Test City',0.50,'Pending','2026-09-19 14:27:24','2026-09-19 14:27:24'),
(4843,1517,'John Customer','123 Main St, Test City',0.50,'Pending','2026-09-19 14:28:25','2026-09-19 14:28:25'),
(4846,1517,'Audited Customer','456 Test Blvd, Suite 101',0.50,'Delivered','2026-09-19 14:33:44','2026-09-19 14:33:44'),
(4847,1517,'Audited Customer','456 Test Blvd, Suite 101',0.50,'Delivered','2026-09-19 14:35:29','2026-09-19 14:35:29'),
(4848,1517,'Audited Customer','456 Test Blvd, Suite 101',0.50,'Delivered','2026-09-19 14:35:48','2026-09-19 14:35:48'),
(4849,1517,'Audited Customer','456 Test Blvd, Suite 101',0.50,'Delivered','2026-09-19 14:35:58','2026-09-19 14:35:58'),
(4850,1517,'John Customer','123 Main St, Test City',0.50,'Pending','2026-09-19 14:36:05','2026-09-19 14:36:05'),
(4851,1517,'Audited Customer','456 Test Blvd, Suite 101',0.50,'Delivered','2026-09-19 14:37:23','2026-09-19 14:37:23'),
(4852,1517,'Audited Customer','456 Test Blvd, Suite 101',0.50,'Delivered','2026-09-19 15:03:26','2026-09-19 15:03:26');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT 'default.jpg',
  `stock_qty` int(11) DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_products_category` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=1857 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES
(1,'Red Apple',0.50,'692a82c013aab.png',307,NULL,'Fruits'),
(2,'Banana Bundle',1.20,'692a82a036702.png',99,NULL,'Fruits'),
(3,'Whole Milk',3.50,'692a8282ba25d.png',80,NULL,'Dairy'),
(4,'Sourdough Bread',4.00,'692a8260a0462.png',94,NULL,'Bakery'),
(5,'Green Grapes',3.50,'692a823e6acba.png',84,NULL,'Fruits'),
(6,'Green Apple',0.55,'692a8217a5426.png',84,NULL,'Fruits'),
(7,'Red Grapes',3.50,'692a81e99d9c5.png',68,NULL,'Fruits'),
(8,'Watermelon',5.00,'692a81c4a4e23.png',77,NULL,'Fruits'),
(9,'Pineapple',3.20,'692a81a4ed6f1.png',82,NULL,'Fruits'),
(10,'Strawberries',4.50,'692a7f99a7948.png',79,NULL,'Fruits'),
(11,'Blueberries',4.99,'692a7f70c7bf4.png',95,NULL,'Fruits'),
(12,'Lemon',0.40,'692a7f3546b97.png',89,NULL,'Fruits'),
(13,'Lime',0.45,'692a7f0c3497a.png',61,NULL,'Fruits'),
(14,'Peach',1.10,'692a7ee707ffe.png',77,NULL,'Fruits'),
(15,'Pear',0.90,'692a7ddc1bcd8.png',98,NULL,'Fruits'),
(16,'Cherry Pack',6.00,'692a7dbe8c9d7.png',70,NULL,'Fruits'),
(17,'Mango',1.50,'692a7da274d65.png',93,NULL,'Fruits'),
(18,'Avocado',1.80,'692a7d71228ea.png',62,NULL,'Vegetables'),
(19,'Carrot Bag',2.00,'692a7d544f905.png',70,NULL,'Vegetables'),
(20,'Broccoli',1.75,'692a7c7dd5a57.png',63,NULL,'Vegetables'),
(21,'Spinach',2.50,'692a7c4085eb2.png',95,NULL,'Vegetables'),
(22,'Cucumber',0.80,'692a7bf5999bc.png',94,NULL,'Vegetables'),
(23,'Tomato',0.60,'692a7bd440c02.png',85,NULL,'Vegetables'),
(24,'Potato Bag',4.50,'692a7bb97839a.png',87,NULL,'Vegetables'),
(25,'Onion Bag',3.00,'692a7a62958e7.png',81,NULL,'Vegetables'),
(26,'Garlic',0.50,'692a7a3f2dfac.png',73,NULL,'Vegetables'),
(27,'Bell Pepper (Red)',1.20,'692a7a20c6edf.png',89,NULL,'Vegetables'),
(28,'Bell Pepper (Green)',1.00,'692a7a00bf9d4.png',57,NULL,'Vegetables'),
(29,'Lettuce',1.50,'692a79ddba72d.png',56,NULL,'Vegetables'),
(30,'Cheddar Cheese',5.50,'692a786550b27.png',100,NULL,'Dairy'),
(31,'Swiss Cheese',6.00,'692a784719272.png',94,NULL,'Dairy'),
(32,'Yogurt (Plain)',1.00,'692a78236c5ad.png',71,NULL,'Dairy'),
(33,'Yogurt (Strawberry)',1.00,'692a77f6d0347.png',63,NULL,'Dairy'),
(34,'Butter',4.00,'692a77d3b1cc8.png',93,NULL,'Dairy'),
(35,'Cream Cheese',2.50,'692a770f9cf6d.png',82,NULL,'Dairy'),
(36,'Almond Milk',3.80,'692a76e6b7569.png',78,NULL,'Dairy'),
(37,'Soy Milk',3.60,'692a768bbe03f.png',88,NULL,'Dairy'),
(38,'Chocolate Milk',2.50,'692a766d2f3d9.png',62,NULL,'Dairy'),
(39,'Heavy Cream',3.00,'692a76251fabf.png',81,NULL,'Dairy'),
(40,'Sour Cream',1.80,'692a755633a4a.png',75,NULL,'Dairy'),
(41,'Whole Wheat Bread',3.50,'692a752e3187a.png',75,NULL,'Bakery'),
(42,'Bagels (6 Pack)',4.00,'692a74d8211d4.png',95,NULL,'Bakery'),
(43,'Croissants (4 Pack)',5.50,'692a74a3ebe90.png',57,NULL,'Bakery'),
(44,'Donuts (Dozen)',8.00,'692a7472e182b.png',83,NULL,'Bakery'),
(45,'Muffins (Blueberry)',4.50,'692a73f24202e.png',97,NULL,'Bakery'),
(46,'Baguette',2.00,'692a73d3e6726.png',92,NULL,'Bakery'),
(47,'Tortillas',2.50,'692a73a74db10.png',69,NULL,'Bakery'),
(48,'Hamburger Buns',3.00,'692a73592f398.png',61,NULL,'Bakery'),
(49,'Hotdog Buns',3.00,'692a7328759e0.png',89,NULL,'Bakery'),
(50,'Chocolate Cake',12.00,'692a726e481b0.png',70,NULL,'Bakery'),
(51,'Chicken Breast',8.50,'692a7247e84b0.png',75,NULL,'Meat'),
(52,'Ground Beef',7.00,'692a7223a5cfa.png',64,NULL,'Meat'),
(53,'Steak',15.00,'692a71fd94474.png',86,NULL,'Meat'),
(54,'Pork Chops',9.00,'692a71d12de34.png',91,NULL,'Meat'),
(55,'Bacon',6.50,'692a70fe50445.png',98,NULL,'Meat'),
(56,'Sausages',5.50,'692a70da00d79.png',69,NULL,'Meat'),
(57,'Salmon Fillet',12.00,'692a70a076cc4.png',89,NULL,'Meat'),
(58,'Shrimp',14.00,'692a7080e9c3b.png',93,NULL,'Meat'),
(59,'Tuna Can',1.50,'692a703b53a03.png',97,NULL,'Meat'),
(60,'Turkey Slices',5.00,'692a6fadb3d41.png',57,NULL,'Meat'),
(61,'Potato Chips',3.00,'692a6f2bcd223.png',80,NULL,'Snacks'),
(62,'Pretzels',2.50,'6929c64374ecb.png',83,NULL,'Snacks'),
(63,'Popcorn',2.00,'6929c614bfcd0.png',74,NULL,'Snacks'),
(64,'Chocolate Bar',1.20,'692a8344c5895.png',66,NULL,'Snacks'),
(65,'Gummy Bears',1.50,'6929c5b4a90b4.png',98,NULL,'Snacks'),
(66,'Cookies',3.50,'6929c4bd8d140.png',100,NULL,'Snacks'),
(67,'Crackers',2.80,'6929c493bef42.png',62,NULL,'Snacks'),
(68,'Trail Mix',4.50,'6929c398db3df.png',92,NULL,'Snacks'),
(69,'Granola Bars',3.80,'6929c45ebafbd.png',82,NULL,'Snacks'),
(70,'Beef Jerky',6.00,'6929c32f9ea4f.png',79,NULL,'Snacks'),
(71,'Soda (Cola)',1.50,'6929c13dd4f85.png',95,NULL,'Beverages'),
(72,'Soda (Lemon)',1.50,'6929c11580730.png',92,NULL,'Beverages'),
(73,'Water Bottle',1.00,'6929c066d8c3e.png',74,NULL,'Beverages'),
(74,'Sparkling Water',1.20,'6929bfde1c323.png',84,NULL,'Beverages'),
(75,'Iced Tea',2.00,'6929bfacdb0c9.png',97,NULL,'Beverages'),
(76,'Lemonade',2.50,'6929bf835ed9d.png',88,NULL,'Beverages'),
(77,'Energy Drink',3.00,'6929bf45e6e51.png',93,NULL,'Beverages'),
(78,'Coffee Beans',12.00,'6929bf03ad964.png',57,NULL,'Beverages'),
(79,'Green Tea Box',4.00,'6929bec49db6e.png',87,NULL,'Beverages'),
(80,'Apple Juice',3.50,'6929be98d9092.png',73,NULL,'Beverages'),
(81,'Pasta',1.50,'6929be71deb84.png',93,NULL,'Pantry'),
(82,'Rice (5lb)',6.00,'6929be42758b8.png',57,NULL,'Pantry'),
(83,'Tomato Sauce',2.00,'6929be2173db9.png',88,NULL,'Pantry'),
(84,'Olive Oil',8.00,'6929bdf4328b0.png',78,NULL,'Pantry'),
(85,'Vegetable Oil',4.00,'6929bdc17cb68.png',72,NULL,'Pantry'),
(86,'Flour',3.00,'6929bd6d5c09e.png',70,NULL,'Pantry'),
(87,'Sugar',2.50,'6929bd16a7b1b.png',82,NULL,'Pantry'),
(88,'Salt',1.00,'6929bca2a04e9.png',96,NULL,'Pantry'),
(89,'Pepper',3.00,'6929bc637e961.png',89,NULL,'Pantry'),
(90,'Honey',6.50,'6929bbe2c1d32.png',55,NULL,'Pantry'),
(91,'Peanut Butter',4.00,'6929bb8201c00.png',92,NULL,'Pantry'),
(92,'Jam (Strawberry)',3.50,'6929bb44df76e.png',57,NULL,'Pantry'),
(93,'Ketchup',2.50,'6929bb0718198.png',93,NULL,'Pantry'),
(94,'Mustard',2.00,'6929baa7bccdc.png',56,NULL,'Pantry'),
(95,'Mayonnaise',3.50,'6929ba7e4e7ba.png',86,NULL,'Pantry'),
(96,'Cereal',4.50,'6929ba1047403.png',66,NULL,'Pantry'),
(97,'Oatmeal',3.00,'6929b9b3a676e.png',66,NULL,'Pantry'),
(98,'Pancake Mix',3.50,'6929b97bb8039.png',75,NULL,'Pantry'),
(99,'Syrup',4.00,'6929b92e2c243.png',79,NULL,'Pantry'),
(100,'Soup Can',1.80,'6929b851cb2d7.png',69,NULL,'Pantry'),
(101,'The Apple',11.00,'6929b7d567f89.png',90,NULL,'Fruits');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=236 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
INSERT INTO `reviews` VALUES
(230,1,1517,5,'Verified automated test review','2026-09-19 14:33:43'),
(231,1,1517,5,'Verified automated test review','2026-09-19 14:35:29'),
(232,1,1517,5,'Verified automated test review','2026-09-19 14:35:48'),
(233,1,1517,5,'Verified automated test review','2026-09-19 14:35:58'),
(234,1,1517,5,'Verified automated test review','2026-09-19 14:37:23'),
(235,1,1517,5,'Verified automated test review','2026-09-19 15:03:26');
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `api_token` varchar(64) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'customer',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=1519 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1517,'Audited Customer Verified','customer@example.com','$2y$10$G6UlCCSt9A3jcwCiykuHAe1xkUB9FP/U21e7RER72xOmIHILQfIwO','789 Updated Lane','2026-09-19 12:25:09','eb1142368edcbbb65672dd39bc1749056b8376d4526c3dabe4031c023c730d29','customer');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


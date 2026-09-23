-- FreshCart Cloud Database Schema & Initial Catalog
-- Target Environment: InfinityFree MySQL Hosting
-- Generated on: 2026-09-23 12:37:28

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

-- --------------------------------------------------------
-- Table structure for table `users`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `users`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table structure for table `products`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `products`;
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
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` (`id`, `name`, `price`, `image`, `stock_qty`, `image_url`, `category`) VALUES
(1,'Red Apple',30.00,'692a82c013aab.png',97,NULL,'Fruits'),
(2,'Banana Bundle',65.00,'692a82a036702.png',99,NULL,'Fruits'),
(3,'Whole Milk',195.00,'692a8282ba25d.png',79,NULL,'Dairy'),
(4,'Sourdough Bread',220.00,'692a8260a0462.png',94,NULL,'Bakery'),
(5,'Green Grapes',195.00,'692a823e6acba.png',84,NULL,'Fruits'),
(6,'Green Apple',30.00,'692a8217a5426.png',84,NULL,'Fruits'),
(7,'Red Grapes',195.00,'692a81e99d9c5.png',68,NULL,'Fruits'),
(8,'Watermelon',275.00,'692a81c4a4e23.png',77,NULL,'Fruits'),
(9,'Pineapple',175.00,'692a81a4ed6f1.png',82,NULL,'Fruits'),
(10,'Strawberries',250.00,'692a7f99a7948.png',77,NULL,'Fruits'),
(11,'Blueberries',275.00,'692a7f70c7bf4.png',95,NULL,'Fruits'),
(12,'Lemon',20.00,'692a7f3546b97.png',89,NULL,'Fruits'),
(13,'Lime',25.00,'692a7f0c3497a.png',61,NULL,'Fruits'),
(14,'Peach',60.00,'692a7ee707ffe.png',77,NULL,'Fruits'),
(15,'Pear',50.00,'692a7ddc1bcd8.png',98,NULL,'Fruits'),
(16,'Cherry Pack',330.00,'692a7dbe8c9d7.png',70,NULL,'Fruits'),
(17,'Mango',85.00,'692a7da274d65.png',93,NULL,'Fruits'),
(18,'Avocado',100.00,'692a7d71228ea.png',62,NULL,'Vegetables'),
(19,'Carrot Bag',110.00,'692a7d544f905.png',70,NULL,'Vegetables'),
(20,'Broccoli',95.00,'692a7c7dd5a57.png',63,NULL,'Vegetables'),
(21,'Spinach',140.00,'692a7c4085eb2.png',95,NULL,'Vegetables'),
(22,'Cucumber',45.00,'692a7bf5999bc.png',94,NULL,'Vegetables'),
(23,'Tomato',35.00,'692a7bd440c02.png',85,NULL,'Vegetables'),
(24,'Potato Bag',250.00,'692a7bb97839a.png',87,NULL,'Vegetables'),
(25,'Onion Bag',165.00,'692a7a62958e7.png',81,NULL,'Vegetables'),
(26,'Garlic',30.00,'692a7a3f2dfac.png',73,NULL,'Vegetables'),
(27,'Bell Pepper (Red)',65.00,'692a7a20c6edf.png',89,NULL,'Vegetables'),
(28,'Bell Pepper (Green)',55.00,'692a7a00bf9d4.png',57,NULL,'Vegetables'),
(29,'Lettuce',85.00,'692a79ddba72d.png',56,NULL,'Vegetables'),
(30,'Cheddar Cheese',305.00,'692a786550b27.png',100,NULL,'Dairy'),
(31,'Swiss Cheese',330.00,'692a784719272.png',94,NULL,'Dairy'),
(32,'Yogurt (Plain)',55.00,'692a78236c5ad.png',71,NULL,'Dairy'),
(33,'Yogurt (Strawberry)',55.00,'692a77f6d0347.png',63,NULL,'Dairy'),
(34,'Butter',220.00,'692a77d3b1cc8.png',93,NULL,'Dairy'),
(35,'Cream Cheese',140.00,'692a770f9cf6d.png',82,NULL,'Dairy'),
(36,'Almond Milk',210.00,'692a76e6b7569.png',78,NULL,'Dairy'),
(37,'Soy Milk',200.00,'692a768bbe03f.png',88,NULL,'Dairy'),
(38,'Chocolate Milk',140.00,'692a766d2f3d9.png',62,NULL,'Dairy'),
(39,'Heavy Cream',165.00,'692a76251fabf.png',81,NULL,'Dairy'),
(40,'Sour Cream',100.00,'692a755633a4a.png',75,NULL,'Dairy'),
(41,'Whole Wheat Bread',195.00,'692a752e3187a.png',75,NULL,'Bakery'),
(42,'Bagels (6 Pack)',220.00,'692a74d8211d4.png',95,NULL,'Bakery'),
(43,'Croissants (4 Pack)',305.00,'692a74a3ebe90.png',57,NULL,'Bakery'),
(44,'Donuts (Dozen)',440.00,'692a7472e182b.png',83,NULL,'Bakery'),
(45,'Muffins (Blueberry)',250.00,'692a73f24202e.png',97,NULL,'Bakery'),
(46,'Baguette',110.00,'692a73d3e6726.png',92,NULL,'Bakery'),
(47,'Tortillas',140.00,'692a73a74db10.png',69,NULL,'Bakery'),
(48,'Hamburger Buns',165.00,'692a73592f398.png',61,NULL,'Bakery'),
(49,'Hotdog Buns',165.00,'692a7328759e0.png',89,NULL,'Bakery'),
(50,'Chocolate Cake',660.00,'692a726e481b0.png',70,NULL,'Bakery');
INSERT INTO `products` (`id`, `name`, `price`, `image`, `stock_qty`, `image_url`, `category`) VALUES
(51,'Chicken Breast',470.00,'692a7247e84b0.png',75,NULL,'Meat'),
(52,'Ground Beef',385.00,'692a7223a5cfa.png',64,NULL,'Meat'),
(53,'Steak',825.00,'692a71fd94474.png',86,NULL,'Meat'),
(54,'Pork Chops',495.00,'692a71d12de34.png',91,NULL,'Meat'),
(55,'Bacon',360.00,'692a70fe50445.png',98,NULL,'Meat'),
(56,'Sausages',305.00,'692a70da00d79.png',69,NULL,'Meat'),
(57,'Salmon Fillet',660.00,'692a70a076cc4.png',89,NULL,'Meat'),
(58,'Shrimp',770.00,'692a7080e9c3b.png',93,NULL,'Meat'),
(59,'Tuna Can',85.00,'692a703b53a03.png',97,NULL,'Meat'),
(60,'Turkey Slices',275.00,'692a6fadb3d41.png',57,NULL,'Meat'),
(61,'Potato Chips',165.00,'692a6f2bcd223.png',80,NULL,'Snacks'),
(62,'Pretzels',140.00,'6929c64374ecb.png',83,NULL,'Snacks'),
(63,'Popcorn',110.00,'6929c614bfcd0.png',74,NULL,'Snacks'),
(64,'Chocolate Bar',65.00,'692a8344c5895.png',66,NULL,'Snacks'),
(65,'Gummy Bears',85.00,'6929c5b4a90b4.png',98,NULL,'Snacks'),
(66,'Cookies',195.00,'6929c4bd8d140.png',100,NULL,'Snacks'),
(67,'Crackers',155.00,'6929c493bef42.png',62,NULL,'Snacks'),
(68,'Trail Mix',250.00,'6929c398db3df.png',92,NULL,'Snacks'),
(69,'Granola Bars',210.00,'6929c45ebafbd.png',82,NULL,'Snacks'),
(70,'Beef Jerky',330.00,'6929c32f9ea4f.png',79,NULL,'Snacks'),
(71,'Soda (Cola)',85.00,'6929c13dd4f85.png',95,NULL,'Beverages'),
(72,'Soda (Lemon)',85.00,'6929c11580730.png',92,NULL,'Beverages'),
(73,'Water Bottle',55.00,'6929c066d8c3e.png',74,NULL,'Beverages'),
(74,'Sparkling Water',65.00,'6929bfde1c323.png',84,NULL,'Beverages'),
(75,'Iced Tea',110.00,'6929bfacdb0c9.png',97,NULL,'Beverages'),
(76,'Lemonade',140.00,'6929bf835ed9d.png',88,NULL,'Beverages'),
(77,'Energy Drink',165.00,'6929bf45e6e51.png',93,NULL,'Beverages'),
(78,'Coffee Beans',660.00,'6929bf03ad964.png',57,NULL,'Beverages'),
(79,'Green Tea Box',220.00,'6929bec49db6e.png',87,NULL,'Beverages'),
(80,'Apple Juice',195.00,'6929be98d9092.png',73,NULL,'Beverages'),
(81,'Pasta',85.00,'6929be71deb84.png',93,NULL,'Pantry'),
(82,'Rice (5lb)',330.00,'6929be42758b8.png',57,NULL,'Pantry'),
(83,'Tomato Sauce',110.00,'6929be2173db9.png',88,NULL,'Pantry'),
(84,'Olive Oil',440.00,'6929bdf4328b0.png',78,NULL,'Pantry'),
(85,'Vegetable Oil',220.00,'6929bdc17cb68.png',72,NULL,'Pantry'),
(86,'Flour',165.00,'6929bd6d5c09e.png',70,NULL,'Pantry'),
(87,'Sugar',140.00,'6929bd16a7b1b.png',82,NULL,'Pantry'),
(88,'Salt',55.00,'6929bca2a04e9.png',96,NULL,'Pantry'),
(89,'Pepper',165.00,'6929bc637e961.png',89,NULL,'Pantry'),
(90,'Honey',360.00,'6929bbe2c1d32.png',55,NULL,'Pantry'),
(91,'Peanut Butter',220.00,'6929bb8201c00.png',92,NULL,'Pantry'),
(92,'Jam (Strawberry)',195.00,'6929bb44df76e.png',57,NULL,'Pantry'),
(93,'Ketchup',140.00,'6929bb0718198.png',93,NULL,'Pantry'),
(94,'Mustard',110.00,'6929baa7bccdc.png',55,NULL,'Pantry'),
(95,'Mayonnaise',195.00,'6929ba7e4e7ba.png',86,NULL,'Pantry'),
(96,'Cereal',250.00,'6929ba1047403.png',66,NULL,'Pantry'),
(97,'Oatmeal',165.00,'6929b9b3a676e.png',66,NULL,'Pantry'),
(98,'Pancake Mix',195.00,'6929b97bb8039.png',75,NULL,'Pantry'),
(99,'Syrup',220.00,'6929b92e2c243.png',78,NULL,'Pantry'),
(100,'Soup Can',100.00,'6929b851cb2d7.png',69,NULL,'Pantry');
INSERT INTO `products` (`id`, `name`, `price`, `image`, `stock_qty`, `image_url`, `category`) VALUES
(101,'The Apple',605.00,'6929b7d567f89.png',90,NULL,'Fruits'),
(2013,'SHEESH',150.00,'prod_6ab27a575508d.png',1,NULL,'Fruits');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;


-- --------------------------------------------------------
-- Table structure for table `coupons`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `discount_percent` int(11) NOT NULL,
  `expiry_date` date NOT NULL,
  `status` varchar(20) DEFAULT 'Active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
INSERT INTO `coupons` (`id`, `code`, `discount_percent`, `expiry_date`, `status`) VALUES
(1,'WELCOME20',20,'2030-12-31','Active'),
(2,'FRESH50',50,'2030-12-31','Active');
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
UNLOCK TABLES;


-- --------------------------------------------------------
-- Table structure for table `orders`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `orders`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table structure for table `order_items`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `order_items`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table structure for table `reviews`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `reviews`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table structure for table `api_cart`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `api_cart`;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
